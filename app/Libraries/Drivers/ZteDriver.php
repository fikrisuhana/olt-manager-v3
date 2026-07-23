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

        // Parameter terstruktur
        $vlanInternet = (int)($params['vlan_internet'] ?? 0);
        $vlanAcs      = (int)($params['vlan_acs'] ?? 0);
        $tcont        = trim($params['tcont_profile'] ?? '');

        // Script tambahan dari template (opsional)
        $ifExtra = trim($params['gpon_onu_script'] ?? '');

        // --- Build perintah gpon-onu interface ---
        // Format diverifikasi dari "show running-config interface" ZTE C320 v1.2
        $ifCmds = [];
        $ifCmds[] = 'sn-bind enable sn';
        $trafficProfile = trim($params['traffic_profile'] ?? '');
        if ($tcont) {
            $ifCmds[] = "tcont 1 name tcont profile {$tcont}";
            $ifCmds[] = "gemport 1 name gemport tcont 1";
            if ($trafficProfile) {
                $ifCmds[] = "gemport 1 traffic-limit upstream {$trafficProfile} downstream {$trafficProfile}";
                $log[] = "Traffic limit: {$trafficProfile}";
            }
            $log[] = "TCONT profile: {$tcont}";
        }
        // ACS dulu (sp1), internet kedua (sp2) — sesuai konvensi OLT tgp
        $spIdx = 1;
        if ($vlanAcs) {
            $ifCmds[] = "service-port {$spIdx} vport 1 user-vlan {$vlanAcs} vlan {$vlanAcs}";
            $spIdx++;
            $log[] = "VLAN ACS: {$vlanAcs}";
        }
        if ($vlanInternet) {
            $ifCmds[] = "service-port {$spIdx} vport 1 user-vlan {$vlanInternet} vlan {$vlanInternet}";
            $log[] = "VLAN internet: {$vlanInternet}";
        }
        foreach (explode("\n", $ifExtra) as $cmd) {
            $cmd = trim($cmd);
            if ($cmd && !str_starts_with($cmd, '#')) $ifCmds[] = $cmd;
        }

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
        $this->telnet->execute("interface gpon-onu_{$board}/{$slot}/{$port}:{$idx}", $this->ifPrompt, 5);
        $this->telnet->execute("name {$name}", $this->ifPrompt, 3);
        foreach ($ifCmds as $cmd) {
            $out = $this->telnet->execute($cmd, $this->ifPrompt, 5);
            if (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false) {
                $log[] = "WARN: '{$cmd}' → " . trim(substr($out, -120));
            }
        }

        $this->telnet->execute('exit', $this->configPrompt, 3);
        $log[] = 'gpon-onu interface configured';

        // pon-onu-mng: blok terpisah — service mapping VLAN ke veip
        // Syntax diverifikasi dari running-config ZTE C320:
        //   pon-onu-mng gpon-onu_B/S/P:I
        //     service hsi gemport 1 vlan {internet}
        //     service acs gemport 1 vlan {acs}
        //     vlan port veip_1 mode hybrid
        if ($vlanInternet || $vlanAcs) {
            $pppoeUser = trim($params['pppoe_user'] ?? '');
            $pppoePass = trim($params['pppoe_pass'] ?? '');

            $this->telnet->execute("pon-onu-mng gpon-onu_{$board}/{$slot}/{$port}:{$idx}", $this->mngPrompt, 5);
            // ACS dulu baru internet — sesuai urutan service-port di gpon-onu interface
            if ($vlanAcs) {
                $out = $this->telnet->execute("service acs gemport 1 vlan {$vlanAcs}", $this->mngPrompt, 5);
                if (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false) {
                    $log[] = "WARN pon-onu-mng: service acs → " . trim(substr($out, -120));
                }
            }
            $isFiberhome = strncasecmp($sn, 'FHTT', 4) === 0;
            if ($vlanInternet) {
                // ZTE ONU dengan PPPoE → service ppp, Fiberhome → service int
                $this->applyServiceInternet($vlanInternet, $log, !$isFiberhome && !empty($pppoeUser));
            }
            $this->telnet->execute("vlan port veip_1 mode hybrid", $this->mngPrompt, 5);

            // DHCP management IP + ACS URL agar ZTE ONU konek ke TR-069/GenieACS
            // ip-host 1 = management channel, tr069-mgmt 1 acs = ACS URL (diverifikasi di OLT v1+v2)
            if ($vlanAcs && !$isFiberhome) {
                $this->applyWanIpDhcp(1, $log);
                $acsUrl = trim($params['acs_url'] ?? $this->config['acs_url'] ?? '');
                if ($acsUrl) {
                    $this->applyTr069Mgmt($acsUrl, $log);
                }
            }

            // PPPoE WAN via pon-onu-mng — hanya ZTE ONU, Fiberhome pakai ACS/TR-069
            if ($pppoeUser && $pppoePass && !$isFiberhome) {
                $out = $this->telnet->execute(
                    "wan-ip 1 mode pppoe username {$pppoeUser} password {$pppoePass} vlan-profile {$pppoeProfile} host 1",
                    $this->mngPrompt, 5
                );
                if (stripos($out, 'Error') !== false || stripos($out, 'Invalid') !== false) {
                    $log[] = "WARN pon-onu-mng: wan-ip pppoe → " . trim(substr($out, -120));
                } else {
                    $this->telnet->execute("wan-ip 1 ping-response enable traceroute-response enable", $this->mngPrompt, 5);
                    $this->telnet->execute("security-mgmt 212 state enable mode forward protocol web", $this->mngPrompt, 5);
                    $log[] = "pon-onu-mng PPPoE: {$pppoeUser} profile={$pppoeProfile}";
                }
            }

            $this->telnet->execute('exit', $this->configPrompt, 3);
            $log[] = "pon-onu-mng: hsi={$vlanInternet} acs={$vlanAcs} veip_1 hybrid";
        }

        // Keluar config mode dan simpan
        $this->telnet->execute('exit', $this->rootPrompt, 3);
        $this->telnet->execute('write', $this->rootPrompt, 20);
        $log[] = 'Configuration saved (write)';

        return ['success' => true, 'log' => $log];
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

        // ip-host 1 dhcp-enable + tr069-mgmt — diverifikasi di OLT v1 (136.1.1.200) dan v2 (136.1.1.210)
        if ($vlanAcs) {
            $this->applyWanIpDhcp(1, $log);
            if ($acsUrl) {
                $this->applyTr069Mgmt($acsUrl, $log);
            }
        }

        if ($pppoeUser && $pppoePass) {
            $out   = $this->telnet->execute(
                "wan-ip 1 mode pppoe username {$pppoeUser} password {$pppoePass} vlan-profile {$pppoeProfile} host 1",
                $this->mngPrompt, 5
            );
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

    private function applyServiceInternet(int $vlan, array &$log, bool $forPppoe = false): void
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
            if (stripos($out, 'Error') === false && stripos($out, 'Invalid') === false) {
                $log[] = "service {$kw} vlan {$vlan} OK (v{$ver})";
                return;
            }
            $log[] = "WARN: service {$kw} gagal (v{$ver}), coba auto-detect";
        }
        // Auto-detect: PPPoE → ppp → hsi → int, non-PPPoE → hsi → int → ppp
        $order = $forPppoe ? ['ppp', 'hsi', 'int'] : ['hsi', 'int', 'ppp'];
        foreach ($order as $kw) {
            $out = $this->telnet->execute("service {$kw} gemport 1 vlan {$vlan}", $this->mngPrompt, 5);
            if (stripos($out, 'Error') === false && stripos($out, 'Invalid') === false) {
                $log[] = "service {$kw} vlan {$vlan} OK";
                return;
            }
        }
        $log[] = "WARN: service hsi/int/ppp semua gagal untuk vlan {$vlan}";
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
        return $this->parseProfileNames($output);
    }

    public function getTrafficProfiles(): array
    {
        $output = $this->telnet->execute('show gpon profile traffic', $this->rootPrompt, 10);
        return $this->parseProfileNames($output);
    }

    /**
     * Parse daftar nama profile dari output ZTE "show gpon profile tcont/traffic".
     * Format output: setiap profile diawali baris "Profile name :NAMA"
     */
    private function parseProfileNames(string $output): array
    {
        $profiles = [];
        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^Profile\s+name\s*:\s*(\S+)/i', trim($line), $m)) {
                $profiles[] = trim($m[1]);
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

        // Konvensi registerOnu: sp1 = ACS, sp2 = internet
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
    public function getPonMngConfig(string $board, string $slot, string $port, string $onuIndex): array
    {
        $raw = $this->telnet->execute(
            "show running-config pon-onu-mng gpon-onu_{$board}/{$slot}/{$port}:{$onuIndex}",
            $this->rootPrompt, 10
        );

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

    public function getBrand(): string { return 'ZTE'; }
    public function getModel(): string { return $this->config['model'] ?? 'C320'; }
}
