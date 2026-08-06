<?php

namespace App\Controllers;

use App\Models\OltModel;
use App\Models\OnuModel;
use App\Models\ProvisionLogModel;
use App\Libraries\OltDriverFactory;

/**
 * Menu "Setting OLT" — perawatan perangkat, bukan pendaftaran pelanggan:
 * status & enable/disable port PON, VLAN database, info sistem OLT.
 */
class OltSettingsController extends BaseController
{
    private int $userId;

    public function __construct()
    {
        $this->userId = (int) session()->get('user_id');
        session_write_close();
    }

    public function index(?int $oltId = null)
    {
        $oltModel = new OltModel();
        $olts     = $oltModel->getByUser($this->userId);

        if (empty($olts)) {
            return redirect()->to('/olts')->with('error', 'Belum ada OLT. Silakan tambahkan OLT terlebih dahulu.');
        }

        $selectedOlt = $oltId ? $oltModel->getByUserAndId($this->userId, $oltId) : null;
        if (!$selectedOlt) $selectedOlt = $olts[0];

        return view('olt_settings/index', [
            'title'       => 'Setting OLT',
            'olts'        => $olts,
            'selectedOlt' => $selectedOlt,
        ]);
    }

    /** GET /olts/{id}/settings/status — info sistem + port PON (1 sesi telnet) */
    public function status(int $oltId)
    {
        $this->response->setContentType('application/json');
        $olt = (new OltModel())->getByUserAndId($this->userId, $oltId);
        if (!$olt) return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            // Satu sesi untuk semuanya — VTY OLT terbatas, jangan konek berkali-kali.
            $system = $driver->getSystemInfo();
            $ports  = $driver->getPonPorts();
            $vlans  = $driver->getVlanDatabase();
            $driver->disconnect();

            return $this->response->setJSON([
                'success' => true,
                'system'  => $system,
                'ports'   => $ports,
                'vlans'   => $vlans,
            ]);
        } catch (\Throwable $e) {
            log_message('error', "OLT settings status {$oltId}: " . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /** POST /olts/{id}/settings/pon-state — shutdown / no shutdown port PON */
    public function ponState(int $oltId)
    {
        $this->response->setContentType('application/json');
        $olt = (new OltModel())->getByUserAndId($this->userId, $oltId);
        if (!$olt) return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);

        $board  = trim($this->request->getPost('board') ?? '');
        $slot   = trim($this->request->getPost('slot') ?? '');
        $port   = trim($this->request->getPost('port') ?? '');
        $enable = (bool)(int)$this->request->getPost('enable');

        if ($board === '' || $slot === '' || $port === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Port PON tidak lengkap.']);
        }

        // Mematikan port PON memutus SEMUA pelanggan di port itu. Sebutkan angkanya
        // supaya yang menekan tombol tahu persis dampaknya (juga masuk log).
        $affected = (new OnuModel())->countActiveOnPort($oltId, $board, $slot, $port);

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $result = $driver->setPonPortState($board, $slot, $port, $enable);
            $driver->disconnect();

            $action = $enable ? 'enable' : 'disable';
            (new ProvisionLogModel())->log(
                $this->userId, 'pon_' . $action, $result['success'] ? 'success' : 'failed',
                "Port gpon-olt_{$board}/{$slot}/{$port} di-{$action}"
                . ($affected ? " ({$affected} ONU terdaftar di port ini)" : ''),
                null, $oltId
            );

            return $this->response->setJSON([
                'success'  => $result['success'],
                'affected' => $affected,
                'log'      => $result['log'] ?? [],
                'message'  => $result['success']
                    ? "Port gpon-olt_{$board}/{$slot}/{$port} berhasil di-{$action}."
                    : "Gagal {$action} port: " . implode(' | ', $result['log'] ?? []),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /** POST /olts/{id}/settings/vlan-add */
    public function vlanAdd(int $oltId)
    {
        $this->response->setContentType('application/json');
        $olt = (new OltModel())->getByUserAndId($this->userId, $oltId);
        if (!$olt) return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);

        $vlanId = (int) $this->request->getPost('vlan_id');
        if ($vlanId < 1 || $vlanId > 4094) {
            return $this->response->setJSON(['success' => false, 'message' => 'VLAN ID harus 1–4094.']);
        }

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $result = $driver->addVlan($vlanId);
            $driver->disconnect();

            (new ProvisionLogModel())->log(
                $this->userId, 'vlan_add', $result['success'] ? 'success' : 'failed',
                "VLAN {$vlanId} ditambahkan ke VLAN database", null, $oltId
            );
            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /** POST /olts/{id}/settings/vlan-delete */
    public function vlanDelete(int $oltId)
    {
        $this->response->setContentType('application/json');
        $olt = (new OltModel())->getByUserAndId($this->userId, $oltId);
        if (!$olt) return $this->response->setJSON(['success' => false, 'message' => 'OLT tidak ditemukan.']);

        $vlanId = (int) $this->request->getPost('vlan_id');
        if ($vlanId < 1 || $vlanId > 4094) {
            return $this->response->setJSON(['success' => false, 'message' => 'VLAN ID harus 1–4094.']);
        }

        // Hapus VLAN yang masih dipakai = memutus pelanggan diam-diam. Tolak di sini,
        // bukan menyerahkan ke OLT (OLT tetap menerima perintahnya).
        $used = (new OnuModel())->countUsingVlan($oltId, $vlanId);
        if ($used > 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => "VLAN {$vlanId} masih dipakai {$used} ONU di OLT ini (internet/ACS). "
                           . "Pindahkan ONU-nya dulu sebelum VLAN dihapus.",
            ]);
        }

        try {
            $driver = OltDriverFactory::make($olt);
            $driver->connect();
            $result = $driver->deleteVlan($vlanId);
            $driver->disconnect();

            (new ProvisionLogModel())->log(
                $this->userId, 'vlan_delete', $result['success'] ? 'success' : 'failed',
                "VLAN {$vlanId} dihapus dari VLAN database", null, $oltId
            );
            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
