<?php

namespace App\Commands;

use App\Libraries\AcsService;
use App\Libraries\OnuCacheService;
use App\Models\AcsServerModel;
use App\Models\OltModel;
use App\Models\OnuModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class AcsAutoProvision extends BaseCommand
{
    protected $group       = 'ACS';
    protected $name        = 'acs:auto-provision';
    protected $description = 'Auto-push PPPoE ke ONU non-ZTE yang baru muncul di ACS (server-side, tanpa browser).';
    protected $usage       = 'php spark acs:auto-provision [--dry-run]';
    protected $options     = [
        '--dry-run' => 'Tampilkan apa yang akan dilakukan tanpa benar-benar push ke ACS.',
    ];

    public function run(array $params): void
    {
        $dryRun     = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');
        $acsModel   = new AcsServerModel();
        $oltModel   = new OltModel();
        $onuModel   = new OnuModel();
        $cache      = new OnuCacheService();

        // Ambil semua ACS server default per user
        $db          = \Config\Database::connect();
        $userIds     = $db->table('acs_servers')
                          ->select('user_id')
                          ->where('is_default', 1)
                          ->distinct()
                          ->get()->getResultArray();

        if (empty($userIds)) {
            CLI::writeLn('Tidak ada ACS server terdaftar.');
            return;
        }

        $totalPushed = 0;
        $totalErrors = 0;

        foreach ($userIds as $row) {
            $userId = (int) $row['user_id'];
            $acs    = $acsModel->getDefault($userId);
            if (!$acs) continue;

            $olts = $oltModel->getByUser($userId);
            if (empty($olts)) continue;

            try {
                $acsService = new AcsService($acs);
            } catch (\Exception $e) {
                CLI::error("User {$userId}: gagal init ACS — " . $e->getMessage());
                $totalErrors++;
                continue;
            }

            foreach ($olts as $olt) {
                $onus = $onuModel->getByOlt($olt['id']);
                if (empty($onus)) continue;

                $onuBySn = [];
                foreach ($onus as $o) {
                    $onuBySn[strtoupper($o['sn'])] = $o;
                }

                $sns = array_map(fn($o) => strtoupper($o['sn']), $onus);

                try {
                    $acsData = $acsService->getDevicesBySns($sns);
                } catch (\Exception $e) {
                    CLI::error("OLT {$olt['name']}: gagal query ACS — " . $e->getMessage());
                    $totalErrors++;
                    continue;
                }

                // Simpan cache (dashboard tetap up-to-date)
                $cache->saveAcs($olt['id'], $acsData);

                foreach ($acsData as $sn => $info) {
                    $onu = $onuBySn[strtoupper($sn)] ?? null;
                    if (!$onu) continue;

                    $isZte    = strncasecmp($onu['sn'], 'ZTEG', 4) === 0;
                    $wasInAcs = !empty($onu['acs_device_id']);
                    $deviceId = $info['device_id'] ?? null;

                    // Simpan device_id ke DB jika baru pertama kali muncul
                    if (!$wasInAcs && $deviceId) {
                        if (!$dryRun) {
                            $onuModel->update($onu['id'], ['acs_device_id' => $deviceId]);
                        }

                        // Skip ZTE: PPPoE diatur via OLT pon-onu-mng, bukan ACS
                        if ($isZte) {
                            CLI::writeLn("[SKIP-ZTE] {$sn} — device_id disimpan, PPPoE skip.");
                            continue;
                        }

                        // Butuh credentials
                        if (empty($onu['pppoe_user']) || empty($onu['pppoe_pass'])) {
                            CLI::writeLn("[SKIP-NOCRED] {$sn} — belum ada PPPoE credentials di DB.");
                            continue;
                        }

                        $mfr   = strtolower($info['manufacturer'] ?? '');
                        $brand = (str_contains($mfr, 'fiber') || str_contains($mfr, 'fh'))
                               ? 'fiberhome'
                               : (str_contains($mfr, 'huawei') ? 'huawei' : 'default');

                        if ($dryRun) {
                            CLI::writeLn("[DRY-RUN] Akan push PPPoE ke {$sn} ({$brand}) user={$onu['pppoe_user']}");
                            $totalPushed++;
                            continue;
                        }

                        try {
                            $acsService->queueProvisionPppoe(
                                $deviceId,
                                $onu['pppoe_user'],
                                $onu['pppoe_pass'],
                                $brand,
                                ['vlan_internet' => (int)($onu['vlan_internet'] ?? 0)]
                            );
                            CLI::writeLn("[PUSHED] {$sn} ({$brand}) → PPPoE queued ke ACS.");
                            $totalPushed++;
                        } catch (\Exception $e) {
                            CLI::error("[ERROR] {$sn}: " . $e->getMessage());
                            $totalErrors++;
                        }
                    }
                }
            }
        }

        CLI::writeLn('');
        CLI::writeLn("Selesai: {$totalPushed} ONU di-push, {$totalErrors} error.");
    }
}
