<?php

namespace App\Controllers;

use App\Models\OltModel;
use App\Models\OnuModel;
use App\Models\TemplateModel;
use App\Models\ProvisionLogModel;
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

        $data = [
            'title'         => 'Dashboard',
            'total_olts'    => count($oltModel->getByUser($userId)),
            'total_onus'    => count($onuModel->getByUser($userId)),
            'total_templates' => count($templateModel->getByUser($userId)),
            'recent_logs'   => $logModel->getByUser($userId, 10),
        ];

        return view('dashboard/index', $data);
    }
}
