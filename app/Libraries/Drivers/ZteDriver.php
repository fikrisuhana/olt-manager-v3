<?php

namespace App\Libraries\Drivers;

use App\Libraries\TelnetService;

/**
 * Driver untuk OLT ZTE (C320, C600, C650, dll)
 * Diverifikasi langsung dengan ZTE C320 v1.2
 *
 * Format output aktual OLT:
 * - show gpon onu baseinfo gpon-olt_B/S/P → list ONU + SN per port
 * - show gpon onu state                   → status semua ONU (format: B/S/P:I enable enable working)
 * - show pon power attenuation            → up Rx :-26.072(dbm) | down Rx:-21.670(dbm)
 */
class ZteDriver implements OltDriverInterface
{
    private TelnetService $telnet;
    private array $config;

    private array $rootPrompt   = ['#'];
    private array $configPrompt = ['config)#'];
    private array $ifPrompt     = ['config-if)#'];
    // v1.x: (config-pon-onu)# / (config-if-pon)#  ;  v2.x: (gpon-onu-mng B/S/P:I)#
    private array $mngPrompt    = ['config-pon-onu)#', 'config-if-pon)#', 'onu-mng'];
    private array $anyPrompt    = ['#', 'config)#', 'config-if)#'];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->telnet = new TelnetService();
    }

    public function connect(): void
    {
        $this->telnet->connect(
            $this->config['ip'],
            (int)($this->config['telnet_port'] ?? 23),
            $this->config['telnet_user'],
            $this->config['telnet_pass']
        );

        // Cek apakah masuk user mode (>) atau langsung privileged mode (#)
        $echo = $this->telnet->execute('', ['#', '>'], 3);

        if (strpos($echo, '#') === false) {
            // User mode — kirim enable, handle password jika ada
            $enableResp = $this->telnet->execute('enable', ['Password:', 'password:', '#'], 5);
            if (stripos($enableResp, 'password:') !== false) {
                $enablePass = trim($this->config['enable_password'] ?? '');
                $this->telnet->send($enablePass);
                $this->telnet->waitFor(['#'], 5);
            }
            $echo = $this->telnet->execute('', ['#'], 3);
        }

        // Detect actual hostname prompt — tiap OLT bisa beda hostname (OLT2#, GPON#, dll)
        if (preg_match('/(\S+#)\s*$/', trim($echo), $m)) {
            $this->rootPrompt = [$m[1]];
            $this->anyPrompt  = [$m[1], 'config)#', 'config-if)#'];
        }
        // Disable pager agar output tidak terpotong "--More--"
        $this->telnet->execute('terminal length 0', array_merge($this->rootPrompt, ['#']), 5);
    }

    public function disconnect(): void
    {
        $this->telnet->disconnect();
    }

    /**
     * ONU yang belum dikonfigurasi.
     * Jika tidak ada, OLT kembalikan: %Code 62310-GPONSRV : No related information to show.
     * Output saat ada: gpon-onu_1/1/1:X   FHTTXXXXXXXX   unknown
     */
    public function getUnconfiguredOnus(): array
    {
        $output = $this->telnet->execute('show gpon onu uncfg', $this->rootPrompt, 20);

        if (stripos($output, 'No related information') !== false) {
            return [];
        }

        $onus  = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            // Format: gpon-onu_1/1/1:3   FHTTXXXXXXXX   unknown
            if (preg_match('/gpon-onu_(\d+)\/(\d+)\/(\d+):(\d+)\s+([A-Za-z0-9]{8,20})\s+(\S+)/', $line, $m)) {
                $onus[] = [
                    'board'     => $m[1],
                    'slot'      => $m[2],
                    'port'      => $m[3],
                    'onu_index' => $m[4],
                    'sn'        => strtoupper($m[5]),
                    'state'     => $m[6],
                ];
            }
        }
        return $onus;
    }

    /**
     * ONU yang sudah terdaftar dengan SN.
     * Menggunakan "show gpon onu baseinfo gpon-olt_B/S/P" per port.
     * Format: gpon-onu_1/1/1:1    ALL-ONT     sn      SN:FHTT05FFE238         ready
     *
     * Alur: parse "show gpon onu state" untuk tahu port apa saja yang ada,
     * kemudian query baseinfo per port.
     */
    public function getRegisteredOnus(): array
    {
        // Ambil port unik dari state output
        $stateOutput = $this->telnet->execute('show gpon onu state', $this->rootPrompt, 20);
        $ports = $this->parseUniquePorts($stateOutput);

        if (empty($ports)) return [];

        $onus = [];
        foreach ($ports as $portKey) {
            $baseinfoOutput = $this->telnet->execute(
                "show gpon onu baseinfo gpon-olt_{$portKey}",
                $this->rootPrompt, 20
            );
            $parsed = $this->parseBaseinfoOutput($baseinfoOutput, $portKey);
            $onus   = array_merge($onus, $parsed);
        }
        return $onus;
    }

    /**
     * Parse "show gpon onu state" untuk mendapatkan daftar port unik.
     * Format baris: 1/1/1:1     enable       enable      working      1(GPON)
     */
    private function parseUniquePorts(string $output): array
    {
        $ports = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            // Format: B/S/P:I  state  state  phase  channel
            if (preg_match('/^(\d+)\/(\d+)\/(\d+):\d+\s/', $line, $m)) {
                $portKey = "{$m[1]}/{$m[2]}/{$m[3]}";
                $ports[$portKey] = true;
            }
        }
        return array_keys($ports);
    }

    /**
     * Parse output "show gpon onu baseinfo gpon-olt_B/S/P"
     * Format: gpon-onu_1/1/1:1    ALL-ONT     sn      SN:FHTT05FFE238         ready
     * Ada kasus line wrap untuk nama type yang panjang (misal HG8243C-OPEN)
     */
    private function parseBaseinfoOutput(string $output, string $port): array
    {
        $onus   = [];
        $lines  = explode("\n", $output);
        $count  = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $line = trim($lines[$i]);
            // Match normal line
            if (preg_match('/gpon-onu_(\d+)\/(\d+)\/(\d+):(\d+)\s+(\S+)\s+\S+\s+SN:([A-Za-z0-9]+)\s+(\S+)/', $line, $m)) {
                $onus[] = [
                    'board'     => $m[1],
                    'slot'      => $m[2],
                    'port'      => $m[3],
                    'onu_index' => $m[4],
                    'onu_type'  => $m[5],
                    'sn'        => strtoupper($m[6]),
                    'status'    => $m[7],
                ];
            }
            // Handle line wrap: gpon-onu_1/1/1:40   HG8243C-OPE sn      SN:... ready
            // followed by next line:                     N
            elseif (preg_match('/gpon-onu_(\d+)\/(\d+)\/(\d+):(\d+)\s+(\S+)$/', $line, $m)) {
                // Check next line for continuation
                $nextLine = trim($lines[$i + 1] ?? '');
                if (preg_match('/^([A-Z0-9]+)\s+\S+\s+SN:([A-Za-z0-9]+)\s+(\S+)/', $nextLine, $nm)) {
                    $onus[] = [
                        'board'     => $m[1],
                        'slot'      => $m[2],
                        'port'      => $m[3],
                        'onu_index' => $m[4],
                        'onu_type'  => $m[5] . $nm[1],
                        'sn'        => strtoupper($nm[2]),
                        'status'    => $nm[3],
                    ];
                    $i++; // skip next line
                }
            }
        }
        return $onus;
    }

    /**
     * Register ONU ke OLT via Telnet CLI.
     *
     * Params wajib : board, slot, port, onu_index, onu_type, sn, name
     * Params config : vlan_internet, vlan_acs, tcont_profile, pppoe_user (untuk disimpan ke DB)
     * Params extra  : gpon_onu_script (script tambahan untuk gpon-onu interface)
     *
     * CLI yang digenerate (diverifikasi vs ZTE C320 v1.2):
     *   interface gpon-olt_1/1/1
     *     onu 1 type ALL-ONT sn FHTTXXXXXXXX
     *   exit
     *   interface gpon-onu_1/1/1:1
     *     name PELANGGAN
     *     sn-bind enable sn
     *     tcont 1 name tcont profile 250M
     *     gemport 1 name gemport tcont 1
     *     gemport 1 traffic-limit upstream 250M downstream 250M   ← hanya jika tcont_profile diisi
     *     service-port 1 vport 1 user-vlan 100 vlan 100           ← vlan_internet
     *     service-port 2 vport 1 user-vlan 155 vlan 155           ← vlan_acs
     *   exit
     *   write
     *
     * Catatan PPPoE: tidak dikonfigurasi via OMCI (pon-onu-mng ip-host tidak dipakai).
     * PPPoE dipush via GenieACS/TR-069 setelah ONU online — untuk semua brand ONU.
     */
    /**
     * Terapkan blok "interface gpon-onu_B/S/P:I" (name, sn-bind, tcont, gemport, service-port).
     * Dipakai bersama oleh registerOnu() dan registerOnuBatch() supaya perilakunya tak bercabang.
     * Asumsi: sudah berada di config mode.
     */
    /**
     * Susun daftar perintah untuk blok "interface gpon-onu" (sn-bind, tcont, gemport,
     * traffic-limit, service-port, script template). Dipakai bersama registerOnu() dan
     * registerOnuBatch() supaya aturannya — termasuk dedupe service-port saat VLAN ACS
     * sama dengan VLAN internet — tidak bercabang antar jalur.
     *
     * @return array ['cmds' => string[], 'tcont' => string]
     */
    private function buildIfCmds(array $params, array &$log, array &$warnings): array
    {
        $vlanInternet = (int)($params['vlan_internet'] ?? 0);
        $vlanAcs      = (int)($params['vlan_acs'] ?? 0);

        $tcont = trim($params['tcont_profile'] ?? '');
        if (!$tcont) {
            $tcontList = array_values(array_filter(array_map('trim', explode("\n", $this->config['tcont_profiles'] ?? ''))));
            $tcont = $tcontList[0] ?? 'default';
            $log[] = "TCONT profile tidak dipilih — menggunakan default: '{$tcont}'";
        }

        $cmds   = ['sn-bind enable sn'];
        $cmds[] = "tcont 1 name tcont profile {$tcont}";
        $cmds[] = "gemport 1 name gemport tcont 1";

        $trafficProfile = trim($params['traffic_profile'] ?? '');
        if ($trafficProfile) {
            $cmds[] = "gemport 1 traffic-limit upstream {$trafficProfile} downstream {$trafficProfile}";
            $log[]  = "Traffic limit: {$trafficProfile}";
        }
        $log[] = "TCONT profile: {$tcont}";

        // ACS dulu (sp1), internet kedua (sp2). Satu ONU tak boleh punya 2 service-port
        // dengan pasangan (user-vlan/vlan) identik → ZTE balas Code 66669.
        $spIdx   = 1;
        $spVlans = [];
        if ($vlanAcs) {
            $cmds[] = "service-port {$spIdx} vport 1 user-vlan {$vlanAcs} vlan {$vlanAcs}";
            $spVlans[$vlanAcs] = true;
            $spIdx++;
            $log[] = "VLAN ACS: {$vlanAcs}";
        }
        if ($vlanInternet && !isset($spVlans[$vlanInternet])) {
            $cmds[] = "service-port {$spIdx} vport 1 user-vlan {$vlanInternet} vlan {$vlanInternet}";
            $log[]  = "VLAN internet: {$vlanInternet}";
        } elseif ($vlanInternet) {
            $log[] = "⚠ VLAN ACS = VLAN internet ({$vlanInternet}) — kemungkinan salah input. "
                   . "ACS biasanya di VLAN manajemen terpisah. service-port & service acs bisa bentrok "
                   . "(OMCI config-fail). Cek VLAN ACS.";
            $warnings[] = "VLAN ACS = VLAN internet ({$vlanInternet}) — ACS mestinya VLAN mgmt terpisah";
        }

        foreach (explode("\n", trim($params['gpon_onu_script'] ?? '')) as $cmd) {
            $cmd = trim($cmd);
            if ($cmd && !str_starts_with($cmd, '#')) $cmds[] = $cmd;
        }

        return ['cmds' => $cmds, 'tcont' => $tcont];
    }

    private function applyIfCmds(
        string $board, string $slot, string $port, string $idx, string $name,
        array $ifCmds, string $tcont, bool $reconfigure, array &$log, array &$warnings
    ): void {
        $this->telnet->execute("interface gpon-onu_{$board}/{$slot}/{$port}:{$idx}", $this->ifPrompt, 5);
        $this->telnet->execute("name {$name}", $this->ifPrompt, 3);

        // Ganti VLAN pada ONU yang SUDAH punya service-port: ZTE menolak service-port
        // dengan nomor sama ("port service para conflicted") sehingga VLAN baru tak pernah
        // masuk. Hapus dulu service-port lama — hanya saat reconfigure, bukan register baru.
        if ($reconfigure) {
            for ($sp = 1; $sp <= 4; $sp++) {
                $this->telnet->execute("no service-port {$sp}", $this->ifPrompt, 5);
            }
            $log[] = 'Service-port lama dibersihkan (reconfigure)';
        }

        // gemport & service-port bergantung pada tcont. Kalau tcont ditolak, sisanya pasti
        // gagal beruntun ("T-CONT does not exist" → "Invalid port") dan cuma bikin log ramai
        // tanpa info baru. Hentikan rantainya, laporkan sebabnya sekali dengan jelas.
        $tcontOk = true;
        foreach ($ifCmds as $cmd) {
            if (!$tcontOk && preg_match('/^(gemport|service-port)\b/i', $cmd)) {
                $log[] = "SKIP: '{$cmd}' (dilewati — tcont gagal, perintah ini pasti ikut gagal)";
                continue;
            }

            $out = $this->telnet->execute($cmd, $this->ifPrompt, 5);
            if ($this->isCliError($out)) {
                $msg = "'{$cmd}' → " . trim(preg_replace('/\s+/', ' ', substr($out, -140)));

                // %Code 62330 "Parameter exceeds range" pada tcont = bandwidth PON port habis:
                // DBA profile bertipe `fixed`/`assured` mem-booking bandwidth per ONU, jadi
                // profil 1G (type 1 fixed 1000000) cuma muat SATU ONU per port.
                if (preg_match('/^tcont\b/i', $cmd)) {
                    $tcontOk = false;
                    if (stripos($out, 'exceeds range') !== false || stripos($out, '62330') !== false) {
                        $msg = "TCONT profile '{$tcont}' ditolak OLT (Parameter exceeds range) — "
                             . "bandwidth PON port sudah penuh. Profil bertipe fixed/assured "
                             . "mem-booking bandwidth per ONU; pakai profil type 4 maximum "
                             . "(best-effort) untuk migrasi banyak ONU. " . $msg;
                    }
                }

                $log[] = "WARN: {$msg}";
                // tcont/gemport/service-port gagal = KRITIS: service acs/int downstream ikut gagal.
                if (preg_match('/^(tcont|gemport|service-port)\b/i', $cmd)) {
                    $warnings[] = $msg;
                }
            }
        }

        $this->telnet->execute('exit', $this->configPrompt, 3);
        $log[] = 'gpon-onu interface configured';
    }

    /**
     * Terapkan blok pon-onu-mng untuk satu ONU. Dipisah dari registerOnu() supaya jalur
     * batch memakai logika yang sama persis (cabang ONU ZTE vs non-ZTE, veip, ACS, PPPoE).
     * Asumsi: sudah berada di config mode.
     */
    private function applyPonMngForRegister(
        string $board, string $slot, string $port, string $idx, string $sn,
        int $vlanInternet, int $vlanAcs, array $params, string $pppoeProfile,
        array &$log, array &$warnings
    ): void {
        // pon-onu-mng: blok terpisah — service mapping VLAN ke veip
        // Syntax diverifikasi dari running-config ZTE C320:
        //   pon-onu-mng gpon-onu_B/S/P:I
        //     service hsi gemport 1 vlan {internet}
        //     service acs gemport 1 vlan {acs}
        //     vlan port veip_1 mode hybrid
        if ($vlanInternet || $vlanAcs) {
            $pppoeUser = trim($params['pppoe_user'] ?? '');
            $pppoePass = trim($params['pppoe_pass'] ?? '');
            $isZteOnu  = $this->isZteVendor($sn);

            // VLAN ACS terisi = ACS memang diminta. Dulu blok ACS di pon-onu-mng masih
            // di-gate flag use_acs OLT, sementara 'service-port user-vlan <acs>' di atas
            // tidak — hasilnya service-port VLAN ACS terbentuk tapi 'service acs' TIDAK,
            // jadi ONU tak pernah nyampe ACS padahal konfigurasinya kelihatan ada.
            // Pemanggil yang menentukan: kalau ACS dimatikan, vlan_acs dikirim 0.

            $this->telnet->execute("pon-onu-mng gpon-onu_{$board}/{$slot}/{$port}:{$idx}", $this->mngPrompt, 5);

            if ($isZteOnu) {
                // ── ONU Merk ZTE: OMCI native ZTE (service acs/ppp, wan-ip pppoe) ──
                if ($vlanAcs) {
                    $out = $this->telnet->execute("service acs gemport 1 vlan {$vlanAcs}", $this->mngPrompt, 5);
                    if ($this->isCliError($out)) {
                        $msg = "service acs gemport 1 vlan {$vlanAcs} → " . trim(preg_replace('/\s+/', ' ', substr($out, -140)));
                        $log[] = "WARN pon-onu-mng: {$msg}";
                        $warnings[] = $msg;
                    }
                }
                if ($vlanInternet) {
                    $okInt = $this->applyServiceInternet($vlanInternet, $log, !empty($pppoeUser));
                    if (!$okInt) {
                        $warnings[] = "service internet gemport 1 vlan {$vlanInternet} gagal";
                    }
                }
                // veip = jalur data ke ONU. Kalau ini gagal, ONU terdaftar tapi tak lewat trafik —
                // dulu output-nya diabaikan diam-diam. Cek + fallback transparent, lalu warning.
                $oV = $this->telnet->execute("vlan port veip_1 mode hybrid", $this->mngPrompt, 5);
                if ($this->isCliError($oV)) {
                    $oV2 = $this->telnet->execute("vlan port veip_1 mode transparent", $this->mngPrompt, 5);
                    if ($this->isCliError($oV2)) {
                        $msg = "vlan port veip_1 (hybrid & transparent gagal) → " . trim(preg_replace('/\s+/', ' ', substr($oV2, -140)));
                        $log[] = "WARN pon-onu-mng: {$msg}";
                        $warnings[] = $msg;
                    } else {
                        $log[] = "vlan port veip_1 mode transparent OK (fallback dari hybrid)";
                    }
                } else {
                    $log[] = "vlan port veip_1 mode hybrid OK";
                }

                if ($vlanAcs) {
                    $this->applyWanIpDhcp(2, $log);
                    $acsUrl = trim($params['acs_url'] ?? $this->config['acs_url'] ?? '');
                    if ($acsUrl) {
                        $this->applyTr069Mgmt($acsUrl, $log);
                    }
                }

                if ($pppoeUser) {
                    $cmdPppoe = $pppoeProfile
                        ? "wan-ip 1 mode pppoe username {$pppoeUser} password {$pppoePass} vlan-profile {$pppoeProfile} host 1"
                        : "wan-ip 1 mode pppoe username {$pppoeUser} password {$pppoePass} host 1";
                    $out = $this->telnet->execute($cmdPppoe, $this->mngPrompt, 5);

                    if ($pppoeProfile && (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false || stripos($out, 'does not exist') !== false)) {
                        $log[] = "WARN: wan-ip dengan vlan-profile '{$pppoeProfile}' gagal, mencoba tanpa vlan-profile...";
                        $out = $this->telnet->execute(
                            "wan-ip 1 mode pppoe username {$pppoeUser} password {$pppoePass} host 1",
                            $this->mngPrompt, 5
                        );
                    }

                    if (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false) {
                        $log[] = "WARN pon-onu-mng: wan-ip pppoe → " . trim(substr($out, -120));
                    } else {
                        $this->telnet->execute("wan-ip 1 ping-response enable traceroute-response enable", $this->mngPrompt, 5);
                        $this->telnet->execute("security-mgmt 212 state enable mode forward protocol web", $this->mngPrompt, 5);
                        $log[] = "pon-onu-mng PPPoE OK: user={$pppoeUser}";
                    }
                }
            } else {
                // ── ONU Merk Non-ZTE (Huawei, Fiberhome, Nokia, China Generic, dll) ──
                // Kunci utama dial: service 1 gemport 1 vlan X + vlan port veip_1 transparent + eth_0/1 transparent
                if ($vlanInternet) {
                    $out1 = $this->telnet->execute("service 1 gemport 1 vlan {$vlanInternet}", $this->mngPrompt, 5);
                    if ($this->isCliError($out1)) {
                        $this->applyServiceInternet($vlanInternet, $log, false);
                    } else {
                        $log[] = "service 1 gemport 1 vlan {$vlanInternet} OK (Non-ZTE / Huawei ONU)";
                    }
                }
                if ($vlanAcs) {
                    $this->telnet->execute("service acs gemport 1 vlan {$vlanAcs}", $this->mngPrompt, 5);
                }

                $o1 = $this->telnet->execute("vlan port veip_1 mode transparent", $this->mngPrompt, 5);
                if ($this->isCliError($o1)) {
                    $this->telnet->execute("vlan port veip_1 mode hybrid", $this->mngPrompt, 5);
                    $log[] = "vlan port veip_1 mode hybrid (fallback)";
                } else {
                    $log[] = "vlan port veip_1 mode transparent OK";
                }

                $o2 = $this->telnet->execute("vlan port eth_0/1 mode transparent", $this->mngPrompt, 5);
                if (!$this->isCliError($o2)) {
                    $log[] = "vlan port eth_0/1 mode transparent OK";
                }
            }

            $this->telnet->execute('exit', $this->configPrompt, 3);
            $log[] = "pon-onu-mng: hsi={$vlanInternet} acs={$vlanAcs} (vendor=" . ($isZteOnu ? 'ZTE' : 'Non-ZTE/Huawei') . ")";
        }
    }

    public function registerOnu(array $params): array
    {
        $board = $params['board'];
        $slot  = $params['slot'];
        $port  = $params['port'];
        $idx   = $params['onu_index'];
        $type  = $params['onu_type'];
        $sn    = $params['sn'];
        $name  = $params['name'];
        $log   = [];
        $warnings = [];   // perintah kritis yang gagal (tcont/gemport/service) → config incomplete

        // Parameter terstruktur
        $vlanInternet = (int)($params['vlan_internet'] ?? 0);
        $vlanAcs      = (int)($params['vlan_acs'] ?? 0);

        // Perintah interface disusun helper yang sama dengan jalur batch.
        $built  = $this->buildIfCmds($params, $log, $warnings);
        $ifCmds = $built['cmds'];
        $tcont  = $built['tcont'];

        // Profile dari UI dropdown (sudah diketahui) → langsung pakai, tidak perlu Telnet.
        // Fallback: ambil dari config OLT (pppoe_vlan_profile), atau default 'PPPOE'.
        $pppoeProfile = trim($params['pppoe_vlan_profile'] ?? '')
            ?: trim($this->config['pppoe_vlan_profile'] ?? 'PPPOE');

        // --- Eksekusi CLI ke OLT ---
        $this->telnet->execute('conf t', $this->configPrompt, 5);
        $log[] = 'Entered configuration mode';

        // Daftarkan ONU di port PON
        $force  = (bool)($params['force'] ?? false);
        $this->telnet->execute("interface gpon-olt_{$board}/{$slot}/{$port}", $this->ifPrompt, 5);
        $result = $this->telnet->execute("onu {$idx} type {$type} sn {$sn}", $this->ifPrompt, 8);
        $log[]  = "OLT response: " . trim(preg_replace('/\s+/', ' ', $result));
        $alreadyExist = stripos($result, 'already exist') !== false || stripos($result, 'exist') !== false;
        if ($alreadyExist && $force) {
            $log[] = "ONU sudah ada di OLT (force re-configure interface)";
        } else {
            $errPatterns = ['error', 'invalid', 'failure', 'failed', 'already exist',
                            'duplicated', 'occupied', 'exist', '% '];
            foreach ($errPatterns as $pat) {
                if (stripos($result, $pat) !== false) {
                    $this->telnet->execute('exit', $this->configPrompt, 3);
                    $this->telnet->execute('exit', $this->rootPrompt, 3);
                    throw new \Exception("Gagal mendaftarkan ONU di OLT: " . trim(preg_replace('/\s+/', ' ', $result)));
                }
            }
        }
        $this->telnet->execute('exit', $this->configPrompt, 3);
        $log[] = "ONU sn={$sn} registered on gpon-olt_{$board}/{$slot}/{$port}:{$idx}";

        // Konfigurasi interface gpon-onu
        $this->applyIfCmds(
            $board, $slot, $port, $idx, $name, $ifCmds, $tcont,
            !empty($params['reconfigure']), $log, $warnings
        );

        // pon-onu-mng (service/veip/ACS/PPPoE) — logika sama dipakai jalur batch.
        $this->applyPonMngForRegister($board, $slot, $port, $idx, $sn, $vlanInternet, $vlanAcs, $params, $pppoeProfile, $log, $warnings);

        // Keluar config mode dan simpan
        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);
        $log[] = 'Configuration saved (write)';

        // Bila ada perintah kritis yang gagal, register TETAP tersimpan (ONU terdaftar) tapi
        // config-nya TIDAK LENGKAP — jangan diam-diam lapor sukses penuh. Surface ke pemanggil.
        $partial = !empty($warnings);
        if ($partial) {
            array_unshift($log, "⚠ KONFIGURASI TIDAK LENGKAP: " . count($warnings)
                . " perintah gagal (ONU bisa tak dapat service/ACS). Cek profil TCONT/traffic. ["
                . implode(' | ', $warnings) . "]");
        }

        return ['success' => true, 'log' => $log, 'warnings' => $warnings, 'partial' => $partial];
    }

    /**
     * Registrasi banyak ONU dalam SATU sesi telnet, dikerjakan BERTAHAP per fase —
     * bukan satu ONU tuntas lalu ONU berikutnya:
     *
     *   Fase 1  conf t → interface gpon-olt_B/S/P → 'onu N type T sn S' untuk semua ONU
     *           di port itu (otorisasi dulu; satu kali masuk interface per port)
     *   Fase 2  interface gpon-onu_B/S/P:N → name/tcont/gemport/service-port per ONU
     *   Fase 3  pon-onu-mng gpon-onu_B/S/P:N → service/veip/ACS/PPPoE per ONU
     *   Fase 4  exit → 'write' SEKALI untuk seluruh batch
     *
     * 'write' menyimpan ke flash dan memakan waktu paling lama; sekali per batch jauh
     * lebih murah daripada sekali per ONU. Fase 1 juga hanya sekali masuk/keluar
     * interface gpon-olt per port.
     *
     * @param array $items  [['sn','name','board','slot','port','onu_index','onu_type'], ...]
     * @param array $common vlan_internet, vlan_acs, tcont_profile, traffic_profile,
     *                      pppoe_vlan_profile, acs_url, gpon_onu_script, force, reconfigure
     * @return array ['results' => [sn => ['success','partial','warnings','log']], 'log' => [...]]
     */
    public function registerOnuBatch(array $items, array $common): array
    {
        $batchLog = [];
        $results  = [];

        foreach ($items as $it) {
            $results[strtoupper($it['sn'])] = [
                'sn' => strtoupper($it['sn']), 'success' => false, 'partial' => false,
                'warnings' => [], 'log' => [],
            ];
        }

        $this->telnet->execute('conf t', $this->configPrompt, 5);
        $batchLog[] = 'Entered configuration mode';

        // ── Fase 1: otorisasi SN, dikelompokkan per port PON ──────────────────
        $byPort = [];
        foreach ($items as $it) {
            $byPort["{$it['board']}/{$it['slot']}/{$it['port']}"][] = $it;
        }

        $force = (bool)($common['force'] ?? false);
        foreach ($byPort as $portKey => $portItems) {
            [$b, $s, $p] = explode('/', $portKey);
            $this->telnet->execute("interface gpon-olt_{$b}/{$s}/{$p}", $this->ifPrompt, 5);

            foreach ($portItems as $it) {
                $sn   = strtoupper($it['sn']);
                $idx  = (string)$it['onu_index'];
                $type = trim($it['onu_type'] ?? '') ?: 'ALL-ONT';

                $out    = $this->telnet->execute("onu {$idx} type {$type} sn {$sn}", $this->ifPrompt, 8);
                $exists = stripos($out, 'exist') !== false;

                if (!$exists && $this->isCliError($out)) {
                    // Gagal otorisasi → ONU ini tidak dilanjutkan ke fase berikutnya,
                    // tapi ONU lain dalam batch tetap jalan.
                    $results[$sn]['log'][] = "Gagal otorisasi di {$portKey}:{$idx} → "
                                           . trim(preg_replace('/\s+/', ' ', $out));
                    continue;
                }
                if ($exists && !$force) {
                    $results[$sn]['log'][] = "ONU sudah ada di {$portKey}:{$idx} dan force tidak aktif.";
                    continue;
                }

                $results[$sn]['authorized'] = true;
                $results[$sn]['log'][] = $exists
                    ? "ONU sudah terdaftar di {$portKey}:{$idx} (force re-configure)"
                    : "Terotorisasi di {$portKey}:{$idx} (type {$type})";
            }

            $this->telnet->execute('exit', $this->configPrompt, 3);
        }
        $batchLog[] = 'Fase 1 selesai: otorisasi SN';

        // ── Fase 2: interface gpon-onu (tcont/gemport/service-port) ───────────
        foreach ($items as $it) {
            $sn = strtoupper($it['sn']);
            if (empty($results[$sn]['authorized'])) continue;

            $params = $common + [
                'board' => $it['board'], 'slot' => $it['slot'], 'port' => $it['port'],
                'onu_index' => (string)$it['onu_index'], 'sn' => $sn,
                'name' => $it['name'], 'onu_type' => $it['onu_type'] ?? 'ALL-ONT',
            ];

            $log = []; $warn = [];
            $built = $this->buildIfCmds($params, $log, $warn);

            $this->applyIfCmds(
                (string)$it['board'], (string)$it['slot'], (string)$it['port'],
                (string)$it['onu_index'], $it['name'], $built['cmds'], $built['tcont'],
                !empty($common['reconfigure']), $log, $warn
            );

            $results[$sn]['log']      = array_merge($results[$sn]['log'], $log);
            $results[$sn]['warnings'] = array_merge($results[$sn]['warnings'], $warn);
            $results[$sn]['params']   = $params;
        }
        $batchLog[] = 'Fase 2 selesai: VLAN/TCONT/service-port';

        // ── Fase 3: pon-onu-mng ───────────────────────────────────────────────
        $vlanInternet = (int)($common['vlan_internet'] ?? 0);
        $vlanAcs      = (int)($common['vlan_acs'] ?? 0);
        $pppoeProfile = trim($common['pppoe_vlan_profile'] ?? '')
            ?: trim($this->config['pppoe_vlan_profile'] ?? 'PPPOE');

        foreach ($items as $it) {
            $sn = strtoupper($it['sn']);
            if (empty($results[$sn]['authorized'])) continue;

            $log = []; $warn = [];
            $this->applyPonMngForRegister(
                (string)$it['board'], (string)$it['slot'], (string)$it['port'],
                (string)$it['onu_index'], $sn, $vlanInternet, $vlanAcs,
                $results[$sn]['params'] ?? $common, $pppoeProfile, $log, $warn
            );

            $results[$sn]['log']      = array_merge($results[$sn]['log'], $log);
            $results[$sn]['warnings'] = array_merge($results[$sn]['warnings'], $warn);
        }
        $batchLog[] = 'Fase 3 selesai: pon-onu-mng';

        // ── Fase 4: keluar config mode, simpan SEKALI untuk seluruh batch ─────
        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 30);
        $batchLog[] = 'Fase 4 selesai: konfigurasi disimpan (write 1x untuk ' . count($items) . ' ONU)';

        foreach ($results as $sn => $r) {
            unset($results[$sn]['params']);
            $results[$sn]['success'] = !empty($r['authorized']);
            $results[$sn]['partial'] = !empty($r['warnings']);
        }

        return ['results' => $results, 'log' => $batchLog];
    }

    /**
     * Deteksi error CLI ZTE secara luas. Bukan cuma "Error"/"Invalid" — ZTE juga balas
     * "Parameter exceeds range", "Failure", "does not exist", "% ...", dll yang tadinya lolos
     * diam-diam dan bikin perintah berikutnya (yang bergantung, mis. gemport→service) ikut gagal.
     */
    private function isCliError(string $out): bool
    {
        return (bool)preg_match(
            '/(error|invalid|failure|failed|exceeds?\s|out of range|not exist|does not exist|cannot|conflict|duplicat|illegal|no such|unknown command|%\s)/i',
            $out
        );
    }

    /**
     * Ambil semua onu vlan-profile dari OLT running-config.
     * Return: [['name' => 'PPPOE', 'vlan' => 155], ...]
     */
    public function getVlanProfiles(): array
    {
        // Coba command khusus dulu (tanpa pipe — lebih reliable di semua firmware)
        $out = $this->telnet->execute('show gpon onu profile vlan', $this->rootPrompt, 8);

        // Fallback: pipe di running-config (hanya OLT tertentu yang support)
        if (empty(trim($out)) || stripos($out, 'invalid') !== false || stripos($out, 'error') !== false) {
            $out = $this->telnet->execute('show running-config | include onu profile vlan', $this->rootPrompt, 8);
        }

        $profiles    = [];
        $seen        = [];
        $currentName = null;

        foreach (explode("\n", $out) as $line) {
            $line = trim($line);

            // Format multi-line (show gpon onu profile vlan):
            // Profile name:  ppp-155
            // CVLAN:         155
            if (preg_match('/^Profile name:\s*(\S+)/i', $line, $m)) {
                $currentName = $m[1];
                continue;
            }
            if ($currentName && preg_match('/^CVLAN:\s*(\d+)/i', $line, $m)) {
                $key = $currentName . ':' . $m[1];
                if (!isset($seen[$key])) {
                    $profiles[] = ['name' => $currentName, 'vlan' => (int)$m[1]];
                    $seen[$key] = true;
                }
                $currentName = null;
                continue;
            }

            // Format single-line (running-config):
            // onu profile vlan PPPOE tag-mode tag cvlan 155 pri 7
            if (preg_match('/onu profile vlan (\S+)\s+tag-mode\s+\S+\s+cvlan\s+(\d+)/i', $line, $m)) {
                $key = $m[1] . ':' . $m[2];
                if (!isset($seen[$key])) {
                    $profiles[] = ['name' => $m[1], 'vlan' => (int)$m[2]];
                    $seen[$key] = true;
                }
            }
        }
        return $profiles;
    }

    private function getVlanProfileForVlan(int $vlan): string
    {
        return trim($this->config['pppoe_vlan_profile'] ?? 'PPPOE');
    }

    /**
     * Push pon-onu-mng (OMCI) ke ONU yang sudah terdaftar — tanpa delete/re-register.
     *
     * Set 2 hal sekaligus:
     *   1. PPPoE WAN (vlan_internet) — jika pppoe_user + pppoe_pass diisi
     *   2. DHCP management + ACS URL (vlan_acs) — jika acs_url + vlan_acs diisi
     *
     * iphost 1 = PPPoE internet, iphost 2 = DHCP management/ACS
     */
    public function applyPonMng(
        string $board, string $slot, string $port, string $onuIndex,
        int $vlanAcs, string $acsUrl,
        int $vlanInternet = 0, string $pppoeUser = '', string $pppoePass = ''
    ): array {
        if (!$vlanInternet && !$vlanAcs) {
            throw new \Exception("VLAN internet dan VLAN ACS keduanya kosong.");
        }

        $pppoeProfile = trim($this->config['pppoe_vlan_profile'] ?? 'PPPOE');
        $log          = [];

        $this->telnet->execute('conf t', $this->configPrompt, 5);
        $this->telnet->execute("pon-onu-mng gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex}", $this->mngPrompt, 5);

        if ($vlanInternet) {
            $this->applyServiceInternet($vlanInternet, $log, !empty($pppoeUser));
        }
        if ($vlanAcs) {
            $out   = $this->telnet->execute("service acs gemport 1 vlan {$vlanAcs}", $this->mngPrompt, 5);
            $log[] = "service acs vlan {$vlanAcs} → " . trim(preg_replace('/\s+/', ' ', $out));
            if (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false) {
                $this->telnet->execute('exit', $this->configPrompt, 3);
                $this->telnet->execute('exit', $this->rootPrompt, 3);
                throw new \Exception("Gagal service acs: " . trim(preg_replace('/\s+/', ' ', $out)));
            }
        }
        $this->telnet->execute("vlan port veip_1 mode hybrid", $this->mngPrompt, 5);

        // ip-host 2 dhcp-enable + tr069-mgmt — ip-host 2 dipakai untuk ACS agar host 1 bebas untuk PPPoE
        if ($vlanAcs) {
            $this->applyWanIpDhcp(2, $log);
            if ($acsUrl) {
                $this->applyTr069Mgmt($acsUrl, $log);
            }
        }

        if ($pppoeUser) {
            $cmdPppoe = $pppoeProfile
                ? "wan-ip 1 mode pppoe username {$pppoeUser} password {$pppoePass} vlan-profile {$pppoeProfile} host 1"
                : "wan-ip 1 mode pppoe username {$pppoeUser} password {$pppoePass} host 1";
            $out   = $this->telnet->execute($cmdPppoe, $this->mngPrompt, 5);

            if ($pppoeProfile && (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false || stripos($out, 'does not exist') !== false)) {
                $log[] = "WARN: wan-ip dengan vlan-profile '{$pppoeProfile}' gagal, mencoba tanpa vlan-profile...";
                $out = $this->telnet->execute(
                    "wan-ip 1 mode pppoe username {$pppoeUser} password {$pppoePass} host 1",
                    $this->mngPrompt, 5
                );
            }

            $log[] = "wan-ip pppoe {$pppoeUser} → " . trim(preg_replace('/\s+/', ' ', $out));
            if (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false) {
                $log[] = "WARN wan-ip pppoe gagal, lanjut tanpa PPPoE config";
            } else {
                $this->telnet->execute("wan-ip 1 ping-response enable traceroute-response enable", $this->mngPrompt, 5);
                $this->telnet->execute("security-mgmt 212 state enable mode forward protocol web", $this->mngPrompt, 5);
                $log[] = "PPPoE configured via pon-onu-mng: {$pppoeUser}";
            }
        }

        $this->telnet->execute('exit', $this->configPrompt, 3);
        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);
        $log[] = 'pon-onu-mng saved';

        return ['success' => true, 'log' => $log];
    }

    /**
     * Kirim service internet ke pon-onu-mng dengan auto-detect keyword.
     * ZTE firmware berbeda-beda: 'hsi', 'int', atau 'ppp'.
     * Coba berurutan sampai berhasil.
     */
    // tr069-mgmt 1 acs {url} [validate basic username {u} password {p}] + state unlock
    private function applyTr069Mgmt(string $acsUrl, array &$log): void
    {
        $acsUser = trim($this->config['acs_user'] ?? '');
        $acsPass = trim($this->config['acs_pass'] ?? '');
        $cmd     = $acsUser && $acsPass
            ? "tr069-mgmt 1 acs {$acsUrl} validate basic username {$acsUser} password {$acsPass}"
            : "tr069-mgmt 1 acs {$acsUrl}";

        $out = $this->telnet->execute($cmd, $this->mngPrompt, 5);
        if (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false) {
            $log[] = "WARN: tr069-mgmt 1 acs → " . trim(substr($out, -120));
        } else {
            $this->telnet->execute("tr069-mgmt 1 state unlock", $this->mngPrompt, 5);
            $log[] = "tr069-mgmt 1 acs" . ($acsUser ? " + validate basic {$acsUser}" : "") . " + state unlock OK";
        }
    }

    // Syntax lengkap diverifikasi langsung di OLT v1+v2:
    // ip-host N dhcp-enable enable ping-response disable traceroute-response disable
    private function applyWanIpDhcp(int $ipHost, array &$log): void
    {
        $cmd = "ip-host {$ipHost} dhcp-enable enable ping-response disable traceroute-response disable";
        $out = $this->telnet->execute($cmd, $this->mngPrompt, 5);
        if (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false) {
            $log[] = "WARN: {$cmd} → " . trim(substr($out, -120));
        } else {
            $log[] = "ip-host {$ipHost} dhcp-enable enable OK";
        }
    }

    private function applyServiceInternet(int $vlan, array &$log, bool $forPppoe = false): bool
    {
        // v1.x → service hsi
        // v2.x + PPPoE → service ppp
        // v2.x + non-PPPoE (Fiberhome) → service int
        $ver = trim($this->config['firmware_version'] ?? '');
        if ($ver) {
            if (version_compare($ver, '2.0', '>=')) {
                $kw = $forPppoe ? 'ppp' : 'int';
            } else {
                $kw = 'hsi';
            }
            $out = $this->telnet->execute("service {$kw} gemport 1 vlan {$vlan}", $this->mngPrompt, 5);
            if (!$this->isCliError($out)) {
                $log[] = "service {$kw} vlan {$vlan} OK (v{$ver})";
                return true;
            }
            $log[] = "WARN: service {$kw} gagal (v{$ver}), coba auto-detect";
        }
        // Auto-detect: PPPoE → ppp → hsi → int, non-PPPoE → hsi → int → ppp
        $order = $forPppoe ? ['ppp', 'hsi', 'int'] : ['hsi', 'int', 'ppp'];
        foreach ($order as $kw) {
            $out = $this->telnet->execute("service {$kw} gemport 1 vlan {$vlan}", $this->mngPrompt, 5);
            if (!$this->isCliError($out)) {
                $log[] = "service {$kw} vlan {$vlan} OK";
                return true;
            }
        }
        $log[] = "WARN: service hsi/int/ppp semua gagal untuk vlan {$vlan}";
        return false;
    }

    /**
     * Index terpakai per port, dari 'show gpon onu state' — satu perintah untuk semua port.
     * Baris: "1/1/1:1     enable       enable      working      1(GPON)"
     * Ini sumber kebenaran saat memilih index kosong; cache JSON bisa basi.
     */
    public function getUsedOnuIndexes(): array
    {
        $out  = $this->telnet->execute('show gpon onu state', $this->rootPrompt, 20);
        $used = [];
        foreach (explode("\n", $out) as $line) {
            if (preg_match('/^\s*(\d+)\/(\d+)\/(\d+):(\d+)\s+/', $line, $m)) {
                $used["{$m[1]}/{$m[2]}/{$m[3]}"][] = (int)$m[4];
            }
        }
        foreach ($used as $k => $v) {
            $v = array_values(array_unique($v));
            sort($v);
            $used[$k] = $v;
        }
        return $used;
    }

    /**
     * Ganti nama ONU tanpa re-register:
     *   conf t → interface gpon-onu_B/S/P:I → name <nama> → write
     * Nama dibersihkan seperti saat register (CLI ZTE memecah token di spasi).
     */
    public function setOnuName(string $board, string $slot, string $port, string $onuIndex, string $name): array
    {
        $clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', trim($name));
        $clean = trim(preg_replace('/_+/', '_', $clean), '_');
        if ($clean === '') {
            return ['success' => false, 'name' => '', 'log' => ['Nama kosong setelah dibersihkan.']];
        }

        $log = [];
        $this->telnet->execute('conf t', $this->configPrompt, 5);
        $this->telnet->execute("interface gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex}", $this->ifPrompt, 5);

        $out = $this->telnet->execute("name {$clean}", $this->ifPrompt, 5);
        $ok  = !$this->isCliError($out);
        $log[] = ($ok ? '' : 'WARN ') . "gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex} → 'name {$clean}' "
               . ($ok ? 'OK' : trim(preg_replace('/\s+/', ' ', substr($out, -140))));

        $this->telnet->execute('exit', $this->configPrompt, 3);
        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);
        $log[] = 'Configuration saved (write)';

        return ['success' => $ok, 'name' => $clean, 'log' => $log];
    }

    public function getSnAtIndex(string $board, string $slot, string $port, string $onuIndex): ?string
    {
        $output = $this->telnet->execute(
            "show gpon onu baseinfo gpon-olt_{$board}/{$slot}/{$port}",
            $this->rootPrompt, 10
        );
        // Format: gpon-onu_1/2/7:1    ZTE-F609    sn    SN:ZTEGD346D870    ready
        foreach (explode("\n", $output) as $line) {
            if (preg_match("/gpon-onu_{$board}\/{$slot}\/{$port}:{$onuIndex}\s/i", $line)) {
                if (preg_match('/\bSN:([A-Za-z0-9]{8,20})\b/i', $line, $m)) {
                    return strtoupper($m[1]);
                }
            }
        }
        return null;
    }

    public function deleteOnu(string $board, string $slot, string $port, string $onuIndex): bool
    {
        $this->telnet->execute('conf t', $this->configPrompt);
        $this->telnet->execute("interface gpon-olt_{$board}/{$slot}/{$port}", $this->ifPrompt);
        $this->telnet->execute("no onu {$onuIndex}", $this->ifPrompt, 10);
        $this->telnet->execute('exit', $this->configPrompt);
        $this->telnet->execute('exit', $this->rootPrompt);
        $this->telnet->execute('write', $this->rootPrompt, 20);
        return true;
    }

    /**
     * Sinyal ONU dari OLT.
     * Format aktual OLT C320:
     *   up      Rx :-26.072(dbm)      Tx:3.170(dbm)        29.242(dB)
     *   down    Tx :10.571(dbm)       Rx:-21.670(dbm)      32.241(dB)
     *
     * Yang relevan untuk monitoring pelanggan:
     *   - olt_rx   = up Rx   = sinyal upstream diterima OLT dari ONU
     *   - onu_rx   = down Rx = sinyal downstream diterima ONU dari OLT (kualitas sinyal pelanggan)
     *   - onu_tx   = up Tx   = daya transmit ONU
     */
    public function getOnuSignal(string $board, string $slot, string $port, string $onuIndex): array
    {
        $output = $this->telnet->execute(
            "show pon power attenuation gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex}",
            $this->rootPrompt, 10
        );

        $result = ['olt_rx' => null, 'onu_tx' => null, 'olt_tx' => null, 'onu_rx' => null];

        // up line: Rx :-26.072(dbm)  Tx:3.170(dbm)
        if (preg_match('/up\s+Rx\s*:([\-\d\.]+)\(dbm\)/i', $output, $m)) {
            $result['olt_rx'] = $m[1];
        }
        if (preg_match('/up\s+Rx[^\n]+Tx\s*:([\-\d\.]+)\(dbm\)/i', $output, $m)) {
            $result['onu_tx'] = $m[1];
        }
        // down line: Tx :10.571(dbm)  Rx:-21.670(dbm)
        if (preg_match('/down\s+Tx\s*:([\-\d\.]+)\(dbm\)/i', $output, $m)) {
            $result['olt_tx'] = $m[1];
        }
        if (preg_match('/down\s+Tx[^\n]+Rx\s*:([\-\d\.]+)\(dbm\)/i', $output, $m)) {
            $result['onu_rx'] = $m[1];
        }

        return $result;
    }

    /**
     * Detail lengkap satu ONU dari OLT.
     * Command: show gpon onu detail-info gpon-onu_B/S/P:I
     */
    public function getOnuDetail(string $board, string $slot, string $port, string $onuIndex): array
    {
        $output = $this->telnet->execute(
            "show gpon onu detail-info gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex}",
            $this->rootPrompt, 10
        );

        $detail = ['name' => null, 'sn' => null, 'distance' => null, 'online_duration' => null, 'phase_state' => null];

        if (preg_match('/Name:\s*(.+)/i', $output, $m))          $detail['name']            = trim($m[1]);
        if (preg_match('/Serial number:\s*([A-Za-z0-9]+)/i', $output, $m)) $detail['sn']   = strtoupper(trim($m[1]));
        if (preg_match('/ONU Distance:\s*(.+)/i', $output, $m))  $detail['distance']        = trim($m[1]);
        if (preg_match('/Online Duration:\s*(.+)/i', $output, $m))$detail['online_duration'] = trim($m[1]);
        if (preg_match('/Phase state:\s*(\S+)/i', $output, $m))  $detail['phase_state']     = trim($m[1]);

        return $detail;
    }

    /**
     * Ambil nama TCONT profile yang terkonfigurasi di OLT.
     * Command: show gpon profile tcont
     * Output ZTE C320:
     *   profile-name  type  assured-bw(kbps)  max-bw(kbps)
     *   -------------------------------------------------------
     *   250M          4     0                 256000
     *   100M          4     0                 102400
     */
    public function getTcontProfiles(): array
    {
        $output = $this->telnet->execute('show gpon profile tcont', $this->rootPrompt, 10);
        if (empty(trim($output)) || $this->isCliError($output)) {
            $output = $this->telnet->execute('show running-config | include profile tcont', $this->rootPrompt, 10);
        }
        return $this->parseProfileNames($output);
    }

    public function getTrafficProfiles(): array
    {
        $output = $this->telnet->execute('show gpon profile traffic', $this->rootPrompt, 10);
        if (empty(trim($output)) || $this->isCliError($output)) {
            $output = $this->telnet->execute('show running-config | include profile traffic', $this->rootPrompt, 10);
        }
        return $this->parseProfileNames($output);
    }

    /**
     * Parse daftar nama profile dari output ZTE "show gpon profile tcont/traffic" atau "show running-config".
     * Mampu membaca berbagai format output firmware ZTE (v1.2, v2.1, C600, dll).
     */
    private function parseProfileNames(string $output): array
    {
        $lines = array_map('trim', explode("\n", $output));

        // Format 1 — blok bernama (V4.8.x, terverifikasi di C320 Cibungur):
        //   Profile name :200M
        //    Type           FBW(kbps)   ABW(kbps)   MBW(kbps)   PRIORITY   WEIGHT
        //    4              0           0           200000      N/A         N/A
        // Baris angka di bawahnya adalah NILAI, bukan nama. Kalau blok bernama ketemu,
        // berhenti di sini — jangan jalankan fallback tabular yang akan menyerap "4"
        // (type) dan "9953280" (SIR traffic) jadi entri profil palsu di dropdown.
        $named = [];
        foreach ($lines as $line) {
            if (preg_match('/^Profile\s*name\s*:\s*(\S+)/i', $line, $m)) {
                $named[] = trim($m[1]);
            }
        }
        if ($named) {
            return array_values(array_unique(array_filter($named)));
        }

        // Fallback untuk firmware lain yang formatnya beda.
        $profiles  = [];
        $skipWords = ['profile-name', 'name', 'type', 'assured-bw(kbps)', 'max-bw(kbps)', 'assured-bw', 'max-bw', 'building', 'current'];

        foreach ($lines as $line) {
            if (empty($line) || str_starts_with($line, '-') || str_starts_with($line, '#')) continue;

            // Format 2: gpon profile tcont 250M ... / gpon profile traffic 50M ...
            if (preg_match('/(?:gpon\s+)?profile\s+(?:tcont|traffic)\s+(\S+)/i', $line, $m)) {
                $profiles[] = trim($m[1]);
                continue;
            }

            // Format 3: Tabular output (misal: 250M  4  0  256000)
            if (preg_match('/^([A-Za-z0-9_\-]+)\s+\d+/i', $line, $m)) {
                $pName = trim($m[1]);
                // Nama yang isinya angka semua = kolom nilai yang kesasar, bukan profil.
                if (ctype_digit($pName)) continue;
                if (!in_array(strtolower($pName), $skipWords)) {
                    $profiles[] = $pName;
                }
            }
        }
        return array_values(array_unique(array_filter($profiles)));
    }

    /**
     * Ambil konfigurasi aktif ONU dari running-config OLT.
     * Command: show running-config interface gpon-onu_B/S/P:I
     *
     * Parse: tcont profile, traffic-limit profile, semua service-port VLAN.
     * Konvensi: sp1 = vlan_internet, sp2 = vlan_acs (sesuai urutan registerOnu)
     */
    public function getOnuConfig(string $board, string $slot, string $port, string $onuIndex): array
    {
        $output = $this->telnet->execute(
            "show running-config interface gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex}",
            $this->rootPrompt, 10
        );

        $result = [
            'name'            => null,
            'tcont_profile'   => '',
            'traffic_profile' => '',
            'vlan_internet'   => 0,
            'vlan_acs'        => 0,
            'service_ports'   => [],
        ];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            // name ADAM CBR
            if (preg_match('/^name\s+(.+)/i', $line, $m)) {
                $result['name'] = trim($m[1]);
            }
            // tcont 1 name tcont profile 250M
            if (preg_match('/^tcont\s+\d+\s+name\s+\S+\s+profile\s+(\S+)/i', $line, $m)) {
                $result['tcont_profile'] = $m[1];
            }
            // gemport 1 traffic-limit upstream 200M downstream 200M
            if (preg_match('/^gemport\s+\d+\s+traffic-limit\s+upstream\s+(\S+)/i', $line, $m)) {
                $result['traffic_profile'] = $m[1];
            }
            // service-port 1 vport 1 user-vlan 155 vlan 155
            if (preg_match('/^service-port\s+(\d+)\s+vport\s+\d+\s+user-vlan\s+(\d+)/i', $line, $m)) {
                $result['service_ports'][(int)$m[1]] = (int)$m[2];
            }
        }

        // Konvensi registerOnu: sp1 = ACS (VLAN mgmt), sp2 = internet (VLAN PPPoE)
        ksort($result['service_ports']);
        $spList = array_values($result['service_ports']);
        $result['vlan_acs']      = $spList[0] ?? 0;
        $result['vlan_internet'] = $spList[1] ?? 0;

        return $result;
    }

    /**
     * Ambil raw config pon-onu-mng dan parse semua field relevan.
     * Return: ['raw' => string, 'pppoe_user' => ?string, 'pppoe_pass' => ?string,
     *          'services' => [...], 'wan_ip' => [...]]
     */
    /** Cache running-config global per sesi telnet — outputnya besar, jangan diambil berulang. */
    private ?string $runningConfigCache = null;

    /**
     * Ambil blok "pon-onu-mng gpon-onu_B/S/P:I" dari running-config global.
     * Dipakai sebagai fallback untuk firmware yang tidak punya perintah per-ONU.
     */
    private function extractPonMngBlock(string $board, string $slot, string $port, string $onuIndex): string
    {
        if ($this->runningConfigCache === null) {
            $this->runningConfigCache = $this->telnet->execute('show running-config', $this->rootPrompt, 90);
        }

        $header = "pon-onu-mng gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex}";
        $lines  = explode("\n", $this->runningConfigCache);
        $block  = [];
        $inside = false;

        foreach ($lines as $line) {
            $trim = trim($line, " \r\n");
            if (!$inside) {
                if ($trim === $header) { $inside = true; $block[] = $trim; }
                continue;
            }
            if ($trim === '!' || preg_match('/^pon-onu-mng\s/', $trim)) break;
            $block[] = $trim;
        }

        return $block ? implode("\n", $block) : '';
    }

    public function getPonMngConfig(string $board, string $slot, string $port, string $onuIndex): array
    {
        $raw = $this->telnet->execute(
            "show running-config pon-onu-mng gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex}",
            $this->rootPrompt, 10
        );

        // C320 V2.1.0 menolak perintah di atas (%Error 20201) dan tidak punya modul 'pon'
        // di 'show running-config module', jadi blok pon-onu-mng hanya bisa diambil dari
        // running-config global. Diambil sekali per sesi lalu dipakai ulang.
        if (preg_match('/%Error\s+\d+|Invalid input detected|Invalid command/i', $raw)) {
            $raw = $this->extractPonMngBlock($board, $slot, $port, $onuIndex);
        }

        $result = ['raw' => $raw, 'pppoe_user' => null, 'pppoe_pass' => null, 'services' => [], 'wan_ip' => []];

        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            // wan-ip 1 mode pppoe username USER password PASS ...
            if (preg_match('/wan-ip\s+(\d+)\s+mode\s+pppoe\s+username\s+(\S+)\s+password\s+(\S+)/i', $line, $m)) {
                $result['wan_ip'][$m[1]] = ['mode' => 'pppoe', 'username' => $m[2], 'password' => $m[3]];
                if ($m[1] === '1') {
                    $result['pppoe_user'] = $m[2];
                    $result['pppoe_pass'] = $m[3];
                }
            }
            // wan-ip 2 mode dhcp ...
            if (preg_match('/wan-ip\s+(\d+)\s+mode\s+dhcp/i', $line, $m)) {
                $result['wan_ip'][$m[1]] = ['mode' => 'dhcp'];
            }
            // service hsi/ppp/acs gemport 1 vlan N
            if (preg_match('/service\s+(\S+)\s+gemport\s+\d+\s+vlan\s+(\d+)/i', $line, $m)) {
                $result['services'][] = ['name' => $m[1], 'vlan' => (int)$m[2]];
            }
        }

        return $result;
    }

    public function getPonMngPppoeUser(string $board, string $slot, string $port, string $onuIndex): ?string
    {
        return $this->getPonMngConfig($board, $slot, $port, $onuIndex)['pppoe_user'];
    }

    public function addTcontProfile(string $name, int $maxBwKbps = 102400): array
    {
        $this->telnet->execute('conf t', $this->configPrompt, 5);

        $cmds = [
            "gpon profile tcont {$name} type 4 max {$maxBwKbps}",
            "profile tcont {$name} type 4 max {$maxBwKbps}",
            "tcont profile {$name} type 4 max {$maxBwKbps}",
        ];

        $lastOut = '';
        $success = false;

        foreach ($cmds as $cmd) {
            $out = $this->telnet->execute($cmd, $this->configPrompt, 5);
            if (!$this->isCliError($out)) {
                $success = true;
                break;
            }
            $lastOut = $out;
        }

        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);

        if (!$success) {
            return ['success' => false, 'message' => "Gagal buat TCONT profile '{$name}': " . trim(preg_replace('/\s+/', ' ', $lastOut))];
        }
        return ['success' => true, 'message' => "TCONT profile '{$name}' berhasil dibuat di OLT."];
    }

    public function deleteTcontProfile(string $name): array
    {
        $this->telnet->execute('conf t', $this->configPrompt, 5);

        $cmds = [
            "no gpon profile tcont {$name}",
            "no profile tcont {$name}",
            "no tcont profile {$name}",
        ];

        $lastOut = '';
        $success = false;

        foreach ($cmds as $cmd) {
            $out = $this->telnet->execute($cmd, $this->configPrompt, 5);
            if (!$this->isCliError($out)) {
                $success = true;
                break;
            }
            $lastOut = $out;
        }

        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);

        if (!$success) {
            return ['success' => false, 'message' => "Gagal hapus TCONT profile '{$name}': " . trim(preg_replace('/\s+/', ' ', $lastOut))];
        }
        return ['success' => true, 'message' => "TCONT profile '{$name}' berhasil dihapus dari OLT."];
    }

    public function addTrafficProfile(string $name, int $sirKbps = 102400, int $pirKbps = 102400): array
    {
        $this->telnet->execute('conf t', $this->configPrompt, 5);

        $cmds = [
            "gpon profile traffic {$name} sir {$sirKbps} pir {$pirKbps}",
            "profile traffic {$name} sir {$sirKbps} pir {$pirKbps}",
            "gpon profile traffic {$name} pir {$pirKbps}",
        ];

        $lastOut = '';
        $success = false;

        foreach ($cmds as $cmd) {
            $out = $this->telnet->execute($cmd, $this->configPrompt, 5);
            if (!$this->isCliError($out)) {
                $success = true;
                break;
            }
            $lastOut = $out;
        }

        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);

        if (!$success) {
            return ['success' => false, 'message' => "Gagal buat Traffic profile '{$name}': " . trim(preg_replace('/\s+/', ' ', $lastOut))];
        }
        return ['success' => true, 'message' => "Traffic profile '{$name}' berhasil dibuat di OLT."];
    }

    public function deleteTrafficProfile(string $name): array
    {
        $this->telnet->execute('conf t', $this->configPrompt, 5);

        $cmds = [
            "no gpon profile traffic {$name}",
            "no profile traffic {$name}",
        ];

        $lastOut = '';
        $success = false;

        foreach ($cmds as $cmd) {
            $out = $this->telnet->execute($cmd, $this->configPrompt, 5);
            if (!$this->isCliError($out)) {
                $success = true;
                break;
            }
            $lastOut = $out;
        }

        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);

        if (!$success) {
            return ['success' => false, 'message' => "Gagal hapus Traffic profile '{$name}': " . trim(preg_replace('/\s+/', ' ', $lastOut))];
        }
        return ['success' => true, 'message' => "Traffic profile '{$name}' berhasil dihapus dari OLT."];
    }

    public function addVlanProfile(string $name, int $vlanId): array
    {
        $this->telnet->execute('conf t', $this->configPrompt, 5);

        // Daftar variasi syntax CLI ZTE per versi firmware (v1.2, v2.1, C600, dll)
        $singleCmds = [
            "gpon onu profile vlan {$name} tag-mode tag cvlan {$vlanId} pri 7",
            "gpon onu profile vlan {$name} tag-mode tag cvlan {$vlanId}",
            "onu profile vlan {$name} tag-mode tag cvlan {$vlanId} pri 7",
            "onu profile vlan {$name} tag-mode tag cvlan {$vlanId}",
            "onu-profile vlan {$name} tag-mode tag cvlan {$vlanId}",
        ];

        $lastOut = '';
        $success = false;

        foreach ($singleCmds as $cmd) {
            $out = $this->telnet->execute($cmd, $this->configPrompt, 5);
            if (!$this->isCliError($out)) {
                $success = true;
                break;
            }
            $lastOut = $out;
        }

        // Jika single-line tidak didukung, coba mode block "onu-profile vlan <name>"
        if (!$success) {
            $out1 = $this->telnet->execute("onu-profile vlan {$name}", $this->configPrompt, 5);
            if (!$this->isCliError($out1)) {
                $out2 = $this->telnet->execute("tag-mode tag cvlan {$vlanId}", $this->configPrompt, 5);
                $this->telnet->execute('exit', $this->configPrompt, 3);
                if (!$this->isCliError($out2)) {
                    $success = true;
                } else {
                    $lastOut = $out2;
                }
            } else {
                $lastOut = $out1;
            }
        }

        // Jika masih belum berhasil, coba via mode "gpon"
        if (!$success) {
            $this->telnet->execute('gpon', $this->configPrompt, 5);
            $out1 = $this->telnet->execute("onu profile vlan {$name} tag-mode tag cvlan {$vlanId}", $this->configPrompt, 5);
            $this->telnet->execute('exit', $this->configPrompt, 3);
            if (!$this->isCliError($out1)) {
                $success = true;
            } else {
                $lastOut = $out1;
            }
        }

        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);

        if (!$success) {
            return ['success' => false, 'message' => "Gagal buat VLAN profile '{$name}': " . trim(preg_replace('/\s+/', ' ', $lastOut))];
        }
        return ['success' => true, 'message' => "ONU VLAN profile '{$name}' (VLAN {$vlanId}) berhasil dibuat di OLT."];
    }

    public function deleteVlanProfile(string $name): array
    {
        $this->telnet->execute('conf t', $this->configPrompt, 5);

        $cmds = [
            "no gpon onu profile vlan {$name}",
            "no onu profile vlan {$name}",
            "no onu-profile vlan {$name}",
        ];

        $lastOut = '';
        $success = false;

        foreach ($cmds as $cmd) {
            $out = $this->telnet->execute($cmd, $this->configPrompt, 5);
            if (!$this->isCliError($out)) {
                $success = true;
                break;
            }
            $lastOut = $out;
        }

        // Fallback via mode gpon
        if (!$success) {
            $this->telnet->execute('gpon', $this->configPrompt, 5);
            $out1 = $this->telnet->execute("no onu profile vlan {$name}", $this->configPrompt, 5);
            $this->telnet->execute('exit', $this->configPrompt, 3);
            if (!$this->isCliError($out1)) {
                $success = true;
            } else {
                $lastOut = $out1;
            }
        }

        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);

        if (!$success) {
            return ['success' => false, 'message' => "Gagal hapus VLAN profile '{$name}': " . trim(preg_replace('/\s+/', ' ', $lastOut))];
        }
        return ['success' => true, 'message' => "ONU VLAN profile '{$name}' berhasil dihapus dari OLT."];
    }

    public function isZteVendor(string $sn): bool
    {
        return strncasecmp($sn, 'ZTE', 3) === 0;
    }

    /**
     * Ambil status Alarm Aktif, Status Kipas (FAN), Power, dan Card OLT
     */
    /**
     * Jalankan kandidat perintah berurutan, kembalikan output pertama yang DITERIMA OLT.
     * Deteksi penolakan dipatok pada kode error ZTE (%Error 202xx) supaya output sah yang
     * kebetulan memuat kata "fail"/"invalid" — misal 'show alarm counter' — tidak ikut dibuang.
     */
    private function firstSupported(array $candidates): string
    {
        $last = '';
        foreach ($candidates as $cmd) {
            $out  = $this->telnet->execute($cmd, $this->rootPrompt, 10);
            $last = $out;
            if (!preg_match('/%Error\s+\d+|Invalid input detected|Invalid command|Ambiguous command|Incomplete command/i', $out)) {
                return $out;
            }
        }
        return "(tidak didukung firmware OLT ini)\n" . trim($last);
    }

    public function getAlarms(): array
    {
        $alarms = [
            'current_alarms' => '',
            'card_status'    => '',
            'fan_status'     => '',
            'power_status'   => '',
            'env_status'     => '',
        ];

        // Perintah berbeda antar firmware. Di C320 V2.1.0 (verified): 'show alarm current',
        // 'show power', dan 'show environment' DITOLAK — yang jalan 'show alarm pool',
        // 'show alarm crtv-active', 'show alarm counter', dan 'show fan' (sudah memuat suhu).
        // Coba berurutan, pakai yang pertama diterima, jangan tampilkan pesan %Error ke user.
        try {
            $alarms['current_alarms'] = $this->firstSupported([
                'show alarm pool', 'show alarm current', 'show alarm crtv-active',
            ]);
            $alarms['card_status']  = $this->telnet->execute('show card', $this->rootPrompt, 10);
            $alarms['fan_status']   = $this->firstSupported(['show fan']);
            $alarms['power_status'] = $this->firstSupported(['show power', 'show power-supply', 'show alarm counter']);
            $alarms['env_status']   = $this->firstSupported(['show environment', 'show temperature', 'show fan']);
        } catch (\Throwable $e) {
            log_message('error', "getAlarms error: " . $e->getMessage());
        }

        return $alarms;
    }

    public function getBrand(): string { return 'ZTE'; }
    public function getModel(): string { return $this->config['model'] ?? 'C320'; }

    // ══ Setup / maintenance OLT ═══════════════════════════════════════════
    // Semua format di bawah diverifikasi langsung di C320 V2.1.0 (OLT Cibungur).

    /**
     * show system-group  → System Description / System name / Started before
     * show ip interface brief → tabel interface manajemen
     * show card → daftar kartu + jumlah port
     */
    public function getSystemInfo(): array
    {
        $info = [
            'name' => '', 'model' => '', 'version' => '', 'uptime' => '',
            'mgmt' => [], 'cards' => [],
        ];

        $sys = $this->telnet->execute('show system-group', $this->rootPrompt, 10);
        if (preg_match('/System name:\s*(\S+)/i', $sys, $m))       $info['name']    = $m[1];
        if (preg_match('/Started before:\s*(.+)/i', $sys, $m))     $info['uptime']  = trim($m[1]);
        // "System Description: C320 Version V2.1.0 Software, Copyright (c) ..."
        if (preg_match('/System Description:\s*(\S+)\s+Version\s+(\S+)/i', $sys, $m)) {
            $info['model']   = $m[1];
            $info['version'] = $m[2];
        }

        // Interface     IP-Address      Mask            Admin Phy  Prot Description
        // vlan10        192.168.10.2    255.255.255.0   up    up   up   none
        $ipOut = $this->telnet->execute('show ip interface brief', $this->rootPrompt, 10);
        foreach (explode("\n", $ipOut) as $line) {
            $line = trim($line);
            if (preg_match('/^(\S+)\s+(\d{1,3}(?:\.\d{1,3}){3})\s+(\d{1,3}(?:\.\d{1,3}){3})\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $m)) {
                $info['mgmt'][] = [
                    'interface' => $m[1], 'ip' => $m[2], 'mask' => $m[3],
                    'admin' => $m[4], 'phy' => $m[5], 'proto' => $m[6],
                ];
            }
        }

        $info['cards'] = $this->getCards();
        return $info;
    }

    /**
     * show card
     * Rack Shelf Slot CfgType RealType Port  HardVer SoftVer  Status
     * 1    1     1    GTGH    GTGHG    16    V1.0.0  V2.1.0   INSERVICE
     */
    private function getCards(): array
    {
        $out   = $this->telnet->execute('show card', $this->rootPrompt, 10);
        $cards = [];
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            if (!preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(.*)$/', $line, $m)) continue;
            $rest = preg_split('/\s+/', trim($m[5]));
            // RealType bisa kosong (kartu OFFLINE) → kolom bergeser; ambil angka port bila ada.
            $realType = '';
            $ports    = 0;
            if (isset($rest[0]) && !ctype_digit($rest[0])) {
                $realType = array_shift($rest);
            }
            if (isset($rest[0]) && ctype_digit($rest[0])) {
                $ports = (int)array_shift($rest);
            }
            $cards[] = [
                'rack'   => $m[1], 'shelf' => $m[2], 'slot' => $m[3],
                'type'   => $m[4], 'real_type' => $realType, 'ports' => $ports,
                'status' => $rest ? end($rest) : '',
            ];
        }
        return $cards;
    }

    /**
     * Daftar port PON + status. Satu perintah per port (running-config memberi
     * shutdown/description/name/daftar ONU sekaligus), plus satu perintah global
     * untuk hitung ONU yang benar-benar working.
     */
    public function getPonPorts(): array
    {
        // ONU per port dari satu perintah — jangan query per port lagi.
        $working = [];
        $stateOut = $this->telnet->execute('show gpon onu state', $this->rootPrompt, 20);
        foreach (explode("\n", $stateOut) as $line) {
            if (preg_match('/^\s*(\d+)\/(\d+)\/(\d+):(\d+)\s+\S+\s+\S+\s+(\S+)/', $line, $m)) {
                $key = "{$m[1]}/{$m[2]}/{$m[3]}";
                if (!isset($working[$key])) $working[$key] = 0;
                if (strcasecmp($m[5], 'working') === 0) $working[$key]++;
            }
        }

        $ports = [];
        foreach ($this->getCards() as $card) {
            // Hanya kartu GPON (GTGH/GTGO/dll) yang punya port PON.
            if ($card['ports'] < 1 || stripos($card['type'], 'GT') !== 0) continue;

            for ($p = 1; $p <= $card['ports']; $p++) {
                $b = $card['rack'];
                $s = $card['slot'];
                $cfg = $this->telnet->execute(
                    "show running-config interface gpon-olt_{$b}/{$s}/{$p}",
                    $this->rootPrompt, 10
                );

                $enabled = stripos($cfg, 'no shutdown') !== false
                        || stripos($cfg, 'shutdown')    === false;
                $desc = preg_match('/^\s*description\s+(.+)$/im', $cfg, $m) ? trim($m[1]) : '';
                $name = preg_match('/^\s*name\s+(.+)$/im', $cfg, $m)        ? trim($m[1]) : '';
                $cfgOnu = preg_match_all('/^\s*onu\s+\d+\s+type\s+/im', $cfg);

                $key = "{$b}/{$s}/{$p}";
                $ports[] = [
                    'board' => (string)$b, 'slot' => (string)$s, 'port' => (string)$p,
                    'enabled'        => $enabled,
                    'description'    => $desc,
                    'name'           => $name,
                    'onu_configured' => $cfgOnu,
                    'onu_working'    => $working[$key] ?? 0,
                ];
            }
        }
        return $ports;
    }

    public function setPonPortState(string $board, string $slot, string $port, bool $enable): array
    {
        $log = [];
        $this->telnet->execute('conf t', $this->configPrompt, 5);
        $this->telnet->execute("interface gpon-olt_{$board}/{$slot}/{$port}", $this->ifPrompt, 5);

        $cmd = $enable ? 'no shutdown' : 'shutdown';
        $out = $this->telnet->execute($cmd, $this->ifPrompt, 10);
        $ok  = !$this->isCliError($out);
        $log[] = ($ok ? '' : 'WARN ') . "gpon-olt_{$board}/{$slot}/{$port}: {$cmd} → " . trim(preg_replace('/\s+/', ' ', $out));

        $this->telnet->execute('exit', $this->configPrompt, 3);
        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);
        $log[] = 'Configuration saved (write)';

        return ['success' => $ok, 'log' => $log];
    }

    /**
     * name / description port PON. Keduanya opsional:
     *   null   → biarkan apa adanya
     *   ''     → hapus (no name / no description)
     * Verified di running-config C320: "description CIBUNGUR", "name ODC-TONI CBR"
     * (name boleh mengandung spasi, tanpa kutip).
     */
    public function setPonPortInfo(string $board, string $slot, string $port, ?string $name, ?string $description): array
    {
        $log = [];
        $ok  = true;

        $this->telnet->execute('conf t', $this->configPrompt, 5);
        $this->telnet->execute("interface gpon-olt_{$board}/{$slot}/{$port}", $this->ifPrompt, 5);

        foreach ([['description', $description], ['name', $name]] as [$field, $value]) {
            if ($value === null) continue;

            // Buang karakter yang bisa memecah baris perintah CLI.
            $value = trim(preg_replace('/[\r\n;|]+/', ' ', $value));
            $cmd   = $value === '' ? "no {$field}" : "{$field} {$value}";

            $out = $this->telnet->execute($cmd, $this->ifPrompt, 5);
            if ($this->isCliError($out)) {
                $ok = false;
                $log[] = "WARN '{$cmd}' → " . trim(preg_replace('/\s+/', ' ', substr($out, -140)));
            } else {
                $log[] = "'{$cmd}' OK";
            }
        }

        $this->telnet->execute('exit', $this->configPrompt, 3);
        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);
        $log[] = 'Configuration saved (write)';

        return ['success' => $ok, 'log' => $log];
    }

    /**
     * show vlan summary
     *   All created vlan num: 17
     *   Details are following:
     *       1,10,81,100,123,134,145,150,152-155,158,160,162,499-500
     */
    public function getVlanDatabase(): array
    {
        $out   = $this->telnet->execute('show vlan summary', $this->rootPrompt, 10);
        $vlans = [];

        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            // Hanya baris yang isinya murni daftar angka/rentang.
            if (!preg_match('/^[\d,\-\s]+$/', $line) || $line === '') continue;

            foreach (preg_split('/\s*,\s*/', $line) as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') continue;
                if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $chunk, $m)) {
                    for ($v = (int)$m[1]; $v <= (int)$m[2]; $v++) $vlans[] = $v;
                } elseif (ctype_digit($chunk)) {
                    $vlans[] = (int)$chunk;
                }
            }
        }

        $vlans = array_values(array_unique(array_filter($vlans, fn($v) => $v >= 1 && $v <= 4094)));
        sort($vlans);
        return $vlans;
    }

    public function addVlan(int $vlanId): array
    {
        return $this->vlanDatabaseCmd("vlan {$vlanId}", "VLAN {$vlanId} ditambahkan");
    }

    public function deleteVlan(int $vlanId): array
    {
        return $this->vlanDatabaseCmd("no vlan {$vlanId}", "VLAN {$vlanId} dihapus");
    }

    private function vlanDatabaseCmd(string $cmd, string $okMsg): array
    {
        $log = [];
        $this->telnet->execute('conf t', $this->configPrompt, 5);
        // prompt vlan database: (config-vlan)#
        $this->telnet->execute('vlan database', ['config-vlan)#', 'config)#'], 5);

        $out = $this->telnet->execute($cmd, ['config-vlan)#', 'config)#'], 10);
        $ok  = !$this->isCliError($out);
        $log[] = ($ok ? '' : 'WARN ') . "'{$cmd}' → " . trim(preg_replace('/\s+/', ' ', $out));

        $this->telnet->execute('exit', $this->configPrompt, 3);
        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);
        $log[] = 'Configuration saved (write)';

        return ['success' => $ok, 'message' => $ok ? $okMsg : end($log), 'log' => $log];
    }
}
