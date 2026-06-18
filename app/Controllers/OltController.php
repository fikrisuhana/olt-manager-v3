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

    public function show(int $id)
    {
        $oltModel = new OltModel();
        $onuModel = new OnuModel();

        $olt = $oltModel->getByUserAndId($this->userId, $id);
        if (!$olt) return redirect()->to('/olts')->with('error', 'OLT tidak ditemukan.');

        $cache = new OnuCacheService();

        return view('olt/show', [
            'title'            => $olt['name'],
            'olt'              => $olt,
            'onus'             => $onuModel->getByOlt($id),
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

            // next_index dari cache lokal (tidak tanya OLT)
            $cacheData = $cache->load($id);
            $noCacheWarning = empty($cacheData['ports']);

            foreach ($uncfgOnus as &$onu) {
                $portKey = "{$onu['board']}/{$onu['slot']}/{$onu['port']}";
                $onu['next_index']         = $cache->nextIndex($id, $onu['board'], $onu['slot'], $onu['port']);
                $onu['already_registered'] = $onuModel->snExists($id, $onu['sn']);
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
                $sn = strtoupper($onu['sn']);
                if ($onuModel->snExists($id, $sn)) {
                    $skipped++;
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
            $driver   = OltDriverFactory::make($oltConfig);
            $driver->connect();
            $profiles = $driver->getTcontProfiles();
            $driver->disconnect();

            $trafficProfiles = $driver->getTrafficProfiles();

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
            'snmp_community'  => $this->request->getPost('snmp_community') ?: 'public',
            'snmp_port'       => (int)($this->request->getPost('snmp_port') ?: 161),
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
