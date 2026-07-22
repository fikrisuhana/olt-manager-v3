<?php

namespace App\Libraries\Drivers;

use App\Libraries\TelnetService;

/**
 * Driver untuk OLT Fiberhome AN6000 (juga AN5516 dgn CLI serupa).
 * Diverifikasi langsung di AN6000-2 @ 136.1.1.103 (2026-07-23).
 *
 * CLI ala Cisco (config mode), BUKAN gaya `cd gpononu\` seperti AN5516 lama.
 * Numbering: frame/slot/pon/onuid  →  board=frame(1), slot, port=pon, onu_index=onuid.
 *
 * Detail command lengkap: docs/fiberhome-reference.md
 *
 * Status implementasi:
 *   ✅ terverifikasi live : connect, getUnconfiguredOnus, getRegisteredOnus, registerOnu (whitelist),
 *                           setVlan (onu wan-cfg tr069+internet), deleteOnu, getSnAtIndex, getOnuConfig
 *   ⚠️ belum fix          : getOnuSignal (syntax show onu optical), getTcontProfiles (format dba-profile)
 */
class FiberhomeDriver implements OltDriverInterface
{
    private TelnetService $telnet;
    private array $config;

    // Prompt per konteks (dipakai TelnetService::waitFor sebagai substring)
    private array $userPrompt   = ['>'];
    private array $privPrompt   = ['Admin#', '#'];
    private array $configPrompt = ['Admin(config)#', '(config)#'];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->telnet = new TelnetService();
    }

    /** Prompt untuk konteks interface pon f/s/p */
    private function ponPrompt(string $b, string $s, string $p): array
    {
        return ["(config-if-pon-{$b}/{$s}/{$p})#", '#'];
    }

    public function connect(): void
    {
        $this->telnet->connect(
            $this->config['ip'],
            (int)($this->config['telnet_port'] ?? 23),
            $this->config['telnet_user'],
            $this->config['telnet_pass']
        );

        // Setelah login FH berada di user mode: "User>"
        $echo = $this->telnet->execute('', ['#', '>'], 3);

        if (strpos($echo, '#') === false) {
            // Kirim enable, tangani password bila diminta
            $enableResp = $this->telnet->execute('enable', ['Password:', 'password:', '#'], 5);
            if (stripos($enableResp, 'password:') !== false) {
                $enablePass = trim($this->config['enable_password'] ?? '');
                $this->telnet->send($enablePass);
                $this->telnet->waitFor(['#'], 5);
            }
        }

        // Masuk config mode sebagai base — mayoritas show & set jalan di sini
        $this->telnet->execute('config', $this->configPrompt, 5);
        // Matikan paging agar output show besar tidak terpotong
        $this->telnet->execute('terminal length 0', $this->configPrompt, 5);
    }

    public function disconnect(): void
    {
        $this->telnet->disconnect();
    }

    // ── Discovery: ONU belum authorized ───────────────────────────────
    // show discovery → section "SLOT = X, PON = Y" lalu baris:
    //   No  OnuType   PhyId        PhyPwd    LogicId    LogicPwd  Why Vendor RealType ...
    //   1   HG6145E   ZTEGdce2cf07 GDCE2CF07 123456789  123456    1   ZTEG   F6600P...
    public function getUnconfiguredOnus(): array
    {
        $output = $this->telnet->execute('show discovery', $this->configPrompt, 20);

        $onus = [];
        $curSlot = null; $curPon = null;
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if (preg_match('/SLOT\s*=\s*(\d+)\s*,\s*PON\s*=\s*(\d+)/i', $line, $m)) {
                $curSlot = $m[1]; $curPon = $m[2];
                continue;
            }
            if ($curSlot === null) continue;

            // Baris data: No OnuType PhyId ...  (PhyId = serial)
            if (preg_match('/^(\d+)\s+(\S+)\s+([A-Za-z0-9]{8,20})\s+/', $line, $m)) {
                $onus[] = [
                    'board'     => '1',
                    'slot'      => $curSlot,
                    'port'      => $curPon,
                    'onu_index' => 0,            // belum ada index — ditentukan saat register
                    'sn'        => strtoupper($m[3]),
                    'onu_type'  => $m[2],
                    'state'     => 'auto-found',
                ];
            }
        }
        return $onus;
    }

    // ── Authorization table: ONU terdaftar ────────────────────────────
    // show authorization → baris: Slot Pon Onu OnuType ST Lic OST PhyId ... Vendor RealType
    //   2    16  1   HG6145E  A  0   dn  FHTT9d308858 ... FHTT   HG6145D2
    public function getRegisteredOnus(): array
    {
        $output = $this->telnet->execute('show authorization', $this->configPrompt, 30);
        return $this->parseAuthTable($output);
    }

    private function parseAuthTable(string $output): array
    {
        $onus = [];
        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            // Kolom: Slot Pon Onu OnuType ST Lic OST PhyId ...
            // ST = A/P/R, OST = up/dn (kadang state lain) → longgarkan jadi \S+.
            if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+([APR])\s+(\S+)\s+(\S+)\s+([A-Za-z0-9]{8,20})/i', $line, $m)) {
                $onus[] = [
                    'board'     => '1',
                    'slot'      => $m[1],
                    'port'      => $m[2],
                    'onu_index' => $m[3],
                    'onu_type'  => $m[4],
                    'sn'        => strtoupper($m[8]),
                    'status'    => strtolower($m[7]) === 'up' ? 'ready' : 'offline',
                ];
            }
        }
        return $onus;
    }

    // ── Register ONU (whitelist add) ✅ terverifikasi ──────────────────
    // whitelist add phy-id <SN> type <TYPE> slot <slot> pon <pon> onuid <index>
    // Lalu (opsional) set nama + VLAN via interface pon.
    public function registerOnu(array $params): array
    {
        $board = $params['board'];   // frame (biasanya 1)
        $slot  = $params['slot'];
        $port  = $params['port'];    // pon
        $idx   = $params['onu_index'];
        $type  = trim($params['onu_type'] ?? '');
        $sn    = strtoupper($params['sn']);
        $name  = trim($params['name'] ?? '');
        $log   = [];

        if ($type === '') {
            throw new \Exception("Fiberhome wajib tipe ONU (mis. HG6145E). onu_type kosong.");
        }

        $vlanInternet = (int)($params['vlan_internet'] ?? 0);
        $vlanAcs      = (int)($params['vlan_acs'] ?? 0);
        $force        = (bool)($params['force'] ?? false);

        // 1) whitelist add — di config mode.
        // phy-id FH CASE-SENSITIVE (mis. ZTEGdce2cf07, bagian hex huruf kecil). App simpan SN uppercase,
        // jadi resolve dulu case-asli dari OLT (discovery/authorization) sebelum whitelist add.
        $phyId = $this->resolvePhyId($board, $slot, $port, $sn);
        $cmd = "whitelist add phy-id {$phyId} type {$type} slot {$slot} pon {$port} onuid {$idx}";
        $out = $this->telnet->execute($cmd, $this->configPrompt, 10);
        $log[] = "whitelist add → " . $this->flat($out);

        // "already exist" (spesifik) = ONU sudah terdaftar. Jangan pakai "exist" saja —
        // pesan error lain seperti "type not exist" juga mengandung "exist".
        $exists = stripos($out, 'already exist') !== false || stripos($out, 'already added') !== false;
        if (!$exists) {
            foreach (['error', 'invalid', 'fail', 'unknown', 'incomplete', 'not exist', 'illegal', '% '] as $pat) {
                if (stripos($out, $pat) !== false) {
                    throw new \Exception("Gagal whitelist add ONU: " . $this->flat($out));
                }
            }
        } elseif (!$force) {
            throw new \Exception("ONU {$sn} sudah ada di whitelist OLT.");
        }
        $log[] = "ONU {$sn} ditambahkan ke whitelist (slot {$slot} pon {$port} onuid {$idx})";

        // 2) Konteks interface pon untuk nama + VLAN
        $pon = $this->ponPrompt($board, $slot, $port);
        $this->telnet->execute("interface pon {$board}/{$slot}/{$port}", $pon, 5);

        // Nama pelanggan → onu description <idx> <name> id 0
        // PENTING: FH tidak menerima spasi/karakter khusus (spasi → "% Unknown command").
        // Sanitasi jadi token aman (spasi→'-'); DB tetap simpan nama asli (lihat OnuController).
        $descName = $this->sanitizeDesc($name);
        if ($descName !== '') {
            $o = $this->telnet->execute("onu description {$idx} {$descName} id 0", $pon, 5);
            if ($this->isErr($o)) $log[] = "WARN description → " . $this->flat($o);
            else $log[] = "Nama diset: {$descName}";
        }

        // VLAN + service WAN
        if ($vlanAcs || $vlanInternet) {
            $pppoeUser = trim($params['pppoe_user'] ?? '');
            $pppoePass = trim($params['pppoe_pass'] ?? '');
            $this->setService($idx, $sn, $vlanAcs, $vlanInternet, $pppoeUser, $pppoePass, $pon, $log);
        }

        $this->telnet->execute('exit', $this->configPrompt, 3);

        // Simpan ke flash
        $this->telnet->execute('save', $this->configPrompt, 30);
        $log[] = 'Konfigurasi disimpan (save)';

        return ['success' => true, 'log' => $log];
    }

    // Port yang di-bind ke WAN internet (4 LAN + 2 band WiFi) — default umum.
    private const WAN_BIND = 'entries 6 fe1 fe2 fe3 fe4 ssid1 ssid5';

    /**
     * Set VLAN + service WAN ONU di konteks interface pon.
     * ✅ Grammar `onu wan-cfg` diverifikasi tulis di OLT AN6000 (2026-07-23) — masuk running-config, "set ok!".
     *
     * Grammar:
     *   onu wan-cfg <id> index <n> mode <tr069|internet|voip|...> type <route|bridge> <vid> <cos 0-7|65535>
     *       nat <enable|disable> qos <enable|disable> dsp <dhcp|static|pppoe|null> [pppoe params]
     *       [entries <n> <fe1-4|ssid1-8|10glan>...]
     *   pppoe: dsp pppoe pro <enable|disable> <user> <pass> <servname|null> <auto|payload|manual>
     *
     * Strategi (keputusan user):
     *   - Kanal 1 = tr069 (VLAN ACS, dhcp) → ONU selalu bisa konek GenieACS.
     *   - ONU Fiberhome (SN FHTT/FHSC) → kanal 2 internet PPPoE full DI OLT (dsp pppoe). [verified]
     *   - ONU non-FH (ZTE dll)          → internet diserahkan ACS/TR-069 (OMCI wan-cfg FH belum tentu
     *                                       dihormati ONU non-FH; ACS authoritative).
     */
    private function setService(
        string $idx, string $sn, int $vlanAcs, int $vlanInternet,
        string $pppoeUser, string $pppoePass, array $ponPrompt, array &$log
    ): void {
        $isFH = strncasecmp($sn, 'FHTT', 4) === 0 || strncasecmp($sn, 'FHSC', 4) === 0;
        $ind  = 1;

        // Kanal management/ACS (semua brand)
        if ($vlanAcs) {
            $cmd = "onu wan-cfg {$idx} index {$ind} mode tr069 type route {$vlanAcs} 65535 nat disable qos disable dsp dhcp entries 0";
            $o = $this->telnet->execute($cmd, $ponPrompt, 6);
            $log[] = ($this->isErr($o) ? "WARN " : "") . "wan-cfg ACS(tr069) vlan {$vlanAcs} → " . $this->flat($o);
            $ind++;
        }

        if (!$vlanInternet) return;

        if ($isFH && $pppoeUser !== '' && $pppoePass !== '') {
            // ONU FH: PPPoE full di OLT (verified)
            $cmd = "onu wan-cfg {$idx} index {$ind} mode internet type route {$vlanInternet} 65535 nat enable qos disable "
                 . "dsp pppoe pro disable {$pppoeUser} {$pppoePass} null auto " . self::WAN_BIND;
            $o = $this->telnet->execute($cmd, $ponPrompt, 6);
            $log[] = ($this->isErr($o) ? "WARN " : "") . "wan-cfg internet PPPoE vlan {$vlanInternet} user={$pppoeUser} → " . $this->flat($o);
        } elseif ($isFH) {
            // ONU FH tanpa kredensial: kanal internet DHCP (ACS bisa ambil alih)
            $cmd = "onu wan-cfg {$idx} index {$ind} mode internet type route {$vlanInternet} 65535 nat enable qos disable dsp dhcp " . self::WAN_BIND;
            $o = $this->telnet->execute($cmd, $ponPrompt, 6);
            $log[] = ($this->isErr($o) ? "WARN " : "") . "wan-cfg internet DHCP vlan {$vlanInternet} → " . $this->flat($o);
        } else {
            // ONU non-FH: internet diurus ACS/TR-069, OLT tidak set kanal internet
            $log[] = "non-FH ONU: internet vlan {$vlanInternet} diserahkan ACS (skip onu wan-cfg internet)";
        }
    }

    // ── Delete ONU (no whitelist) ✅ terverifikasi ─────────────────────
    // interface pon f/s/p → no whitelist <onuid>  (deauth + hapus whitelist sekaligus)
    public function deleteOnu(string $board, string $slot, string $port, string $onuIndex): bool
    {
        $pon = $this->ponPrompt($board, $slot, $port);
        $this->telnet->execute("interface pon {$board}/{$slot}/{$port}", $pon, 5);
        $out = $this->telnet->execute("no whitelist {$onuIndex}", $pon, 10);
        $this->telnet->execute('exit', $this->configPrompt, 3);
        $this->telnet->execute('save', $this->configPrompt, 30);

        // "Deauth ONU X success." = sukses
        return stripos($out, 'success') !== false || !$this->isErr($out);
    }

    // ── Ambil SN di index tertentu (verifikasi sebelum delete) ─────────
    public function getSnAtIndex(string $board, string $slot, string $port, string $onuIndex): ?string
    {
        $out = $this->telnet->execute("show authorization {$board}/{$slot}/{$port}", $this->configPrompt, 15);
        foreach ($this->parseAuthTable($out) as $onu) {
            if ((string)$onu['onu_index'] === (string)$onuIndex) {
                return $onu['sn'];
            }
        }
        return null;
    }

    // ── Sinyal optik ⚠️ syntax belum fix ──────────────────────────────
    // Kandidat: `show onu optical <f/s/p> <onuid>`. Parse Rx/Tx (dBm).
    public function getOnuSignal(string $board, string $slot, string $port, string $onuIndex): array
    {
        $result = ['olt_rx' => null, 'onu_tx' => null, 'olt_tx' => null, 'onu_rx' => null];
        try {
            $out = $this->telnet->execute(
                "show onu optical {$board}/{$slot}/{$port} {$onuIndex}",
                $this->configPrompt, 10
            );
            // Pola umum FH: "Rx power : -21.67 dBm", "Tx power : 2.31 dBm"
            if (preg_match('/Rx\s*power[^\-\d]*([\-\d\.]+)/i', $out, $m)) $result['onu_rx'] = $m[1];
            if (preg_match('/Tx\s*power[^\-\d]*([\-\d\.]+)/i', $out, $m)) $result['onu_tx'] = $m[1];
        } catch (\Exception $e) { /* biarkan null */ }
        return $result;
    }

    /**
     * Status WAN/DHCP ONU: apakah dapat IP?  (fitur "cek status DHCP")
     * Command: interface pon f/s/p → show onu wan-info <onuid>
     * State OLT:
     *   "onu not online!"        → ONU offline
     *   "onu wan info is empty!"  → belum ada WAN dikonfigurasi
     *   selain itu               → tabel WAN, parse IP.
     * Return: ['online'=>bool,'has_wan'=>bool,'ip'=>?string,'raw'=>string]
     */
    public function getWanInfo(string $board, string $slot, string $port, string $onuIndex): array
    {
        $pon = $this->ponPrompt($board, $slot, $port);
        $this->telnet->execute("interface pon {$board}/{$slot}/{$port}", $pon, 5);
        $raw = $this->telnet->execute("show onu wan-info {$onuIndex}", $pon, 10);
        $this->telnet->execute('exit', $this->configPrompt, 3);

        $notOnline = stripos($raw, 'not online') !== false;
        $empty     = stripos($raw, 'is empty')  !== false;
        $ip = null;
        // Cari IP address (hindari 0.0.0.0)
        if (preg_match_all('/\b(\d{1,3}(?:\.\d{1,3}){3})\b/', $raw, $mm)) {
            foreach ($mm[1] as $cand) {
                if ($cand !== '0.0.0.0' && $cand !== '255.255.255.255') { $ip = $cand; break; }
            }
        }
        return [
            'online'  => !$notOnline,
            'has_wan' => !$empty && !$notOnline,
            'ip'      => $ip,
            'raw'     => trim($raw),
        ];
    }

    // ── Config satu ONU (parse VLAN dari running-config) ───────────────
    public function getOnuConfig(string $board, string $slot, string $port, string $onuIndex): array
    {
        $out = $this->telnet->execute(
            "show onu running-config {$board}/{$slot}/{$port} {$onuIndex}",
            $this->configPrompt, 15
        );

        $result = [
            'name'            => null,
            'tcont_profile'   => '',
            'traffic_profile' => '',
            'vlan_internet'   => 0,
            'vlan_acs'        => 0,
            'service_ports'   => [],
        ];

        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            // onu wan-cfg <id> ind <k> mode tr069 ty r <vlan> ...  → ACS/management
            if (preg_match('/onu\s+wan-cfg\s+\d+\s+ind\s+\d+\s+mode\s+tr069\s+ty\s+\S+\s+(\d+)/i', $line, $m)) {
                $result['vlan_acs'] = (int)$m[1];
            }
            // onu wan-cfg <id> ind <k> mode inter[net] ty r <vlan> ...  → internet
            elseif (preg_match('/onu\s+wan-cfg\s+\d+\s+ind\s+\d+\s+mode\s+inter(?:net)?\s+ty\s+\S+\s+(\d+)/i', $line, $m)) {
                $result['vlan_internet'] = (int)$m[1];
            }
            // onu description <id> <name> id 0
            if (preg_match('/onu\s+description\s+\d+\s+(.+?)\s+id\s+\d+/i', $line, $m)) {
                $result['name'] = trim($m[1]);
            }
        }
        return $result;
    }

    /**
     * Push VLAN + ACS ke ONU yang sudah terdaftar (tanpa re-register).
     * ✅ Pakai setVlanInPon (grammar wan-cfg terverifikasi).
     * PPPoE diabaikan untuk FH (dibuat ACS/TR-069 — FH auto-create WAN).
     */
    public function applyPonMng(
        string $board, string $slot, string $port, string $onuIndex,
        int $vlanAcs, string $acsUrl,
        int $vlanInternet = 0, string $pppoeUser = '', string $pppoePass = ''
    ): array {
        if (!$vlanInternet && !$vlanAcs) {
            throw new \Exception("VLAN internet dan VLAN ACS keduanya kosong.");
        }
        $log = [];
        $pon = $this->ponPrompt($board, $slot, $port);
        // SN diambil dari OLT untuk deteksi brand (FH vs non-FH)
        $sn  = $this->getSnAtIndex($board, $slot, $port, $onuIndex) ?? '';
        $this->telnet->execute("interface pon {$board}/{$slot}/{$port}", $pon, 5);
        $this->setService($onuIndex, $sn, $vlanAcs, $vlanInternet, $pppoeUser, $pppoePass, $pon, $log);
        $this->telnet->execute('exit', $this->configPrompt, 3);
        $this->telnet->execute('save', $this->configPrompt, 30);
        $log[] = 'VLAN service disimpan (save)';
        return ['success' => true, 'log' => $log];
    }

    // ── Profiles ──────────────────────────────────────────────────────
    // FH pakai DBA profile (setara TCONT ZTE). Parse dari show gpon-dba-profile.
    // DBA/bandwidth profile FH (setara TCONT ZTE).
    // Format tabular: "Index  Profile-Name  Type  Fix-BW  Ass-BW  Max-BW", baris data diawali angka.
    public function getTcontProfiles(): array
    {
        $out = $this->telnet->execute('show gpon-dba-profile', $this->configPrompt, 10);
        $profiles = [];
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            // Baris data: <index> <profile-name> ...  (lewati header "Index..." dan separator "----")
            if (preg_match('/^\d+\s+([A-Za-z0-9._\-]+)\s+/', $line, $m)) {
                $profiles[] = $m[1];
            }
        }
        return array_values(array_unique(array_filter($profiles)));
    }

    public function getTrafficProfiles(): array { return []; }
    public function getVlanProfiles(): array { return []; }

    // ── helpers ───────────────────────────────────────────────────────
    private function flat(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Cari phy-id ONU dengan CASE ASLI (mis. ZTEGdce2cf07) yang cocok dengan $snUpper
     * (case-insensitive). phy-id FH case-sensitive di `whitelist add`.
     * Cek discovery (ONU baru) lalu authorization (ONU sudah terdaftar utk re-config).
     * Fallback: kembalikan $snUpper apa adanya bila tidak ketemu.
     */
    private function resolvePhyId(string $b, string $s, string $p, string $snUpper): string
    {
        foreach (["show discovery {$b}/{$s}/{$p}", "show authorization {$b}/{$s}/{$p}"] as $cmd) {
            $out = $this->telnet->execute($cmd, $this->configPrompt, 20);
            foreach (explode("\n", $out) as $line) {
                if (preg_match_all('/\b([A-Za-z]{3,5}[A-Za-z0-9]{6,17})\b/', $line, $mm)) {
                    foreach ($mm[1] as $cand) {
                        if (strcasecmp($cand, $snUpper) === 0) return $cand;
                    }
                }
            }
        }
        return $snUpper;
    }

    /** Nama aman untuk `onu description` FH: spasi→'-', buang char selain [A-Za-z0-9._-]. */
    private function sanitizeDesc(string $name): string
    {
        $s = preg_replace('/\s+/', '-', trim($name));
        $s = preg_replace('/[^A-Za-z0-9._\-]/', '', $s);
        return trim($s, '-');
    }

    private function isErr(string $s): bool
    {
        return (bool)preg_match('/(error|invalid|fail|unknown command|incomplete|%\s)/i', $s);
    }

    public function getBrand(): string { return 'Fiberhome'; }
    public function getModel(): string { return $this->config['model'] ?? 'AN6000'; }
}
