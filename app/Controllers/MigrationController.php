<?php

namespace App\Controllers;

use App\Models\OltModel;
use App\Models\OnuModel;
use App\Models\ProvisionLogModel;
use App\Libraries\Drivers\OltDriverFactory;
use App\Libraries\OnuCacheService;

class MigrationController extends BaseController
{
    private int $userId;

    public function __construct()
    {
        $this->userId = (int) session()->get('user_id');
        session_write_close();
    }

    /**
     * Halaman utama Menu Migrasi & Registrasi Massal
     */
    public function index(?int $oltId = null)
    {
        $oltModel = new OltModel();
        $olts     = $oltModel->getByUser($this->userId);

        if (empty($olts)) {
            return redirect()->to('/olts')->with('error', 'Belum ada OLT. Silakan tambahkan OLT terlebih dahulu.');
        }

        $selectedOlt = null;
        if ($oltId) {
            $selectedOlt = $oltModel->getByUserAndId($this->userId, $oltId);
        }
        if (!$selectedOlt && !empty($olts)) {
            $selectedOlt = $olts[0];
        }

        // Tipe ONU yang sudah pernah dipakai di OLT ini — jadi saran di form,
        // bukan hardcode 'ALL-ONT' yang bisa ditolak OLT.
        $onuTypes = [];
        if ($selectedOlt) {
            $existing = (new OnuModel())->getByOlt((int)$selectedOlt['id']);
            $onuTypes = array_values(array_unique(array_filter(array_column($existing, 'onu_type'))));
        }

        return view('migration/index', [
            'title'       => 'Migrasi & Registrasi Massal',
            'olts'        => $olts,
            'selectedOlt' => $selectedOlt,
            'onu_types'   => $onuTypes,
        ]);
    }

    /**
     * AJAX: Scan semua ONU unconfigured di OLT & generate nama sekuensial
     * GET /olts/{id}/migration/scan?name_scheme=scheme_1
     */
    public function scan(int $oltId)
    {
        $this->response->setContentType('application/json');

        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $oltId);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $scheme   = $this->request->getGet('name_scheme') ?? 'scheme_1';
        $prefix   = trim($this->request->getGet('custom_prefix') ?? 'User');
        $onuModel = new OnuModel();
        $cache    = new OnuCacheService();

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $uncfgOnus = $driver->getUnconfiguredOnus();
            $driver->disconnect();

            $cacheData = $cache->load($oltId);
            if (($cacheData['updated_at'] ?? null) === null) {
                $cache->save($oltId, []);
            }

            $counter = 1;
            $result  = [];

            foreach ($uncfgOnus as $onu) {
                $sn     = strtoupper($onu['sn']);
                $board  = (string)$onu['board'];
                $slot   = (string)$onu['slot'];
                $port   = (string)$onu['port'];
                $index  = $cache->nextIndex($oltId, $board, $slot, $port);

                $existing = $onuModel->getByOltAndSn($oltId, $sn);

                $vendor = 'Generic';
                if (strncasecmp($sn, 'HWTC', 4) === 0 || strncasecmp($sn, 'HWTS', 4) === 0 || strncasecmp($sn, 'HWTE', 4) === 0) {
                    $vendor = 'Huawei';
                } elseif (strncasecmp($sn, 'ZTE', 3) === 0) {
                    $vendor = 'ZTE';
                } elseif (strncasecmp($sn, 'FHTT', 4) === 0 || strncasecmp($sn, 'FHSC', 4) === 0) {
                    $vendor = 'Fiberhome';
                } elseif (strncasecmp($sn, 'ALCL', 4) === 0) {
                    $vendor = 'Nokia';
                }

                $cleanPrefix = preg_replace('/[^A-Za-z0-9_]/', '_', $prefix ?: 'Migrasi');
                $autoName = match ($scheme) {
                    'scheme_1' => sprintf('Pelanggan_%s_%s_%s_%02d', $board, $slot, $port, $index),
                    'scheme_2' => sprintf('Pelanggan_%03d', $counter),
                    'scheme_3' => sprintf('%s_%s_%02d', $cleanPrefix, strtoupper(substr($vendor, 0, 2)), $counter),
                    default    => sprintf('Pelanggan_%s_%s_%s_%02d', $board, $slot, $port, $index),
                };

                $result[] = [
                    'sn'                 => $sn,
                    'vendor'             => $vendor,
                    'board'              => $board,
                    'slot'               => $slot,
                    'port'               => $port,
                    'onu_index'          => $index,
                    'auto_name'          => $autoName,
                    'already_registered' => $existing !== null,
                    'existing_id'        => $existing['id'] ?? null,
                ];

                $counter++;
            }

