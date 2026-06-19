<?php

namespace App\Controllers;

use App\Models\OltModel;
use App\Models\OnuModel;
use App\Models\TemplateModel;
use App\Models\ProvisionLogModel;
use App\Libraries\OnuCacheService;
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
}
