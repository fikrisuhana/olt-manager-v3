<?php

namespace App\Controllers;

use App\Models\TemplateModel;
use CodeIgniter\Controller;

class TemplateController extends Controller
{
    private int $userId;

    public function __construct()
    {
        $this->userId = (int) session()->get('user_id');
    }

    public function index()
    {
        $model = new TemplateModel();
        return view('template/index', [
            'title'     => 'Template Konfigurasi',
            'templates' => $model->getByUser($this->userId),
        ]);
    }

    public function create()
    {
        return view('template/form', ['title' => 'Tambah Template', 'template' => null]);
    }

    public function store()
    {
        $model = new TemplateModel();
        $data  = $this->getFormData();

        if (empty($data['name'])) {
            return redirect()->back()->with('error', 'Nama template wajib diisi.')->withInput();
        }

        $data['user_id'] = $this->userId;
        $model->insert($data);
        return redirect()->to('/templates')->with('success', 'Template berhasil disimpan.');
    }

    public function edit(int $id)
    {
        $model    = new TemplateModel();
        $template = $model->getByUserAndId($this->userId, $id);
        if (!$template) return redirect()->to('/templates')->with('error', 'Template tidak ditemukan.');

        return view('template/form', ['title' => 'Edit Template', 'template' => $template]);
    }

    public function update(int $id)
    {
        $model    = new TemplateModel();
        $template = $model->getByUserAndId($this->userId, $id);
        if (!$template) return redirect()->to('/templates')->with('error', 'Template tidak ditemukan.');

        $model->update($id, $this->getFormData());
        return redirect()->to('/templates')->with('success', 'Template berhasil diperbarui.');
    }

    public function delete(int $id)
    {
        $model    = new TemplateModel();
        $template = $model->getByUserAndId($this->userId, $id);
        if (!$template) return redirect()->to('/templates')->with('error', 'Template tidak ditemukan.');

        $model->delete($id);
        return redirect()->to('/templates')->with('success', 'Template berhasil dihapus.');
    }

    private function getFormData(): array
    {
        return [
            'name'               => $this->request->getPost('name'),
            'brand'              => $this->request->getPost('brand') ?: 'ZTE',
            'vlan_internet'      => $this->request->getPost('vlan_internet') ?: null,
            'vlan_management'    => $this->request->getPost('vlan_management') ?: 100,
            'wan_type'           => $this->request->getPost('wan_type') ?: 'pppoe',
            'tcont_profile'      => $this->request->getPost('tcont_profile'),
            'gpon_onu_script' => $this->request->getPost('gpon_onu_script'),
            'description'     => $this->request->getPost('description'),
        ];
    }
}