            return $this->response->setJSON([
                'success' => true,
                'onus'    => $result,
                'count'   => count($result),
            ]);
        } catch (\Throwable $e) {
            log_message('error', "Migration scan error OLT {$oltId}: " . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => "Telnet Scan Gagal: " . $e->getMessage()]);
        }
    }

    /**
     * AJAX: Eksekusi registrasi masal (Pure Transparent Bridge Mode)
     * POST /olts/{id}/migration/execute
     */
    public function execute(int $oltId)
    {
        $this->response->setContentType('application/json');

        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $oltId);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $items = $this->request->getPost('items');
        if (empty($items) || !is_array($items)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Pilih minimal satu ONU untuk diregistrasi.']);
        }

        $vlanInternet   = (int) $this->request->getPost('vlan_internet');
        $vlanAcs        = (int) $this->request->getPost('vlan_acs');
        $useAcsPost     = $this->request->getPost('use_acs');
        $useAcs         = $useAcsPost !== null ? (bool)(int)$useAcsPost : (bool)($olt['use_acs'] ?? 1);
        if (!$useAcs) $vlanAcs = 0;
        $tcontProfile   = trim($this->request->getPost('tcont_profile') ?? '');
        $trafficProfile = trim($this->request->getPost('traffic_profile') ?? '');

        if (!$vlanInternet) {
            return $this->response->setJSON(['success' => false, 'message' => 'VLAN Internet (Service) wajib dipilih.']);
        }

        $onuModel = new OnuModel();
        $logModel = new ProvisionLogModel();
        $cache    = new OnuCacheService();

        $successCount = 0;
        $failCount    = 0;
        $logs         = [];

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();

            foreach ($items as $idx => $item) {
                $sn       = strtoupper(trim($item['sn'] ?? ''));
                $rawName  = trim($item['name'] ?? $sn);
                $name     = preg_replace('/[^A-Za-z0-9_]/', '_', $rawName);
                $name     = preg_replace('/_+/', '_', trim($name, '_'));
                if (empty($name)) $name = $sn;
                $board    = (string)($item['board'] ?? '1');
                $slot     = (string)($item['slot'] ?? '1');
                $port     = (string)($item['port'] ?? '1');
                $onuIndex = (int)($item['onu_index'] ?? 1);
                $onuType  = trim($item['onu_type'] ?? '');
                if ($onuType === '' || strtolower($onuType) === 'undefined') $onuType = 'ALL-ONT';

                if (empty($sn)) continue;

                $logs[] = sprintf("▶ [%d/%d] Registrasi %s (%s) di %s/%s/%s:%d...", $idx + 1, count($items), $sn, $name, $board, $slot, $port, $onuIndex);

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
                    'pppoe_user'      => '', // Kosongkan agar murni transparent bridge tanpa OMCI PPPoE
                    'pppoe_pass'      => '',
                    'acs_url'         => trim($olt['acs_url'] ?? ''),
                    'use_acs'         => $useAcs,
                    'force'           => true,
                ]);

                if ($result['success']) {
                    $existing = $onuModel->getAnyByOltAndSn($oltId, $sn);
                    if ($existing) {
                        $onuId = (int)$existing['id'];
                        $onuModel->update($onuId, [
                            'name'          => $name,
                            'board'         => $board,
                            'slot'          => $slot,
                            'port'          => $port,
                            'onu_index'     => $onuIndex,
                            'onu_type'      => $onuType,
                            'vlan_internet' => $vlanInternet,
                            'vlan_acs'      => $vlanAcs ?: null,
                            'tcont_profile' => $tcontProfile ?: null,
                            'status'        => 'registered',
                            'registered_at' => date('Y-m-d H:i:s'),
                        ]);
                    } else {
                        $insertedId = $onuModel->insert([
                            'olt_id'        => $oltId,
                            'sn'            => $sn,
                            'name'          => $name,
                            'board'         => $board,
                            'slot'          => $slot,
                            'port'          => $port,
                            'onu_index'     => $onuIndex,
                            'onu_type'      => $onuType,
                            'vlan_internet' => $vlanInternet,
                            'vlan_acs'      => $vlanAcs ?: null,
                            'tcont_profile' => $tcontProfile ?: null,
                            'status'        => 'registered',
                            'registered_at' => date('Y-m-d H:i:s'),
                        ]);
                        $onuId = (int)$insertedId;
                    }

                    $cache->addOnu($oltId, $board, $slot, $port, $onuIndex, $sn, $onuType, $name, $result['state'] ?? 'working');
                    $logModel->log($this->userId, 'mass_register', 'success', "Mass register {$sn} ({$name})", $onuId, $oltId);

                    $successCount++;
                    $logs[] = empty($result['partial'])
                        ? "  ✔ SUKSES: {$sn} ({$name}) terdaftar di {$board}/{$slot}/{$port}:{$onuIndex}"
                        : "  ⚠ TIDAK LENGKAP: {$sn} ({$name}) di {$board}/{$slot}/{$port}:{$onuIndex} — " . implode(' | ', $result['warnings'] ?? []);
                } else {
                    $failCount++;
                    $logMsg = implode(' | ', $result['log'] ?? ['Gagal']);
                    $logModel->log($this->userId, 'mass_register', 'failed', "Mass register gagal {$sn}: {$logMsg}", null, $oltId);
                    $logs[] = "  ✘ GAGAL: {$sn} → " . $logMsg;
                }
            }

            $driver->disconnect();

            return $this->response->setJSON([
                'success' => true,
                'total'   => count($items),
                'success_count' => $successCount,
                'fail_count'    => $failCount,
                'logs'          => $logs,
                'message'       => sprintf('Selesai memproses %d ONU (%d Sukses, %d Gagal).', count($items), $successCount, $failCount),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage(), 'logs' => $logs]);
        }
    }

    /**
     * AJAX: Eksekusi registrasi 1 ONU per request (Sequential Batch & Jeda untuk Keamanan OLT)
     * POST /olts/{id}/migration/execute-single
     */
    public function executeSingle(int $oltId)
    {
        $this->response->setContentType('application/json');

        $oltModel = new OltModel();
        $olt = $oltModel->getByUserAndId($this->userId, $oltId);
        if (!$olt) {
            return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);
        }

        $sn       = strtoupper(trim($this->request->getPost('sn') ?? ''));
        $rawName  = trim($this->request->getPost('name') ?? $sn);
        $name     = preg_replace('/[^A-Za-z0-9_]/', '_', $rawName);
        $name     = preg_replace('/_+/', '_', trim($name, '_'));
        if (empty($name)) $name = $sn;

        $board        = (string)($this->request->getPost('board') ?? '1');
        $slot         = (string)($this->request->getPost('slot') ?? '1');
        $port         = (string)($this->request->getPost('port') ?? '1');
        $onuIndex     = (int)($this->request->getPost('onu_index') ?? 1);
        $onuType      = trim($this->request->getPost('onu_type') ?? '');
        if ($onuType === '' || strtolower($onuType) === 'undefined') $onuType = 'ALL-ONT';
        $vlanInternet = (int) $this->request->getPost('vlan_internet');
        $vlanAcs      = (int) $this->request->getPost('vlan_acs');
        // Pakai ACS atau tidak: pilihan di form, default ikut setting OLT (kolom use_acs).
        $useAcsPost = $this->request->getPost('use_acs');
        $useAcs     = $useAcsPost !== null ? (bool)(int)$useAcsPost : (bool)($olt['use_acs'] ?? 1);
        if (!$useAcs) $vlanAcs = 0;   // tanpa ACS: jangan tulis service acs/tr069-mgmt
        $tcontProfile     = trim($this->request->getPost('tcont_profile') ?? '');
        $trafficProfile   = trim($this->request->getPost('traffic_profile') ?? '');
        $pppoeVlanProfile = trim($this->request->getPost('pppoe_vlan_profile') ?? '');

        if (empty($sn) || !$vlanInternet) {
            return $this->response->setJSON(['success' => false, 'message' => 'SN & VLAN Internet wajib diisi.']);
        }

        $onuModel = new OnuModel();
        $logModel = new ProvisionLogModel();
        $cache    = new OnuCacheService();

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
                // Transparent bridge: PPPoE tidak di-dial dari OLT — ONU dial sendiri via ACS.
                'pppoe_user'         => '',
                'pppoe_pass'         => '',
                'pppoe_vlan_profile' => $pppoeVlanProfile,
                // Tanpa acs_url, driver tidak menulis tr069-mgmt → ONU tak pernah nyampe ACS.
                'acs_url'         => trim($olt['acs_url'] ?? ''),
                'use_acs'         => $useAcs,
                'force'           => true,
            ]);

            $driver->disconnect();

            if (!empty($result['success'])) {
                $existing = $onuModel->getAnyByOltAndSn($oltId, $sn);
                if ($existing) {
                    $onuId = (int)$existing['id'];
                    $onuModel->update($onuId, [
                        'name'          => $name,
                        'board'         => $board,
                        'slot'          => $slot,
                        'port'          => $port,
                        'onu_index'     => $onuIndex,
                        'onu_type'      => $onuType,
                        'vlan_internet' => $vlanInternet,
                        'vlan_acs'      => $vlanAcs ?: null,
                        'tcont_profile' => $tcontProfile ?: null,
                        'status'        => 'registered',
                        'registered_at' => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $insertedId = $onuModel->insert([
                        'olt_id'        => $oltId,
                        'sn'            => $sn,
                        'name'          => $name,
                        'board'         => $board,
                        'slot'          => $slot,
                        'port'          => $port,
                        'onu_index'     => $onuIndex,
                        'onu_type'      => $onuType,
                        'vlan_internet' => $vlanInternet,
                        'vlan_acs'      => $vlanAcs ?: null,
                        'tcont_profile' => $tcontProfile ?: null,
                        'status'        => 'registered',
                        'registered_at' => date('Y-m-d H:i:s'),
                    ]);
                    $onuId = (int)$insertedId;
                }

                $cache->addOnu($oltId, $board, $slot, $port, $onuIndex, $sn, $onuType, $name, $result['state'] ?? 'working');

                // "Sukses tapi tidak lengkap": ONU terdaftar tapi ada perintah kritis yang gagal
                // (tcont/gemport/service/acs). Jangan dilaporkan hijau polos — nanti ketahuan
                // belakangan pas ONU tak dapat service/ACS.
                $partial  = !empty($result['partial']);
                $warnings = $result['warnings'] ?? [];
                // status ENUM('success','failed') — 'partial' ditandai lewat pesan.
                $logModel->log(
                    $this->userId, 'mass_register', 'success',
                    "Mass register {$sn} ({$name})" . ($partial ? ' — TIDAK LENGKAP: ' . implode(' | ', $warnings) : ''),
                    $onuId, $oltId
                );

                return $this->response->setJSON([
                    'success'  => true,
                    'partial'  => $partial,
                    'warnings' => $warnings,
                    'sn'       => $sn,
                    'name'     => $name,
                    'log'      => $result['log'] ?? [],
                    'message'  => $partial
                        ? "TIDAK LENGKAP: {$sn} ({$name}) terdaftar di {$board}/{$slot}/{$port}:{$onuIndex} — " . implode(' | ', $warnings)
                        : "SUKSES: {$sn} ({$name}) terdaftar di {$board}/{$slot}/{$port}:{$onuIndex}",
                ]);
            } else {
                $logMsg = implode(' | ', $result['log'] ?? ['Gagal registrasi di OLT']);
                $logModel->log($this->userId, 'mass_register', 'failed', "Mass register gagal {$sn}: {$logMsg}", null, $oltId);
                return $this->response->setJSON([
                    'success' => false,
                    'sn'      => $sn,
                    'name'    => $name,
                    'message' => "GAGAL: {$sn} → " . $logMsg,
                    'log'     => $result['log'] ?? [],
                ]);
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage() ?: get_class($e);
            log_message('error', "executeSingle exception: " . $msg);
            return $this->response->setJSON([
                'success' => false,
                'sn'      => $sn,
                'name'    => $name,
                'message' => "Exception: " . $msg
            ]);
        }
    }
}
