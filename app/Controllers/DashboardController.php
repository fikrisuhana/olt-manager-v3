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
        $cache = new OnuCacheService();
        $onlineOnus = 0;
        $acsTotal   = 0;
        foreach ($olts as $olt) {
            foreach ($cache->loadAcs($olt['id'])['devices'] as $info) {
                $acsTotal++;
                if ($info['online']) $onlineOnus++;
            }
        }

        $data = [
            'title'            => 'Dashboard',
            'total_olts'       => count($olts),
            'total_onus'       => count($onuModel->getByUser($userId)),
            'total_templates'  => count($templateModel->getByUser($userId)),
            'recent_logs'      => $logModel->getByUser($userId, 10),
            'online_onus'      => $onlineOnus,
            'acs_total'        => $acsTotal,
        ];

        return view('dashboard/index', $data);
    }
}
