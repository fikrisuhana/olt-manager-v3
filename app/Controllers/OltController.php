<?php

namespace App\Controllers;

use App\Models\OltModel;
use App\Models\OnuModel;
use App\Models\AcsServerModel;
use App\Libraries\OltDriverFactory;
use App\Libraries\OnuCacheService;
use App\Libraries\AcsService;
use CodeIgniter\Controller;

class OltController extends Controller
{
    private int $userId;

    public function __construct()
    {
        $this->userId = (int) session()->get('user_id');
        session_write_close();
    }

    public function index()
    {
        $oltModel = new OltModel();
        return view('olt/index', [
            'title' => 'Daftar OLT',
            'olts'  => $oltModel->getByUser($this->userId),
        ]);
    }

    public function create()
    {
        return view('olt/form', ['title' => 'Tambah OLT', 'olt' => null]);
    }

    public function store()
    {
        $oltModel = new OltModel();
        $data = $this->getFormData();

        if (!$this->validateForm($data)) {
            return redirect()->back()->with('error', 'Nama, IP, username, dan password wajib diisi.')->withInput();
        }

        $data['user_id'] = $this->userId;
        $oltModel->insert($data);
        return redirect()->to('/olts')->with('success', 'OLT berhasil ditambahkan.');
    }

    private function ensureVlanProfilesColumn(): void
    {
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('olts')) {
                if (!$db->fieldExists('vlan_profiles', 'olts')) {
                    $db->query("ALTER TABLE olts ADD COLUMN vlan_profiles TEXT NULL AFTER traffic_profiles");
                }
                if (!$db->fieldExists('use_acs', 'olts')) {
                    $db->query("ALTER TABLE olts ADD COLUMN use_acs TINYINT(1) NOT NULL DEFAULT 1 AFTER acs_url");
                }
            }
        } catch (\Throwable $e) {
            // Ignore error if column exists
        }
    }

    public function show(int $id)
    {
        $this->ensureVlanProfilesColumn();
        $oltModel = new OltModel();
        $onuModel = new OnuModel();

        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) return redirect()->to('/olts')->with('error', 'OLT tidak ditemukan.');

        $cache = new OnuCacheService();

        $onus     = $onuModel->getByOlt($id);
        $onuTypes = array_values(array_unique(array_filter(array_column($onus, 'onu_type'))));

        return view('olt/show', [
            'title'            => $olt['name'],
            'olt'              => $olt,
            'onus'             => $onus,
            'onu_types'        => $onuTypes,
            'cache_updated_at' => $cache->lastUpdated($id),
        ]);
    }

    public function edit(int $id)
    {
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) return redirect()->to('/olts')->with('error', 'OLT tidak ditemukan.');

        return view('olt/form', ['title' => 'Edit OLT', 'olt' => $olt]);
    }

    public function update(int $id)
    {
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) return redirect()->to('/olts')->with('error', 'OLT tidak ditemukan.');

        $data = $this->getFormData();
        // Jika password dikosongkan, pertahankan password lama
        if (empty($data['telnet_pass'])) {
            unset($data['telnet_pass']);
        }
        if ($data['enable_password'] === null) {
            unset($data['enable_password']);
        }

        $oltModel->update($id, $data);
        return redirect()->to("/olts/{$id}")->with('success', 'OLT berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) return redirect()->to('/olts')->with('error', 'OLT tidak ditemukan.');

        $oltModel->delete($id);
        return redirect()->to('/olts')->with('success', 'OLT berhasil dihapus.');
    }

    /**
     * AJAX: scan ONU belum dikonfigurasi SAJA (1 command ke OLT).
     * Next index diambil dari cache lokal — tidak perlu tanya OLT lagi.
     * Cache harus di-refresh dulu via refreshCache() sebelum pertama kali scan.
     */
    public function scan(int $id)
    {
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $onuModel = new OnuModel();
        $cache    = new OnuCacheService();

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            // Hanya 1 command: show gpon onu uncfg
            $uncfgOnus = $driver->getUnconfiguredOnus();
            $driver->disconnect();

            // next_index dari cache & database (tidak lock OLT baru)
            $cacheData = $cache->load($id);
            $noCacheWarning = ($cacheData['updated_at'] === null);

            // Jika OLT baru & belum pernah sync cache, buat file cache awal agar timestamp terinisialisasi
            if ($noCacheWarning) {
                $cache->save($id, []);
                $cacheData = $cache->load($id);
            }

            foreach ($uncfgOnus as &$onu) {
                $existing = $onuModel->getByOltAndSn($id, $onu['sn']);
                $onu['next_index']         = $cache->nextIndex($id, $onu['board'], $onu['slot'], $onu['port']);
                $onu['already_registered'] = $existing !== null;
                $onu['existing_id']        = $existing['id'] ?? null;
            }

            return $this->response->setJSON([
                'success'          => true,
                'onus'             => $uncfgOnus,
                'count'            => count($uncfgOnus),
                'cache_updated_at' => $cacheData['updated_at'],
                'no_cache_warning' => $noCacheWarning,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: refresh cache ONU terdaftar dari OLT.
     * Ini yang berat (1 + N port commands). Harus dilakukan SEKALI di awal,
     * setelah itu cache dijaga konsisten lewat addOnu/removeOnu.
     * Jangan dipanggil terlalu sering!
     */
    public function refreshCache(int $id)
    {
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $cache = new OnuCacheService();

        try {
            // Izinkan eksekusi lebih lama — 1 cmd per port aktif bisa banyak
            set_time_limit(180);

            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $registeredOnus  = $driver->getRegisteredOnus();
            $tcontProfiles   = $driver->getTcontProfiles();
            $trafficProfiles = $driver->getTrafficProfiles();
            $driver->disconnect();

            $cache->save($id, $registeredOnus);

            // Simpan profiles ke DB
            $oltModel->update($id, [
                'tcont_profiles'   => implode("\n", $tcontProfiles),
                'traffic_profiles' => implode("\n", $trafficProfiles),
            ]);

            // Sync/upsert semua ONU terdaftar dari OLT ke tabel onus di Database
            $onuModel = new OnuModel();
            foreach ($registeredOnus as $ro) {
                $sn = strtoupper($ro['sn']);
                $onuIndex = (string)($ro['onu_index'] ?? $ro['index'] ?? '1');
                $onuType  = $ro['onu_type'] ?? $ro['type'] ?? 'ALL-ONT';
                $existing = $onuModel->getAnyByOltAndSn($id, $sn);
                if (!$existing) {
                    $onuModel->insert([
                        'olt_id'        => $id,
                        'sn'            => $sn,
                        'name'          => $ro['name'] ?? $sn,
                        'board'         => (string)$ro['board'],
                        'slot'          => (string)$ro['slot'],
                        'port'          => (string)$ro['port'],
                        'onu_index'     => $onuIndex,
                        'onu_type'      => $onuType,
                        'status'        => 'registered',
                        'registered_at' => date('Y-m-d H:i:s'),
                    ]);
                } else if ($existing['status'] === 'deleted') {
                    $onuModel->update($existing['id'], [
                        'status'        => 'registered',
                        'board'         => (string)$ro['board'],
                        'slot'          => (string)$ro['slot'],
                        'port'          => (string)$ro['port'],
                        'onu_index'     => $onuIndex,
                        'registered_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // Sekaligus fetch ACS status untuk semua SN yang ada di OLT
            $acsMessage = '';
            $acsModel   = new AcsServerModel();
            $acs        = $acsModel->getDefault($this->userId);
            if ($acs) {
                try {
                    $sns = array_map(fn($o) => strtoupper($o['sn']), $registeredOnus);
                    $acsService = new AcsService($acs);
                    $acsData    = $acsService->getDevicesBySns($sns);
                    $cache->saveAcs($id, $acsData);
                    $onlineCount = count(array_filter($acsData, fn($d) => $d['online']));
                    $acsMessage = " | ACS: {$onlineCount}/".count($acsData)." online";
                } catch (\Exception $e) {
                    $acsMessage = ' | ACS: gagal (' . $e->getMessage() . ')';
                }
            }

            return $this->response->setJSON([
                'success'   => true,
                'count'     => count($registeredOnus),
                'updated_at'=> date('Y-m-d H:i:s'),
                'message'   => 'Cache berhasil diperbarui.' . $acsMessage,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: baca cache ONU terdaftar (lokal, tanpa konek ke OLT).
     * Return: { "SN": {index, type, status, name, board, slot, port}, ... }
     */
    public function cacheData(int $id)
    {
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $cache     = new OnuCacheService();
        $data      = $cache->load($id);
        $bySnIdx   = [];

        foreach ($data['ports'] as $portKey => $onus) {
            [$b, $s, $p] = explode('/', $portKey);
            foreach ($onus as $o) {
                $bySnIdx[strtoupper($o['sn'])] = [
                    'index'  => $o['index'],
                    'type'   => $o['type'],
                    'status' => $o['status'] ?? 'unknown',
                    'name'   => $o['name'],
                    'board'  => $b, 'slot' => $s, 'port' => $p,
                ];
            }
        }

        $acsCache = $cache->loadAcs($id);

        return $this->response->setJSON([
            'success'        => true,
            'updated_at'     => $data['updated_at'],
            'data'           => $bySnIdx,
            'acs'            => $acsCache['devices'] ?? [],
            'acs_updated_at' => $acsCache['updated_at'],
        ]);
    }

    /**
     * AJAX: ambil status ACS untuk semua ONU di OLT ini.
     * Return: { "SN": {online, last_inform, device_id, model, manufacturer}, ... }
     */
    public function acsStatus(int $id)
    {
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $onuModel  = new OnuModel();
        $acsModel  = new AcsServerModel();
        $acs       = $acsModel->getDefault($this->userId);

        if (!$acs) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada ACS server default.']);
        }

        $onus = $onuModel->getByOlt($id);
        $sns  = array_column($onus, 'sn');

        try {
            $acsService = new AcsService($acs);
            $acsData    = $acsService->getDevicesBySns($sns);

            return $this->response->setJSON([
                'success' => true,
                'data'    => $acsData,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Test koneksi Telnet ke OLT.
     * POST — mengambil ip, telnet_port, telnet_user, telnet_pass dari form (bukan dari DB).
     * Jika edit OLT dan password dikosongkan, ambil dari DB via olt_id.
     */
    public function testTelnet()
    {
        $this->response->setContentType('application/json');

        $ip       = trim($this->request->getPost('ip') ?? '');
        $port     = (int)($this->request->getPost('telnet_port') ?: 23);
        $user     = trim($this->request->getPost('telnet_user') ?? '');
        $pass     = trim($this->request->getPost('telnet_pass') ?? '');
        $oltId    = (int)($this->request->getPost('olt_id') ?: 0);

        if (empty($ip) || empty($user)) {
            return $this->response->setJSON(['success' => false, 'message' => 'IP dan username wajib diisi.']);
        }

        // Saat edit OLT, password bisa kosong — ambil dari DB
        if (empty($pass) && $oltId > 0) {
            $oltModel = new OltModel();
            $olt = $oltModel->getByUserAndId($this->userId, $oltId);
            $pass = $olt['telnet_pass'] ?? '';
        }

        if (empty($pass)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Password diperlukan untuk test koneksi.']);
        }

        try {
            $t0     = microtime(true);
            $telnet = new \App\Libraries\TelnetService();
            $telnet->connect($ip, $port, $user, $pass);
            $elapsed = round((microtime(true) - $t0) * 1000);
            $telnet->disconnect();

            return $this->response->setJSON([
                'success' => true,
                'message' => "Terhubung ke {$ip}:{$port} dalam {$elapsed}ms.",
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Debug login step-by-step ke OLT (untuk diagnosa masalah koneksi).
     * POST — ip, telnet_port, telnet_user, telnet_pass, enable_password, brand, olt_id
     */
    public function debugConnect()
    {
        $this->response->setContentType('application/json');

        $ip          = trim($this->request->getPost('ip') ?? '');
        $port        = (int)($this->request->getPost('telnet_port') ?: 23);
        $user        = trim($this->request->getPost('telnet_user') ?? '');
        $pass        = trim($this->request->getPost('telnet_pass') ?? '');
        $enablePass  = trim($this->request->getPost('enable_password') ?? '');
        $oltId       = (int)($this->request->getPost('olt_id') ?: 0);

        if (empty($ip) || empty($user)) {
            return $this->response->setJSON(['success' => false, 'log' => ['[ERROR] IP dan username wajib diisi.']]);
        }

        // Ambil password dari DB jika kosong (mode edit)
        if (($empty = empty($pass) || empty($enablePass)) && $oltId > 0) {
            $oltModel = new OltModel();
            $olt = $oltModel->getByUserAndId($this->userId, $oltId);
            if (empty($pass))       $pass       = $olt['telnet_pass']     ?? '';
            if (empty($enablePass)) $enablePass = $olt['enable_password'] ?? '';
        }

        $log = [];

        try {
            // Step 1: TCP connect + login
            $t0     = microtime(true);
            $telnet = new \App\Libraries\TelnetService();
            $log[]  = "[INFO] Menghubungi {$ip}:{$port} ...";
            $telnet->connect($ip, $port, $user, $pass);
            $ms     = round((microtime(true) - $t0) * 1000);
            $log[]  = "[OK] Login sebagai '{$user}' berhasil ({$ms}ms)";

            // Step 2: Deteksi mode setelah login
            $echo = $telnet->execute('', ['#', '>'], 3);
            $prompt = trim(substr($echo, strrpos($echo, "\n") + 1));

            if (strpos($echo, '#') !== false) {
                $log[] = "[OK] Langsung privileged mode (#) — enable password tidak diperlukan";
                $log[] = "[INFO] Prompt: " . esc(substr($prompt, -30));
            } else {
                $log[] = "[INFO] User mode (>) terdeteksi — mengirim 'enable'";

                // Step 3: Enable
                $enableResp = $telnet->execute('enable', ['Password:', 'password:', '#'], 5);

                if (stripos($enableResp, 'password:') !== false) {
                    $log[] = "[INFO] Enable password diminta oleh OLT";

                    if (empty($enablePass)) {
                        $log[] = "[WARN] Enable password tidak dikonfigurasi — mencoba tanpa password";
                        $telnet->send('');
                    } else {
                        $log[] = "[INFO] Mengirim enable password (" . strlen($enablePass) . " karakter)";
                        $telnet->send($enablePass);
                    }

                    $afterEnable = $telnet->waitFor(['#', '>', 'fail', 'incorrect', 'bad', 'denied'], 5);

                    if (strpos($afterEnable, '#') !== false) {
                        $log[] = "[OK] Enable berhasil — masuk privileged mode (#)";
                    } else {
                        $snippet = esc(trim(substr($afterEnable, -80)));
                        $log[] = "[ERROR] Enable gagal. OLT response: {$snippet}";
                        $telnet->disconnect();
                        return $this->response->setJSON(['success' => false, 'log' => $log]);
                    }

                } elseif (strpos($enableResp, '#') !== false) {
                    $log[] = "[OK] Enable tanpa password — privileged mode (#)";
                } else {
                    $snippet = esc(trim(substr($enableResp, -80)));
                    $log[] = "[ERROR] Tidak ada respons yang diharapkan setelah 'enable': {$snippet}";
                    $telnet->disconnect();
                    return $this->response->setJSON(['success' => false, 'log' => $log]);
                }
            }

            // Step 4: terminal length 0
            $telnet->execute('terminal length 0', ['#'], 5);
            $log[] = "[OK] terminal length 0 — pager dinonaktifkan";

            // Step 5: Test command show version
            $ver = $telnet->execute('show version', ['#'], 8);
            $firstLine = trim(explode("\n", $ver)[0] ?? '');
            if (!empty($firstLine)) {
                $log[] = "[OK] 'show version' → " . esc(substr($firstLine, 0, 80));
            } else {
                $log[] = "[WARN] 'show version' tidak ada output";
            }

            $telnet->disconnect();
            $log[] = "[OK] Koneksi ditutup — semua langkah berhasil";

            return $this->response->setJSON(['success' => true, 'log' => $log]);

        } catch (\Exception $e) {
            $log[] = "[ERROR] Exception: " . $e->getMessage();
            return $this->response->setJSON(['success' => false, 'log' => $log]);
        }
    }

    /**
     * AJAX: Import ONU dari cache lokal ke database.
     * ONU yang sudah ada di DB (SN sama) di-skip.
     */
    public function importFromCache(int $id)
    {
        $this->response->setContentType('application/json');

        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $cache    = new OnuCacheService();
        $cacheData = $cache->load($id);

        if (empty($cacheData['ports'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Cache kosong. Jalankan Sync Cache dahulu.']);
        }

        $onuModel  = new OnuModel();
        $logModel  = new \App\Models\ProvisionLogModel();
        $imported  = 0;
        $skipped   = 0;

        foreach ($cacheData['ports'] as $portKey => $onus) {
            [$board, $slot, $port] = explode('/', $portKey);
            foreach ($onus as $onu) {
                $sn      = strtoupper($onu['sn']);
                $existing = $onuModel->getAnyByOltAndSn($id, $sn);

                if ($existing) {
                    if ($existing['status'] !== 'deleted') {
                        $skipped++;
                        continue;
                    }
                    // Restore ONU yang sudah deleted
                    $onuModel->update($existing['id'], [
                        'board'         => $board,
                        'slot'          => $slot,
                        'port'          => $port,
                        'onu_index'     => (int)$onu['index'],
                        'onu_type'      => $onu['type'] ?? 'ALL-ONT',
                        'status'        => 'registered',
                        'registered_at' => date('Y-m-d H:i:s'),
                    ]);
                    $logModel->log($this->userId, 'import', 'success', "Restore dari cache: {$sn}", $existing['id'], $id);
                    $imported++;
                    continue;
                }

                $onuId = $onuModel->insert([
                    'olt_id'        => $id,
                    'sn'            => $sn,
                    'name'          => $onu['name'] ?: $sn,
                    'board'         => $board,
                    'slot'          => $slot,
                    'port'          => $port,
                    'onu_index'     => (int)$onu['index'],
                    'onu_type'      => $onu['type'] ?? 'ALL-ONT',
                    'status'        => 'registered',
                    'registered_at' => date('Y-m-d H:i:s'),
                ]);
                $logModel->log($this->userId, 'import', 'success', "Import dari cache: {$sn}", $onuId, $id);
                $imported++;
            }
        }

        return $this->response->setJSON([
            'success'  => true,
            'imported' => $imported,
            'skipped'  => $skipped,
            'message'  => "{$imported} ONU diimpor, {$skipped} sudah ada di DB.",
        ]);
    }

    /**
     * AJAX: Ambil daftar TCONT profile dari OLT via Telnet.
     * POST — ip, telnet_port, telnet_user, telnet_pass, brand, olt_id (jika edit)
     */
    public function fetchTcont()
    {
        $this->response->setContentType('application/json');

        $ip    = trim($this->request->getPost('ip') ?? '');
        $port  = (int)($this->request->getPost('telnet_port') ?: 23);
        $user  = trim($this->request->getPost('telnet_user') ?? '');
        $pass  = trim($this->request->getPost('telnet_pass') ?? '');
        $brand = trim($this->request->getPost('brand') ?? 'ZTE');
        $oltId = (int)($this->request->getPost('olt_id') ?: 0);

        if (empty($ip) || empty($user)) {
            return $this->response->setJSON(['success' => false, 'message' => 'IP dan username wajib diisi.']);
        }

        if (empty($pass) && $oltId > 0) {
            $oltModel = new OltModel();
            $olt = $oltModel->getByUserAndId($this->userId, $oltId);
            $pass = $olt['telnet_pass'] ?? '';
        }

        if (empty($pass)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Password diperlukan untuk ambil data dari OLT.']);
        }

        try {
            $oltConfig = [
                'ip'          => $ip,
                'telnet_port' => $port,
                'telnet_user' => $user,
                'telnet_pass' => $pass,
                'brand'       => $brand,
                'model'       => $this->request->getPost('model') ?? '',
            ];
            $driver          = OltDriverFactory::make($oltConfig);
            $driver->connect();
            $profiles        = $driver->getTcontProfiles();
            $trafficProfiles = $driver->getTrafficProfiles();
            $driver->disconnect();

            // Simpan ke DB sekalian
            if ($oltId > 0) {
                $oltModel = new OltModel();
                $olt2 = $oltModel->getByUserAndId($this->userId, $oltId);
                if ($olt2) {
                    $oltModel->update($oltId, [
                        'tcont_profiles'   => implode("\n", $profiles),
                        'traffic_profiles' => implode("\n", $trafficProfiles),
                    ]);
                }
            }

            return $this->response->setJSON([
                'success'          => true,
                'profiles'         => $profiles,
                'traffic_profiles' => $trafficProfiles,
                'count'            => count($profiles),
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ambil daftar onu vlan-profile dari OLT yang sudah tersimpan.
     * GET /olts/{id}/vlan-profiles
     */
    public function fetchVlanProfiles(int $id)
    {
        $this->response->setContentType('application/json');
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }
        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $profiles = $driver->getVlanProfiles();
            $driver->disconnect();
            return $this->response->setJSON(['success' => true, 'profiles' => $profiles]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Sync semua profil OLT (TCONT, Traffic Limit, VLAN profile) langsung dari OLT via Telnet.
     * POST /olts/{id}/sync-profiles
     */
    public function syncProfiles(int $id)
    {
        $this->ensureVlanProfilesColumn();
        $this->response->setContentType('application/json');
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $tcontProfiles   = $driver->getTcontProfiles();
            $trafficProfiles = $driver->getTrafficProfiles();
            $vlanProfiles    = $driver->getVlanProfiles();
            $driver->disconnect();

            $vlanProfilesFormatted = array_map(function($p) {
                return "{$p['name']} — VLAN {$p['vlan']}";
            }, $vlanProfiles);

            $updateData = [
                'tcont_profiles'   => implode("\n", $tcontProfiles),
                'traffic_profiles' => implode("\n", $trafficProfiles),
                'vlan_profiles'    => implode("\n", $vlanProfilesFormatted),
            ];

            $oltModel->update($id, $updateData);

            return $this->response->setJSON([
                'success'          => true,
                'tcont_profiles'   => $tcontProfiles,
                'traffic_profiles' => $trafficProfiles,
                'vlan_profiles'    => $vlanProfilesFormatted,
                'message'          => 'Semua profil OLT (TCONT, Traffic Limit, VLAN) berhasil ditarik dari OLT dan disimpan.',
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Simpan profil OLT manual (TCONT, Traffic Limit, VLAN profile).
     * POST /olts/{id}/save-profiles
     */
    public function saveProfiles(int $id)
    {
        $this->ensureVlanProfilesColumn();
        $this->response->setContentType('application/json');
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $tcont   = trim($this->request->getPost('tcont_profiles') ?? '');
        $traffic = trim($this->request->getPost('traffic_profiles') ?? '');
        $vlan    = trim($this->request->getPost('vlan_profiles') ?? '');

        $oltModel->update($id, [
            'tcont_profiles'   => $tcont ?: null,
            'traffic_profiles' => $traffic ?: null,
            'vlan_profiles'    => $vlan ?: null,
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Profil OLT berhasil disimpan.',
        ]);
    }

    /**
     * AJAX: Tambah profil baru langsung ke OLT via Telnet & simpan di DB.
     * POST /olts/{id}/add-profile
     */
    public function addProfile(int $id)
    {
        $this->ensureVlanProfilesColumn();
        $this->response->setContentType('application/json');
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $type  = trim($this->request->getPost('type') ?? '');
        $name  = trim($this->request->getPost('name') ?? '');
        $param = (int)($this->request->getPost('param') ?? 0);

        if (empty($name)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Nama profil wajib diisi.']);
        }

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();

            $res = ['success' => false, 'message' => 'Tipe profil tidak valid.'];
            if ($type === 'tcont') {
                $maxBw = $param > 0 ? $param : 102400; // default 100M in kbps
                $res = $driver->addTcontProfile($name, $maxBw);
                if ($res['success']) {
                    $existing = array_filter(array_map('trim', explode("\n", $olt['tcont_profiles'] ?? '')));
                    if (!in_array($name, $existing)) {
                        $existing[] = $name;
                        $oltModel->update($id, ['tcont_profiles' => implode("\n", $existing)]);
                    }
                }
            } elseif ($type === 'traffic') {
                $speed = $param > 0 ? $param : 102400; // default 100M in kbps
                $res = $driver->addTrafficProfile($name, $speed, $speed);
                if ($res['success']) {
                    $existing = array_filter(array_map('trim', explode("\n", $olt['traffic_profiles'] ?? '')));
                    if (!in_array($name, $existing)) {
                        $existing[] = $name;
                        $oltModel->update($id, ['traffic_profiles' => implode("\n", $existing)]);
                    }
                }
            } elseif ($type === 'vlan') {
                $vlanId = $param > 0 ? $param : 100;
                $res = $driver->addVlanProfile($name, $vlanId);
                if ($res['success']) {
                    $existing = array_filter(array_map('trim', explode("\n", $olt['vlan_profiles'] ?? '')));
                    $newItem  = "{$name} — VLAN {$vlanId}";
                    if (!in_array($newItem, $existing)) {
                        $existing[] = $newItem;
                        $oltModel->update($id, ['vlan_profiles' => implode("\n", $existing)]);
                    }
                }
            }

            $driver->disconnect();
            return $this->response->setJSON($res);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Hapus profil dari OLT via Telnet & hapus dari DB.
     * POST /olts/{id}/delete-profile
     */
    public function deleteProfile(int $id)
    {
        $this->ensureVlanProfilesColumn();
        $this->response->setContentType('application/json');
        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $type = trim($this->request->getPost('type') ?? '');
        $name = trim($this->request->getPost('name') ?? '');

        if (empty($name)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Nama profil wajib diisi.']);
        }

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();

            $res = ['success' => false, 'message' => 'Tipe profil tidak valid.'];
            if ($type === 'tcont') {
                $res = $driver->deleteTcontProfile($name);
                if ($res['success']) {
                    $existing = array_filter(array_map('trim', explode("\n", $olt['tcont_profiles'] ?? '')));
                    $existing = array_values(array_filter($existing, fn($p) => $p !== $name));
                    $oltModel->update($id, ['tcont_profiles' => implode("\n", $existing)]);
                }
            } elseif ($type === 'traffic') {
                $res = $driver->deleteTrafficProfile($name);
                if ($res['success']) {
                    $existing = array_filter(array_map('trim', explode("\n", $olt['traffic_profiles'] ?? '')));
                    $existing = array_values(array_filter($existing, fn($p) => $p !== $name));
                    $oltModel->update($id, ['traffic_profiles' => implode("\n", $existing)]);
                }
            } elseif ($type === 'vlan') {
                $res = $driver->deleteVlanProfile($name);
                if ($res['success']) {
                    $existing = array_filter(array_map('trim', explode("\n", $olt['vlan_profiles'] ?? '')));
                    $existing = array_values(array_filter($existing, fn($p) => !str_starts_with($p, $name)));
                    $oltModel->update($id, ['vlan_profiles' => implode("\n", $existing)]);
                }
            }

            $driver->disconnect();
            return $this->response->setJSON($res);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getFormData(): array
    {
        return [
            'name'            => $this->request->getPost('name'),
            'ip'              => $this->request->getPost('ip'),
            'brand'           => $this->request->getPost('brand') ?: 'ZTE',
            'model'           => $this->request->getPost('model') ?: 'C320',
            'telnet_port'     => (int)($this->request->getPost('telnet_port') ?: 23),
            'telnet_user'     => $this->request->getPost('telnet_user'),
            'telnet_pass'     => $this->request->getPost('telnet_pass'),
            'enable_password' => $this->request->getPost('enable_password') ?: null,
            'snmp_community'  => $this->request->getPost('snmp_community') ?: 'public',
            'snmp_port'       => (int)($this->request->getPost('snmp_port') ?: 161),
            'acs_url'          => $this->request->getPost('acs_url') ?: null,
            'use_acs'          => (int)($this->request->getPost('use_acs') ?? 1),
            'pppoe_vlan_profile' => $this->request->getPost('pppoe_vlan_profile') ?: null,
            'firmware_version' => $this->request->getPost('firmware_version') ?: null,
            'tcont_profiles'   => $this->request->getPost('tcont_profiles') ?: null,
            'traffic_profiles' => $this->request->getPost('traffic_profiles') ?: null,
            'description'      => $this->request->getPost('description'),
        ];
    }

    private function validateForm(array $data): bool
    {
        return !empty($data['name']) && !empty($data['ip'])
            && !empty($data['telnet_user']) && !empty($data['telnet_pass']);
    }
}
