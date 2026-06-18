<?php

namespace App\Controllers;

use App\Models\AcsServerModel;
use App\Libraries\AcsService;
use CodeIgniter\Controller;

class AcsController extends Controller
{
    private int $userId;

    public function __construct()
    {
        $this->userId = (int) session()->get('user_id');
        session_write_close();
    }

    public function index()
    {
        $model = new AcsServerModel();
        return view('acs/index', [
            'title'  => 'ACS Server',
            'servers'=> $model->getByUser($this->userId),
        ]);
    }

    public function create()
    {
        return view('acs/form', ['title' => 'Tambah ACS Server', 'server' => null]);
    }

    public function store()
    {
        $model = new AcsServerModel();
        $data  = $this->getFormData();

        if (empty($data['name']) || empty($data['url'])) {
            return redirect()->back()->with('error', 'Nama dan URL wajib diisi.')->withInput();
        }

        $data['user_id'] = $this->userId;

        // Jika is_default, unset yang lain dulu
        if (!empty($data['is_default'])) {
            $model->where('user_id', $this->userId)->set('is_default', 0)->update();
        }

        $model->insert($data);
        return redirect()->to('/acs')->with('success', 'ACS server berhasil ditambahkan.');
    }

    public function delete(int $id)
    {
        $model  = new AcsServerModel();
        $server = $model->getByUserAndId($this->userId, $id);
        if (!$server) return redirect()->to('/acs')->with('error', 'ACS server tidak ditemukan.');

        $model->delete($id);
        return redirect()->to('/acs')->with('success', 'ACS server berhasil dihapus.');
    }

    public function setDefault(int $id)
    {
        $model  = new AcsServerModel();
        $server = $model->getByUserAndId($this->userId, $id);
        if (!$server) return redirect()->to('/acs')->with('error', 'ACS server tidak ditemukan.');

        $model->setDefault($this->userId, $id);
        return redirect()->to('/acs')->with('success', 'Default ACS server diperbarui.');
    }

    /**
     * AJAX: Test koneksi ke ACS server
     */
    public function test(int $id)
    {
        $model  = new AcsServerModel();
        $server = $model->getByUserAndId($this->userId, $id);
        if (!$server) {
            return $this->response->setJSON(['success' => false, 'message' => 'ACS server tidak ditemukan.']);
        }

        try {
            $acs     = new AcsService($server);
            $devices = $acs->getOnlineDevices();
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Koneksi berhasil. Device online: ' . count($devices),
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getFormData(): array
    {
        return [
            'name'       => $this->request->getPost('name'),
            'url'        => rtrim($this->request->getPost('url'), '/'),
            'username'   => $this->request->getPost('username'),
            'password'   => $this->request->getPost('password'),
            'is_default' => $this->request->getPost('is_default') ? 1 : 0,
        ];
    }
}
