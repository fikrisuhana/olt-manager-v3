<?php

namespace App\Controllers;

use App\Models\OltModel;
use App\Models\OnuModel;
use App\Models\TemplateModel;
use App\Models\AcsServerModel;
use App\Models\ProvisionLogModel;
use App\Libraries\OltDriverFactory;
use App\Libraries\AcsService;
use App\Libraries\OnuCacheService;
use CodeIgniter\Controller;

class OnuController extends Controller
{
    private int $userId;

    public function __construct()
    {
        $this->userId = (int) session()->get('user_id');
        // Release session file lock so concurrent AJAX requests don't block each other
        session_write_close();
    }

    /**
     * Daftar semua ONU milik user (paginated)
     */
    public function index()
    {
        $onuModel = new OnuModel();
        $perPage  = 50;
        $page     = max(1, (int)($this->request->getGet('page') ?? 1));
        $q        = trim($this->request->getGet('q') ?? '');
        $sort     = $this->request->getGet('sort') ?? 'registered_at';
        $dir      = $this->request->getGet('dir') ?? 'DESC';
        $filter   = in_array($this->request->getGet('filter'), ['no_pppoe', 'no_acs']) ? $this->request->getGet('filter') : '';

        $onus     = $onuModel->getByUserPaginated($this->userId, $perPage, $page, $q, $sort, $dir, $filter);
        $total    = $onuModel->countByUser($this->userId, $q, $filter);
        $totalAll = $onuModel->countByUser($this->userId);

        $cache   = new OnuCacheService();
        $acsData = [];
        foreach (array_unique(array_column($onus, 'olt_id')) as $oltId) {
            foreach ($cache->loadAcs((int)$oltId)['devices'] as $sn => $info) {
                $acsData[strtoupper($sn)] = $info;
            }
        }

        return view('onu/index', [
            'title'    => 'Semua ONU',
            'onus'     => $onus,
            'acsData'  => $acsData,
            'total'    => $total,
            'totalAll' => $totalAll,
            'perPage'  => $perPage,
            'page'     => $page,
            'q'        => $q,
            'sort'     => $sort,
            'dir'      => $dir,
            'filter'   => $filter,
            'counts'   => [
                'all'      => $totalAll,
                'no_pppoe' => $onuModel->countByUser($this->userId, '', 'no_pppoe'),
                'no_acs'   => $onuModel->countByUser($this->userId, '', 'no_acs'),
            ],
        ]);
    }

    /**
     * Detail ONU
     */
    public function show(int $id)
    {
        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);

        if (!$onu || $onu['user_id'] != $this->userId) {
            return redirect()->to('/onus')->with('error', 'ONU tidak ditemukan.');
        }

        $cache    = new OnuCacheService();
        $acsCache = $cache->loadAcs($onu['olt_id']);
        $acsInfo  = $acsCache['devices'][strtoupper($onu['sn'])] ?? null;

