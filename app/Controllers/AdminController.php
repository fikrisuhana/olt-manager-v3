<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\OltModel;
use App\Models\OnuModel;
use App\Models\AcsServerModel;
use App\Models\ProvisionLogModel;
use CodeIgniter\Controller;

class AdminController extends Controller
{
    // ─── Users ───────────────────────────────────────────────────

    public function users()
    {
        $userModel = new UserModel();
        return view('admin/users', [
            'title' => 'Manajemen User',
            'users' => $userModel->orderBy('created_at', 'DESC')->findAll(),
        ]);
    }

    public function userCreate()
    {
        return view('admin/user_form', ['title' => 'Tambah User', 'user' => null]);
    }

    public function userStore()
    {
        $userModel = new UserModel();
        $password  = $this->request->getPost('password');

        if (empty($this->request->getPost('username')) || empty($password)) {
            return redirect()->back()->with('error', 'Username dan password wajib diisi.')->withInput();
        }

        if ($userModel->findByUsername($this->request->getPost('username'))) {
            return redirect()->back()->with('error', 'Username sudah digunakan.')->withInput();
        }

        $userModel->skipValidation(true)->insert([
            'name'     => $this->request->getPost('name'),
            'username' => $this->request->getPost('username'),
            'email'    => $this->request->getPost('email'),
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role'     => $this->request->getPost('role') ?: 'user',
        ]);

        return redirect()->to('/admin/users')->with('success', 'User berhasil dibuat.');
    }

    public function userEdit(int $id)
    {
        $userModel = new UserModel();
        $user = $userModel->find($id);
        if (!$user) return redirect()->to('/admin/users')->with('error', 'User tidak ditemukan.');
        return view('admin/user_form', ['title' => 'Edit User', 'user' => $user]);
    }

    public function userUpdate(int $id)
    {
        $userModel = new UserModel();
        $user = $userModel->find($id);
        if (!$user) return redirect()->to('/admin/users')->with('error', 'User tidak ditemukan.');

        $data = [
            'name'  => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'role'  => $this->request->getPost('role') ?: 'user',
        ];

        $newPass = $this->request->getPost('password');
        if (!empty($newPass)) {
            $data['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        }

        $userModel->update($id, $data);
        return redirect()->to('/admin/users')->with('success', 'User berhasil diperbarui.');
    }

    public function userDelete(int $id)
    {
        $userModel = new UserModel();
        // Jangan hapus diri sendiri
        if ($id == session()->get('user_id')) {
            return redirect()->to('/admin/users')->with('error', 'Tidak bisa menghapus akun sendiri.');
        }
        $userModel->delete($id);
        return redirect()->to('/admin/users')->with('success', 'User berhasil dihapus.');
    }

    // ─── Overview semua OLT ──────────────────────────────────────

    public function olts()
    {
        $oltModel  = new OltModel();
        $userModel = new UserModel();
        $onuModel  = new OnuModel();

        // Ambil semua OLT dari semua user + nama user
        $olts = $oltModel->select('olts.*, users.name as user_name, users.username')
                         ->join('users', 'users.id = olts.user_id')
                         ->orderBy('users.username, olts.name')
                         ->findAll();

        // Hitung jumlah ONU per OLT
        foreach ($olts as &$olt) {
            $olt['onu_count'] = $onuModel->where('olt_id', $olt['id'])
                                         ->where('status !=', 'deleted')
                                         ->countAllResults();
        }

        return view('admin/olts', [
            'title' => 'Semua OLT',
            'olts'  => $olts,
            'users' => $userModel->findAll(),
        ]);
    }

    // ─── Overview semua ACS ──────────────────────────────────────

    public function acs()
    {
        $acsModel  = new AcsServerModel();
        $userModel = new UserModel();

        $servers = $acsModel->select('acs_servers.*, users.name as user_name, users.username')
                            ->join('users', 'users.id = acs_servers.user_id')
                            ->orderBy('users.username, acs_servers.name')
                            ->findAll();

        return view('admin/acs', [
            'title'   => 'Semua ACS Server',
            'servers' => $servers,
        ]);
    }

    // ─── Log aktivitas global ────────────────────────────────────

    public function logs()
    {
        $logModel = new ProvisionLogModel();
        $logs = $logModel->select('provision_logs.*, users.username, onus.sn, olts.name as olt_name')
                         ->join('users', 'users.id = provision_logs.user_id', 'left')
                         ->join('onus', 'onus.id = provision_logs.onu_id', 'left')
                         ->join('olts', 'olts.id = provision_logs.olt_id', 'left')
                         ->orderBy('provision_logs.created_at', 'DESC')
                         ->limit(200)
                         ->findAll();

        return view('admin/logs', ['title' => 'Log Aktivitas Global', 'logs' => $logs]);
    }
}
