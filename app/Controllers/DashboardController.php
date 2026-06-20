<?php

namespace App\Controllers;

use App\Models\OltModel;
use App\Models\OnuModel;
use App\Models\AcsServerModel;
use App\Models\TemplateModel;
use App\Models\ProvisionLogModel;
use App\Libraries\OnuCacheService;
use App\Libraries\AcsService;
use CodeIgniter\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = session()->get('user_id');

        $oltModel      = new OltModel();
        $onuModel      = new OnuModel();
        $templateModel = new TemplateModel();
        $logModel      = new ProvisionLogModel();

        $olts  = $oltModel->getByUser($userId);
        $onus  = $onuModel->getByUser($userId);
        $cache = new OnuCacheService();

        // Build per-OLT ACS stats
        $acsById = [];  // [olt_id => ['total'=>n, 'online'=>n, 'devices'=>[sn=>info]]]
        foreach ($olts as $olt) {
            $acsData = $cache->loadAcs($olt['id']);
            $total  = 0;
            $online = 0;
            foreach ($acsData['devices'] as $info) {
                $total++;
                if ($info['online']) $online++;
            }
            $acsById[$olt['id']] = [
                'total'   => $total,
                'online'  => $online,
                'devices' => $acsData['devices'],
            ];
        }

        // Compute ONU stats
        $noPppoe   = 0;
        $noAcs     = 0;
        $brands    = [];
        $onuByOlt  = [];   // [olt_id => count]

        foreach ($onus as $onu) {
            if (empty($onu['pppoe_user'])) $noPppoe++;
            if (empty($onu['acs_device_id'])) $noAcs++;

            // Deteksi brand dari SN prefix
            $sn = strtoupper($onu['sn'] ?? '');
            if      (str_starts_with($sn, 'ZTEG'))  $brand = 'ZTE';
            elseif  (str_starts_with($sn, 'FHTT'))  $brand = 'Fiberhome';
            elseif  (str_starts_with($sn, 'ALCL'))  $brand = 'Nokia';
            elseif  (str_starts_with($sn, 'HWTC'))  $brand = 'Huawei';
            else                                     $brand = 'Lainnya';
            $brands[$brand] = ($brands[$brand] ?? 0) + 1;

            $onuByOlt[$onu['olt_id']] = ($onuByOlt[$onu['olt_id']] ?? 0) + 1;
        }

        arsort($brands);

        // Per-OLT enriched data
        $oltStats = [];
        foreach ($olts as $olt) {
            $acs          = $acsById[$olt['id']] ?? ['total' => 0, 'online' => 0];
            $oltStats[]   = [
                'olt'         => $olt,
                'onu_count'   => $onuByOlt[$olt['id']] ?? 0,
                'acs_total'   => $acs['total'],
                'acs_online'  => $acs['online'],
                'acs_offline' => $acs['total'] - $acs['online'],
            ];
        }

        $acsTotal   = array_sum(array_column($oltStats, 'acs_total'));
        $onlineOnus = array_sum(array_column($oltStats, 'acs_online'));

        $data = [
            'title'           => 'Dashboard',
            'total_olts'      => count($olts),
            'total_onus'      => count($onus),
            'total_templates' => count($templateModel->getByUser($userId)),
            'recent_logs'     => $logModel->getByUser($userId, 8),
            'online_onus'     => $onlineOnus,
            'acs_total'       => $acsTotal,
            'no_pppoe'        => $noPppoe,
            'no_acs'          => $noAcs,
            'brands'          => $brands,
            'olt_stats'       => $oltStats,
        ];

        return view('dashboard/index', $data);
    }

    /**
     * Cron endpoint: auto-provision PPPoE untuk ONU non-ZTE yang baru muncul di ACS.
     * Dipanggil via curl dari crontab — tidak perlu session/login.
     * Token di-set via CRON_TOKEN di .env.
     */
    public function cronProvision(string $token): void
    {
        $expected = env('CRON_TOKEN', '');
        if (!$expected || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $acsModel = new AcsServerModel();
        $oltModel = new OltModel();
        $onuModel = new OnuModel();
        $cache    = new OnuCacheService();

        $db      = \Config\Database::connect();
        $userIds = $db->table('acs_servers')
                      ->select('user_id')
                      ->where('is_default', 1)
                      ->distinct()
                      ->get()->getResultArray();

        $totalPushed = 0;
        $totalErrors = 0;
        $log         = [];

        foreach ($userIds as $row) {
            $userId = (int) $row['user_id'];
            $acs    = $acsModel->getDefault($userId);
            if (!$acs) continue;

            $olts = $oltModel->getByUser($userId);
            if (empty($olts)) continue;

            try {
                $acsService = new AcsService($acs);
            } catch (\Exception $e) {
                $log[] = "User {$userId}: gagal init ACS — " . $e->getMessage();
                $totalErrors++;
                continue;
            }

            foreach ($olts as $olt) {
                $onus = $onuModel->getByOlt($olt['id']);
                if (empty($onus)) continue;

                // Hanya ONU non-ZTE, belum di ACS, sudah punya credentials
                // ONU yang sudah acs_device_id-nya diisi → skip total (tidak query ACS)
                $pending = array_filter($onus, fn($o) =>
                    empty($o['acs_device_id']) &&
                    !empty($o['pppoe_user']) &&
                    !empty($o['pppoe_pass']) &&
                    strncasecmp($o['sn'], 'ZTEG', 4) !== 0
                );

                if (empty($pending)) continue;

                $onuBySn = [];
                foreach ($pending as $o) {
                    $onuBySn[strtoupper($o['sn'])] = $o;
                }

                $sns = array_map(fn($o) => strtoupper($o['sn']), $pending);

                try {
                    $acsData = $acsService->getDevicesBySns($sns);
                } catch (\Exception $e) {
                    $log[] = "OLT {$olt['name']}: gagal query ACS — " . $e->getMessage();
                    $totalErrors++;
                    continue;
                }

                // Tidak update cache di sini — biarkan syncAcs() yang handle cache dashboard

                foreach ($acsData as $sn => $info) {
                    $onu = $onuBySn[strtoupper($sn)] ?? null;
                    if (!$onu) continue;

                    $isZte    = strncasecmp($onu['sn'], 'ZTEG', 4) === 0;
                    $wasInAcs = !empty($onu['acs_device_id']);
                    $deviceId = $info['device_id'] ?? null;

                    if (!$wasInAcs && $deviceId) {
                        $onuModel->update($onu['id'], ['acs_device_id' => $deviceId]);

                        if ($isZte || empty($onu['pppoe_user']) || empty($onu['pppoe_pass'])) {
                            continue;
                        }

                        $mfr   = strtolower($info['manufacturer'] ?? '');
                        $brand = (str_contains($mfr, 'fiber') || str_contains($mfr, 'fh'))
                               ? 'fiberhome'
                               : (str_contains($mfr, 'huawei') ? 'huawei' : 'default');

                        try {
                            $acsService->queueProvisionPppoe(
                                $deviceId,
                                $onu['pppoe_user'],
                                $onu['pppoe_pass'],
                                $brand,
                                ['vlan_internet' => (int)($onu['vlan_internet'] ?? 0)]
                            );
                            $log[] = "PUSHED: {$sn} ({$brand})";
                            $totalPushed++;
                        } catch (\Exception $e) {
                            $log[] = "ERROR {$sn}: " . $e->getMessage();
                            $totalErrors++;
                        }
                    }
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'pushed' => $totalPushed,
            'errors' => $totalErrors,
            'log'    => $log,
            'time'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * AJAX: sync ACS cache untuk semua OLT milik user.
     * Hanya query GenieACS (tidak konek OLT via Telnet) — cepat.
     */
    public function syncAcs()
    {
        $this->response->setContentType('application/json');
        $userId = session()->get('user_id');

        $acsModel = new AcsServerModel();
        $acs      = $acsModel->getDefault($userId);
        if (!$acs) {
            return $this->response->setJSON(['success' => false, 'message' => 'Tidak ada ACS server default.']);
        }

        $oltModel = new OltModel();
        $onuModel = new OnuModel();
        $cache    = new OnuCacheService();
        $olts     = $oltModel->getByUser($userId);

        $totalOnline  = 0;
        $totalSynced  = 0;
        $errors       = [];
        $pppoeUpdated = 0;
        $autoPushed   = 0;

        try {
            $acsService = new AcsService($acs);

            foreach ($olts as $olt) {
                $onus = $onuModel->getByOlt($olt['id']);
                if (empty($onus)) continue;

                // Index ONU by SN untuk update pppoe_user
                $onuBySn = [];
                foreach ($onus as $o) {
                    $onuBySn[strtoupper($o['sn'])] = $o;
                }

                $sns     = array_map(fn($o) => strtoupper($o['sn']), $onus);
                $acsData = $acsService->getDevicesBySns($sns);
                $cache->saveAcs($olt['id'], $acsData);

                $online       = count(array_filter($acsData, fn($d) => $d['online']));
                $totalOnline += $online;
                $totalSynced += count($acsData);

                foreach ($acsData as $sn => $info) {
                    $onu = $onuBySn[strtoupper($sn)] ?? null;
                    if (!$onu) continue;

                    $isZte      = strncasecmp($onu['sn'], 'ZTEG', 4) === 0;
                    $wasInAcs   = !empty($onu['acs_device_id']);   // sudah pernah diproses ACS?
                    $deviceId   = $info['device_id'] ?? null;

                    // ACS punya PPPoE tapi DB belum → simpan ke DB
                    if (!empty($info['pppoe_user']) && empty($onu['pppoe_user'])) {
                        $onuModel->update($onu['id'], ['pppoe_user' => $info['pppoe_user']]);
                        $pppoeUpdated++;
                    }

                    // ONU baru pertama kali muncul di ACS (acs_device_id masih kosong di DB)
                    if (!$wasInAcs && $deviceId) {
                        // Simpan device_id agar next sync tidak push lagi
                        $onuModel->update($onu['id'], ['acs_device_id' => $deviceId]);

                        // Auto-push PPPoE hanya untuk non-ZTE yang punya credentials di DB
                        // ZTE skip: PPPoE dikonfigurasi di OLT pon-onu-mng, bukan via ACS
                        if (!$isZte && !empty($onu['pppoe_user']) && !empty($onu['pppoe_pass'])) {
                            $mfr   = strtolower($info['manufacturer'] ?? '');
                            $brand = (str_contains($mfr, 'fiber') || str_contains($mfr, 'fh'))
                                   ? 'fiberhome'
                                   : (str_contains($mfr, 'huawei') ? 'huawei' : 'default');
                            try {
                                $acsService->queueProvisionPppoe(
                                    $deviceId,
                                    $onu['pppoe_user'],
                                    $onu['pppoe_pass'],
                                    $brand,
                                    ['vlan_internet' => (int)($onu['vlan_internet'] ?? 0)]
                                );
                                $autoPushed++;
                            } catch (\Exception $e) {
                                $errors[] = "Auto-push {$sn}: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }

        $msg = "{$totalOnline} online dari {$totalSynced} device di ACS.";
        if ($pppoeUpdated) $msg .= " {$pppoeUpdated} PPPoE username disimpan ke DB.";
        if ($autoPushed)   $msg .= " {$autoPushed} ONU di-queue auto-provisioning PPPoE.";
        if ($errors)       $msg .= " " . count($errors) . " error.";

        return $this->response->setJSON([
            'success'       => true,
            'online'        => $totalOnline,
            'synced'        => $totalSynced,
            'pppoe_updated' => $pppoeUpdated,
            'auto_pushed'   => $autoPushed,
            'errors'        => $errors,
            'message'       => $msg,
        ]);
    }
}