        return view('onu/show', [
            'title'        => $onu['name'] ?? $onu['sn'],
            'onu'          => $onu,
            'acsInfo'      => $acsInfo,
            'acsUpdatedAt' => $acsCache['updated_at'],
        ]);
    }

    /**
     * AJAX: Register ONU ke OLT
     * POST /olts/{olt_id}/onu/register
     */
    public function register(int $oltId)
    {
        $this->response->setContentType('application/json');

        $oltModel      = new OltModel();
        $onuModel      = new OnuModel();
        $templateModel = new TemplateModel();
        $logModel      = new ProvisionLogModel();

        $olt = $oltModel->getByUserAndId($this->userId, $oltId);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $sn           = strtoupper(trim($this->request->getPost('sn')));
        $name         = trim($this->request->getPost('name'));
        $onuType      = trim($this->request->getPost('onu_type'));
        $board        = trim($this->request->getPost('board'));
        $slot         = trim($this->request->getPost('slot'));
        $port         = trim($this->request->getPost('port'));
        $onuIndex     = (int) $this->request->getPost('onu_index');
        $templateId   = (int) $this->request->getPost('template_id');
        $vlanInternet = (int) $this->request->getPost('vlan_internet');
        $vlanAcs      = (int) $this->request->getPost('vlan_acs');
        $tcontProfile   = trim($this->request->getPost('tcont_profile') ?? '');
        $trafficProfile = trim($this->request->getPost('traffic_profile') ?? '');
        $pppoeUser      = trim($this->request->getPost('pppoe_user') ?? '');
        $pppoePass      = trim($this->request->getPost('pppoe_pass') ?? '');
        $pppoeVlanProfile = trim($this->request->getPost('pppoe_vlan_profile') ?? '');
        $acsEnable    = $this->request->getPost('acs_enable');

        // Auto-determine index dari cache jika tidak dikirim atau 0
        if ($onuIndex <= 0) {
            $cache    = new OnuCacheService();
            $onuIndex = $cache->nextIndex($oltId, $board, $slot, $port);
        }

        if (empty($sn) || empty($name) || empty($onuType) || empty($board)) {
            return $this->response->setJSON(['success' => false, 'message' => 'SN, nama, tipe ONU wajib diisi.']);
        }

        $force       = (bool)$this->request->getPost('force');
        // Cek termasuk soft-deleted agar tidak kena unique key constraint saat INSERT
        $existingOnu = $onuModel->getAnyByOltAndSn($oltId, $sn);

        // Blok duplikat hanya jika aktif dan bukan force re-register
        if ($existingOnu && $existingOnu['status'] !== 'deleted' && !$force) {
            return $this->response->setJSON(['success' => false, 'message' => "SN {$sn} sudah terdaftar di OLT ini."]);
        }

        // Ambil script tambahan dari template (opsional)
        $template = $templateId ? $templateModel->getByUserAndId($this->userId, $templateId) : null;
        $ifExtra  = $template['gpon_onu_script'] ?? '';

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();

            $result = $driver->registerOnu([
                'board'           => $board,
                'slot'            => $slot,
                'port'            => $port,
                'onu_index'       => (string)$onuIndex,
                'onu_type'        => $onuType,
                'sn'              => $sn,
                'name'            => $name,
                'vlan_internet'   => $vlanInternet,
                'vlan_acs'        => $vlanAcs,
                'tcont_profile'   => $tcontProfile,
                'traffic_profile' => $trafficProfile,
                'gpon_onu_script' => $ifExtra,
                'pppoe_user'         => $pppoeUser,
                'pppoe_pass'         => $pppoePass,
                'pppoe_vlan_profile' => $pppoeVlanProfile,
                'acs_url'            => trim($olt['acs_url'] ?? ''),
                'force'           => $force,
            ]);

            $driver->disconnect();

            if (!$result['success']) {
                throw new \Exception(implode("\n", $result['log'] ?? ['Registrasi gagal']));
            }

            // Register bisa "sukses tapi tidak lengkap": ONU terdaftar namun ada perintah kritis
            // (tcont/gemport/service) yang gagal → ONU bisa tak dapat service/ACS. Surface ke user.
            $partial  = !empty($result['partial']);
            $warnings = $result['warnings'] ?? [];

            // Update DB jika re-register, insert jika baru
            if ($existingOnu) {
                $onuId = $existingOnu['id'];
                $onuModel->update($onuId, [
                    'name'          => $name,
                    'board'         => $board,
                    'slot'          => $slot,
                    'port'          => $port,
                    'onu_index'     => $onuIndex,
                    'onu_type'      => $onuType,
                    'vlan_internet' => $vlanInternet ?: null,
                    'vlan_acs'      => $vlanAcs ?: null,
                    'tcont_profile' => $tcontProfile ?: null,
                    'pppoe_user'    => $pppoeUser ?: null,
                    'pppoe_pass'    => $pppoePass ?: null,
                    'status'        => 'registered',
                    'registered_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $onuId = $onuModel->insert([
                    'olt_id'        => $oltId,
                    'sn'            => $sn,
                    'name'          => $name,
                    'board'         => $board,
                    'slot'          => $slot,
                    'port'          => $port,
                    'onu_index'     => $onuIndex,
                    'onu_type'      => $onuType,
                    'vlan_internet' => $vlanInternet ?: null,
                    'vlan_acs'      => $vlanAcs ?: null,
                    'tcont_profile' => $tcontProfile ?: null,
                    'pppoe_user'    => $pppoeUser ?: null,
                    'pppoe_pass'    => $pppoePass ?: null,
                    'status'        => 'registered',
                    'template_id'   => $templateId ?: null,
                    'registered_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Update cache ONU — status pakai OST aktual dari driver (fallback 'working')
            $cache = new OnuCacheService();
            $cache->addOnu($oltId, $board, $slot, $port, $onuIndex, $sn, $onuType, $name, $result['state'] ?? 'working');

            $logModel->log($this->userId, 'register', $partial ? 'warning' : 'success',
                implode(' | ', $result['log']), $onuId, $oltId);

            // Tentukan apakah PPPoE dipush via ACS (bukan di OLT) — tergantung kombinasi
            // brand OLT × brand ONU:
            //   OLT ZTE  : ZTE ONU → PPPoE di OLT (pon-onu-mng); non-ZTE (FH) → via ACS
            //   OLT FH   : FH ONU  → PPPoE di OLT (onu wan-cfg);  non-FH (ZTE) → via ACS
            $oltBrand = strtoupper($olt['brand'] ?? 'ZTE');
            $isFhOnu  = strncasecmp($sn, 'FHTT', 4) === 0 || strncasecmp($sn, 'FHSC', 4) === 0;
            $isZteOnu = strncasecmp($sn, 'ZTEG', 4) === 0;
            $pppoeViaAcs = ($oltBrand === 'FIBERHOME' || $oltBrand === 'FH') ? !$isFhOnu : !$isZteOnu;

            return $this->response->setJSON([
                'success'      => true,
                'partial'      => $partial,
                'warnings'     => $warnings,
                'message'      => $partial
                    ? "ONU {$sn} terdaftar (index {$onuIndex}) TAPI KONFIGURASI TIDAK LENGKAP — "
                        . count($warnings) . " perintah gagal (kemungkinan profil TCONT/traffic tak valid). "
                        . "ONU bisa tak dapat service/ACS. Perbaiki profil lalu Set ACS / register ulang."
                    : "ONU {$sn} berhasil didaftarkan (index {$onuIndex}).",
                'log'          => $result['log'],
                'onu_id'       => $onuId,
                'sn'           => $sn,
                'onu_index'    => $onuIndex,
                'watch_acs'    => !empty($pppoeUser) && $pppoeViaAcs,
                'push_via_acs' => !empty($pppoeUser) && $pppoeViaAcs,
            ]);
        } catch (\Exception $e) {
            $logModel->log($this->userId, 'register', 'failed', $e->getMessage(), null, $oltId);
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Update info ONU di database (nama, VLAN, TCONT, PPPoE user).
     * Tidak menyentuh OLT — hanya update record DB.
     */
    public function updateInfo(int $id)
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);
        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $data = [
            'name'          => trim($this->request->getPost('name') ?? '') ?: $onu['name'],
            'vlan_internet' => (int)($this->request->getPost('vlan_internet') ?: 0) ?: null,
            'vlan_acs'      => (int)($this->request->getPost('vlan_acs') ?: 0) ?: null,
            'tcont_profile' => trim($this->request->getPost('tcont_profile') ?? '') ?: null,
            'pppoe_user'    => trim($this->request->getPost('pppoe_user') ?? '') ?: null,
            'pppoe_pass'    => trim($this->request->getPost('pppoe_pass') ?? '') ?: null,
        ];

        $onuModel->update($id, $data);

        return $this->response->setJSON(['success' => true, 'message' => 'Info ONU berhasil disimpan.']);
    }

    /**
     * AJAX: Hapus ONU dari OLT + database
     */
    public function delete(int $id)
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);

        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $oltModel = new OltModel();
        $olt = $oltModel->find($onu['olt_id']);

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();

            // Verifikasi SN di OLT sebelum delete — cegah hapus ONU lain yang kebetulan di slot sama
            $snOnOlt = $driver->getSnAtIndex($onu['board'], $onu['slot'], $onu['port'], $onu['onu_index']);
            if ($snOnOlt !== null && strtoupper($snOnOlt) !== strtoupper($onu['sn'])) {
                $driver->disconnect();
                // Slot diisi ONU lain — hanya hapus dari DB, jangan sentuh OLT
                $onuModel->update($id, ['status' => 'deleted']);
                $logModel = new ProvisionLogModel();
                $logModel->log($this->userId, 'delete', 'success',
                    "ONU {$onu['sn']} dihapus dari DB saja (slot {$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']} di OLT berisi SN lain: {$snOnOlt})",
                    $id, $onu['olt_id']);
                return $this->response->setJSON(['success' => true,
                    'message' => "ONU {$onu['sn']} dihapus dari DB. (Slot di OLT berisi ONU lain, tidak disentuh.)"]);
            }

            $driver->deleteOnu($onu['board'], $onu['slot'], $onu['port'], $onu['onu_index']);
            $driver->disconnect();

            $onuModel->update($id, ['status' => 'deleted']);

            $cache = new OnuCacheService();
            $cache->removeOnu($onu['olt_id'], $onu['board'], $onu['slot'], $onu['port'], (int)$onu['onu_index']);

            $logModel = new ProvisionLogModel();
            $logModel->log($this->userId, 'delete', 'success', "ONU {$onu['sn']} dihapus", $id, $onu['olt_id']);

            return $this->response->setJSON(['success' => true, 'message' => "ONU {$onu['sn']} berhasil dihapus."]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ambil sinyal RX/TX ONU dari OLT
     */
    public function signal(int $id)
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);
        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $oltModel = new OltModel();
        $olt = $oltModel->find($onu['olt_id']);

        // OLT Fiberhome (AN6000): CLI tidak menyediakan sinyal per-ONU yang andal
        // (`show onu optical` ambiguous di firmware ini). RX dibaca dari ACS
        // (VirtualParameters.RXPower) — ONU FH memang dikelola via ACS/TR-069.
        if (in_array(strtoupper($olt['brand'] ?? ''), ['FIBERHOME', 'FH'])) {
            $acsModel = new AcsServerModel();
            $acs = $acsModel->getDefault($this->userId);
            if (!$acs) {
                return $this->response->setJSON(['success' => false, 'message' => 'Sinyal FH dibaca dari ACS, tapi tidak ada ACS server default.']);
            }
            try {
                $acsService = new AcsService($acs);
                $deviceId   = $onu['acs_device_id'];
                if (!$deviceId) {
                    $device = $acsService->findDeviceBySn($onu['sn']);
                    if (!$device) {
                        return $this->response->setJSON(['success' => false, 'message' => 'ONU belum terdaftar di ACS — sinyal belum bisa dibaca.']);
                    }
                    $deviceId = $device['_id'];
                    $onuModel->update($id, ['acs_device_id' => $deviceId]);
                }
                $rx = $acsService->getRxPower($deviceId);
                if (!$rx || ($rx['onu_rx'] ?? null) === null) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Data sinyal optik belum tersedia di ACS untuk ONU ini.']);
                }
                $onuRx   = (float)$rx['onu_rx'];
                $quality = 'unknown';
                if ($onuRx !== 0.0) {
                    if ($onuRx >= -25) $quality = 'good';
                    elseif ($onuRx >= -28) $quality = 'warn';
                    else $quality = 'bad';
                }
                return $this->response->setJSON([
                    'success' => true,
                    'source'  => 'acs',
                    'signal'  => ['onu_rx' => $rx['onu_rx'], 'olt_rx' => null, 'onu_tx' => null, 'olt_tx' => null, 'temperature' => $rx['temperature'] ?? null],
                    'quality' => $quality,
                    'label'   => "ONU-RX: {$rx['onu_rx']} dBm (dari ACS)",
                ]);
            } catch (\Exception $e) {
                return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
            }
        }

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $signal = $driver->getOnuSignal($onu['board'], $onu['slot'], $onu['port'], $onu['onu_index']);
            $driver->disconnect();

            // Tentukan kualitas sinyal berdasarkan ONU RX (sinyal di pelanggan)
            $onuRx = (float)($signal['onu_rx'] ?? 0);
            $quality = 'unknown';
            if ($onuRx !== 0.0) {
                if ($onuRx >= -25) $quality = 'good';
                elseif ($onuRx >= -28) $quality = 'warn';
                else $quality = 'bad';
            }

            return $this->response->setJSON([
                'success' => true,
                'signal'  => $signal,
                'quality' => $quality,
                'label'   => "OLT-RX: {$signal['olt_rx']} | ONU-RX: {$signal['onu_rx']} dBm",
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Cek status WAN/DHCP ONU dari OLT (dapat IP atau tidak).
     * Khusus driver yang punya getWanInfo (Fiberhome). Untuk brand lain → not_supported.
     */
    public function wanInfo(int $id)
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);
        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $oltModel = new OltModel();
        $olt = $oltModel->find($onu['olt_id']);

        try {
            $driver = OltDriverFactory::make($olt);
            if (!method_exists($driver, 'getWanInfo')) {
                return $this->response->setJSON(['success' => false, 'message' => 'Cek DHCP hanya untuk OLT Fiberhome.']);
            }
            $driver->connect();
            $info = $driver->getWanInfo($onu['board'], $onu['slot'], $onu['port'], (string)$onu['onu_index']);
            $driver->disconnect();

            return $this->response->setJSON(['success' => true, 'info' => $info]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ambil info lengkap ONU dari GenieACS (WAN, WiFi, status)
     */
    public function acsInfo(int $id)
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);
        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $acsModel = new AcsServerModel();
        $acs      = $acsModel->getDefault($this->userId);
        if (!$acs) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada ACS server default.']);
        }

        try {
            $acsService = new AcsService($acs);

            // Cari device untuk mendapatkan device_id dan manufacturer
            $device   = $acsService->findDeviceBySn($onu['sn']);
            if (!$device) {
                return $this->response->setJSON(['success' => false, 'message' => 'Device belum terdaftar di ACS.']);
            }
            $deviceId = $device['_id'];

            // Cache device_id ke DB jika belum tersimpan
            if (empty($onu['acs_device_id'])) {
                $onuModel->update($id, ['acs_device_id' => $deviceId]);
            }

            // Deteksi brand dari manufacturer ACS (bukan brand OLT)
            $brand = $acsService->getDeviceBrand($device);
            $info  = $acsService->getDeviceInfo($deviceId, $brand);

            return $this->response->setJSON([
                'success'   => true,
                'device_id' => $deviceId,
                'info'      => $info,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Push setting WiFi / PPPoE ke ONU via ACS
     * POST /onus/{id}/acs-set
     */
    public function acsSet(int $id)
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);
        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $oltModel  = new OltModel();
        $olt       = $oltModel->find($onu['olt_id']);
        // PPPoE dipush via OLT (OMCI langsung) hanya bila brand OLT & ONU cocok:
        //   OLT ZTE + ONU ZTE (ZTEG)  → pon-onu-mng
        //   OLT FH  + ONU FH  (FHTT)  → onu wan-cfg
        // Selain itu (brand ONU beda dari OLT) → PPPoE via ACS/TR-069.
        $oltBrand    = strtoupper($olt['brand'] ?? 'ZTE');
        $isZteOnu    = strncasecmp($onu['sn'] ?? '', 'ZTEG', 4) === 0;
        $isFhOnu     = strncasecmp($onu['sn'] ?? '', 'FHTT', 4) === 0 || strncasecmp($onu['sn'] ?? '', 'FHSC', 4) === 0;
        $pppoeViaOlt = ($oltBrand === 'ZTE' && $isZteOnu)
                    || (($oltBrand === 'FIBERHOME' || $oltBrand === 'FH') && $isFhOnu);

        $action = $this->request->getPost('action'); // 'pppoe' | 'wifi' | 'reboot'

        // PPPoE via OLT (OMCI) — set VLAN + PPPoE sekaligus
        if ($action === 'pppoe' && $pppoeViaOlt) {
            $pppoeUser    = trim($this->request->getPost('pppoe_user'));
            $pppoePass    = trim($this->request->getPost('pppoe_pass'));
            $vlanAcs      = (int)($onu['vlan_acs'] ?? 0);
            $vlanInternet = (int)($onu['vlan_internet'] ?? 0);
            $acsUrl       = trim($olt['acs_url'] ?? '');

            if (!$vlanAcs && !$vlanInternet) {
                return $this->response->setJSON(['success' => false, 'message' => 'VLAN belum diset di ONU ini. Edit Info ONU → isi VLAN Internet/ACS.']);
            }

            try {
                $driver = OltDriverFactory::make($olt);
                $driver->connect();
                $result = $driver->applyPonMng(
                    $onu['board'], $onu['slot'], $onu['port'], (string)$onu['onu_index'],
                    $vlanAcs, $acsUrl, $vlanInternet, $pppoeUser, $pppoePass
                );
                $driver->disconnect();

                if ($result['success']) {
                    $onuModel->update($id, ['pppoe_user' => $pppoeUser, 'pppoe_pass' => $pppoePass ?: null]);
                }

                $oltMech = ($oltBrand === 'FIBERHOME' || $oltBrand === 'FH') ? 'onu wan-cfg' : 'pon-onu-mng';
                $logModel = new ProvisionLogModel();
                $logModel->log($this->userId, 'olt_pppoe', 'success',
                    "{$oltMech} PPPoE user={$pppoeUser} vlan_internet={$vlanInternet} vlan_acs={$vlanAcs}", $id, $onu['olt_id']);

                return $this->response->setJSON([
                    'success'  => true,
                    'message'  => "PPPoE berhasil dipush ke ONU via OLT ({$oltMech}).",
                    'wan_path' => "OLT {$oltMech}",
                    'log'      => $result['log'],
                ]);
            } catch (\Exception $e) {
                return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
            }
        }

        // Non-ZTE OLT atau action selain pppoe: gunakan ACS (TR-069)
        $acsModel = new AcsServerModel();
        $acs      = $acsModel->getDefault($this->userId);
        if (!$acs) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada ACS server default.']);
        }

        try {
            $acsService = new AcsService($acs);
            $deviceId   = $onu['acs_device_id'];
            $device     = null;

            if (!$deviceId) {
                $device   = $acsService->findDeviceBySn($onu['sn']);
                if (!$device) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Device belum terdaftar di ACS.']);
                }
                $deviceId = $device['_id'];
                $onuModel->update($id, ['acs_device_id' => $deviceId]);
            }

            $logModel = new ProvisionLogModel();

            if ($action === 'pppoe') {
                $pppoeUser = trim($this->request->getPost('pppoe_user'));
                $pppoePass = trim($this->request->getPost('pppoe_pass'));
                $device    = $acsService->findDeviceBySn($onu['sn']);
                $brand     = $device ? $acsService->getDeviceBrand($device) : 'default';
                $result    = $acsService->provisionPppoe($deviceId, $pppoeUser, $pppoePass, $brand, [
                    'vlan_internet' => (int)($onu['vlan_internet'] ?? 0),
                ]);

                if ($result['success']) {
                    $onuModel->update($id, ['pppoe_user' => $pppoeUser, 'pppoe_pass' => $pppoePass ?: null]);
                }

                $logModel->log($this->userId, 'acs_pppoe', $result['success'] ? 'success' : 'failed',
                    "PPPoE user={$pppoeUser} path={$result['wan_path']}", $id, $onu['olt_id']);

                return $this->response->setJSON(array_merge(['success' => $result['success']], $result));

            } elseif ($action === 'wifi') {
                $ssid    = trim($this->request->getPost('ssid'));
                $wifiKey = trim($this->request->getPost('wifi_key'));
                $band    = $this->request->getPost('band') ?: ($this->request->getPost('dual_band') ? 'both' : '24');
                $bands   = match ($band) { '5' => [5], '24' => [1], default => [1, 5] };
                $device  = $device ?? $acsService->findDeviceBySn($onu['sn']);
                $brand   = $device ? $acsService->getDeviceBrand($device) : 'default';
                $result  = $acsService->setWifi($deviceId, $ssid, $wifiKey, $bands, $brand);

                $logModel->log($this->userId, 'acs_wifi', $result['success'] ? 'success' : 'failed',
                    "WiFi SSID={$ssid}", $id, $onu['olt_id']);

                return $this->response->setJSON($result);

            } elseif ($action === 'reboot') {
                $ok = $acsService->rebootDevice($deviceId);
                $logModel->log($this->userId, 'acs_reboot', $ok ? 'success' : 'failed', "Reboot ONU {$onu['sn']}", $id, $onu['olt_id']);
                return $this->response->setJSON(['success' => $ok]);
            }

            return $this->response->setJSON(['success' => false, 'message' => 'Action tidak dikenali.']);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ambil konfigurasi ONU aktif dari OLT (VLAN, TCONT, traffic-limit).
     * Untuk ZTE: parse running-config via Telnet.
     * Untuk brand lain: kembalikan WAN info dari ACS cache sebagai fallback.
     */
    public function fetchConfig(int $id)
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);
        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $oltModel = new OltModel();
        $olt = $oltModel->find($onu['olt_id']);

        // Non-ZTE: fetch WAN VLAN dari ACS live (atau cache)
        if (strtoupper($olt['brand'] ?? '') !== 'ZTE') {
            $acsModel = new AcsServerModel();
            $acs      = $acsModel->getDefault($this->userId);
            $wanVlan  = 0;
            $source   = 'db';
            if ($acs) {
                try {
                    $acsService = new AcsService($acs);
                    $device     = $acsService->findDeviceBySn($onu['sn']);
                    if ($device) {
                        $brand  = $acsService->getDeviceBrand($device);
                        $info   = $acsService->getDeviceInfo($device['_id'], $brand);
                        $wanVlan = (int)($info['wan']['vlan'] ?? 0);
                        $source  = 'acs';
                    }
                } catch (\Exception $e) { /* fallback ke DB */ }
            }
            return $this->response->setJSON([
                'success' => true,
                'source'  => $source,
                'config'  => [
                    'tcont_profile'   => $onu['tcont_profile'] ?? '',
                    'traffic_profile' => '',
                    'vlan_internet'   => $wanVlan ?: ($onu['vlan_internet'] ?? 0),
                    'vlan_acs'        => $onu['vlan_acs'] ?? 0,
                    'service_ports'   => [],
                ],
                'note' => "Brand {$olt['brand']}: VLAN diambil dari ACS" . ($wanVlan ? " (VLAN {$wanVlan})" : " (tidak ditemukan, pakai data DB)"),
            ]);
        }

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();

            $config    = $driver->getOnuConfig($onu['board'], $onu['slot'], $onu['port'], $onu['onu_index']);
            $ponMng    = method_exists($driver, 'getPonMngConfig')
                ? $driver->getPonMngConfig($onu['board'], $onu['slot'], $onu['port'], $onu['onu_index'])
                : null;

            $driver->disconnect();

            $pppoeUser = $ponMng['pppoe_user'] ?? null;
            $pppoePass = $ponMng['pppoe_pass'] ?? null;

            // Fallback PPPoE dari ACS bila OLT tak menyediakan. Di ZTE firmware v2.1 tidak ada
            // command per-ONU untuk baca pon-onu-mng (`show running-config pon-onu-mng` invalid),
            // jadi PPPoE diambil dari ACS (VirtualParameters.pppoeUsername) — robust lintas versi/brand.
            $pppoeSource = $pppoeUser ? 'olt' : 'db';
            if (!$pppoeUser) {
                $acsModel = new AcsServerModel();
                $acs = $acsModel->getDefault($this->userId);
                if ($acs) {
                    try {
                        $acsService = new AcsService($acs);
                        $device = $acsService->findDeviceBySn($onu['sn']);
                        if ($device) {
                            $info = $acsService->getDeviceInfo($device['_id'], $acsService->getDeviceBrand($device));
                            if (!empty($info['wan']['pppoe_user'])) {
                                $pppoeUser   = $info['wan']['pppoe_user'];
                                $pppoePass   = $info['wan']['pppoe_pass'] ?: $pppoePass;
                                $pppoeSource = 'acs';
                            }
                        }
                    } catch (\Exception $e) { /* fallback gagal, biarkan kosong */ }
                }
            }

            // Simpan ke DB jika ditemukan dan DB masih kosong
            $dbUpdate = [];
            if ($pppoeUser && empty($onu['pppoe_user'])) $dbUpdate['pppoe_user'] = $pppoeUser;
            if ($pppoePass && empty($onu['pppoe_pass'])) $dbUpdate['pppoe_pass'] = $pppoePass;
            if ($dbUpdate) $onuModel->update($id, $dbUpdate);

            return $this->response->setJSON([
                'success'      => true,
                'source'       => 'olt',
                'config'       => $config,
                'pon_mng'      => $ponMng,
                'pppoe_user'   => $pppoeUser,
                'pppoe_pass'   => $pppoePass,
                'pppoe_source' => $pppoeSource,
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ambil nama satu ONU langsung dari OLT via running-config
     */
    public function syncName(int $id)
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);
        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $oltModel = new OltModel();
        $olt = $oltModel->find($onu['olt_id']);

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $config = $driver->getOnuConfig($onu['board'], $onu['slot'], $onu['port'], $onu['onu_index']);
            $driver->disconnect();

            $name = $config['name'] ?? null;
            if (!$name) {
                return $this->response->setJSON(['success' => false, 'message' => 'Nama tidak ditemukan di running-config OLT.']);
            }

            $onuModel->update($id, ['name' => $name]);
            return $this->response->setJSON(['success' => true, 'name' => $name]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Sync nama semua ONU dari OLT langsung (bulk).
     * Konek 1x per OLT, baca running-config per ONU, update DB.
     * Hanya update ONU yang namanya masih sama dengan SN.
     */
    public function syncAllNames()
    {
        $this->response->setContentType('application/json');
        set_time_limit(600);

        $onuModel = new OnuModel();
        $oltModel = new OltModel();
        $onus     = $onuModel->getByUser($this->userId);

        // Group by OLT agar 1 koneksi per OLT
        $byOlt = [];
        foreach ($onus as $onu) {
            $byOlt[$onu['olt_id']][] = $onu;
        }

        $updated = 0;
        $errors  = [];

        foreach ($byOlt as $oltId => $oltOnus) {
            $olt = $oltModel->find($oltId);
            if (!$olt) continue;

            try {
                $driver = OltDriverFactory::make($olt);
                $driver->connect();

                foreach ($oltOnus as $onu) {
                    $config = $driver->getOnuConfig(
                        $onu['board'], $onu['slot'], $onu['port'], $onu['onu_index']
                    );
                    if (!empty($config['name'])) {
                        $onuModel->update($onu['id'], ['name' => $config['name']]);
                        $updated++;
                    }
                }

                $driver->disconnect();
            } catch (\Exception $e) {
                $errors[] = "OLT {$oltId}: " . $e->getMessage();
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'updated' => $updated,
            'errors'  => $errors,
        ]);
    }

    /**
     * AJAX: Sync PPPoE username semua ONU dari sumbernya ke DB.
     * ZTE → baca dari pon-onu-mng running-config OLT.
     * FH/lainnya → baca dari ACS cache.
     */
    public function syncPppoeAll()
    {
        $this->response->setContentType('application/json');

        $onuModel = new OnuModel();
        $cache    = new OnuCacheService();
        $onus     = $onuModel->getByUser($this->userId);

        // Semua brand (ZTE, FH, Nokia, Huawei) — ambil dari ACS cache
        $acsData = [];
        foreach (array_unique(array_column($onus, 'olt_id')) as $oltId) {
            foreach ($cache->loadAcs((int)$oltId)['devices'] as $sn => $info) {
                $acsData[strtoupper($sn)] = $info;
            }
        }

        $updated = 0;
        foreach ($onus as $onu) {
            $pppoeUser = $acsData[strtoupper($onu['sn'])]['pppoe_user'] ?? null;
            if ($pppoeUser && $pppoeUser !== ($onu['pppoe_user'] ?? '')) {
                $onuModel->update($onu['id'], ['pppoe_user' => $pppoeUser]);
                $updated++;
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'updated' => $updated,
            'message' => "{$updated} PPPoE username berhasil disinkronisasi ke database.",
        ]);
    }

    /**
     * AJAX: Bulk push pon-onu-mng (ACS management only) ke semua ZTE ONU
     * yang belum terdaftar di ACS. Hanya push vlan_acs + wan-ip 2 dhcp.
     * wan-ip 1 (PPPoE) tidak disentuh — biarkan konfigurasi existing di ONU.
     */
    public function setAcsAll()
    {
        $this->response->setContentType('application/json');
        set_time_limit(600);

        $onuModel = new OnuModel();
        $oltModel = new OltModel();
        $cache    = new OnuCacheService();

        $onus = $onuModel->getByUser($this->userId);

        // Cari ONU yang belum di ACS cache (acs_device_id kosong)
        // Muat ACS cache untuk tahu mana yang sudah terdaftar
        $acsSnSet = [];
        foreach (array_unique(array_column($onus, 'olt_id')) as $oltId) {
            foreach ($cache->loadAcs((int)$oltId)['devices'] as $sn => $_) {
                $acsSnSet[strtoupper($sn)] = true;
            }
        }

        // Filter: ZTE ONU, belum di ACS cache, punya vlan_acs
        $targets = array_filter($onus, fn($o) =>
            strncasecmp($o['sn'], 'ZTEG', 4) === 0 &&
            !isset($acsSnSet[strtoupper($o['sn'])]) &&
            !empty($o['vlan_acs'])
        );

        if (empty($targets)) {
            return $this->response->setJSON(['success' => true, 'pushed' => 0, 'errors' => [], 'message' => 'Tidak ada ZTE ONU yang perlu di-push ACS.']);
        }

        // Group by OLT agar 1 koneksi per OLT
        $byOlt = [];
        foreach ($targets as $onu) {
            $byOlt[$onu['olt_id']][] = $onu;
        }

        $pushed = 0;
        $errors = [];

        foreach ($byOlt as $oltId => $oltOnus) {
            $olt    = $oltModel->find($oltId);
            $acsUrl = trim($olt['acs_url'] ?? '');
            if (!$olt || !$acsUrl) {
                $errors[] = "OLT {$oltId}: ACS URL belum diset.";
                continue;
            }
            try {
                $driver = OltDriverFactory::make($olt);
                $driver->connect();
                foreach ($oltOnus as $onu) {
                    // vlanInternet=0: skip internet service, jangan sentuh wan-ip 1
                    $driver->applyPonMng(
                        $onu['board'], $onu['slot'], $onu['port'], $onu['onu_index'],
                        (int)$onu['vlan_acs'], $acsUrl, 0
                    );
                    $pushed++;
                }
                $driver->disconnect();
            } catch (\Exception $e) {
                $errors[] = "OLT {$olt['name']}: " . $e->getMessage();
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'pushed'  => $pushed,
            'errors'  => $errors,
            'message' => "{$pushed} ONU berhasil di-push ACS management." . (count($errors) ? ' ' . count($errors) . ' error.' : ''),
        ]);
    }

    public function setAcs(int $id)
    {
        $onuModel = new OnuModel();
        $onu = $onuModel->getWithOlt($id);
        if (!$onu || $onu['user_id'] != $this->userId) {
            return $this->response->setJSON(['success' => false, 'message' => 'ONU tidak ditemukan.']);
        }

        $oltModel = new OltModel();
        $olt = $oltModel->find($onu['olt_id']);

        $acsUrl  = trim($olt['acs_url'] ?? '');
        $vlanAcs = (int)($onu['vlan_acs'] ?? 0);

        if (!$acsUrl) {
            return $this->response->setJSON(['success' => false, 'message' => 'ACS URL belum diset di konfigurasi OLT. Edit OLT → isi ACS URL.']);
        }
        if (!$vlanAcs) {
            return $this->response->setJSON(['success' => false, 'message' => 'VLAN ACS belum diset di ONU ini. Edit info ONU → isi VLAN ACS.']);
        }

        $vlanInternet = (int)($onu['vlan_internet'] ?? 0);

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $result = $driver->applyPonMng(
                $onu['board'], $onu['slot'], $onu['port'], $onu['onu_index'],
                $vlanAcs, $acsUrl, $vlanInternet
            );
            $driver->disconnect();
            return $this->response->setJSON([
                'success' => true,
                'message' => 'pon-onu-mng berhasil dipush. ONU akan konek ke ACS — push PPPoE dari halaman ini setelah online.',
                'log'     => $result['log'],
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function tryAcsProvision(string $sn, string $pppoeUser, string $pppoePass, string $oltBrand = 'ZTE'): ?array
    {
        try {
            $acsModel = new AcsServerModel();
            $acs = $acsModel->getDefault($this->userId);
            if (!$acs) return ['status' => 'skip', 'message' => 'Tidak ada ACS server default'];

            $acsService = new AcsService($acs);

            // Cari device di GenieACS berdasarkan SN
            $device = $acsService->findDeviceBySn($sn);
            if (!$device) {
                return ['status' => 'not_found', 'message' => 'Device belum terkoneksi ke ACS'];
            }

            $deviceId    = $device['_id'];
            $deviceBrand = $acsService->getDeviceBrand($device);

            // Push PPPoE credentials
            $result = $acsService->provisionPppoe($deviceId, $pppoeUser, $pppoePass, $deviceBrand);

            $logModel = new ProvisionLogModel();
            $logModel->log(
                $this->userId, 'acs_provision',
                $result['success'] ? 'success' : 'failed',
                "ACS PPPoE provision sn={$sn} user={$pppoeUser} brand={$deviceBrand} path={$result['wan_path']}"
            );

            return array_merge($result, ['device_id' => $deviceId, 'brand' => $deviceBrand]);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
