<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

class AuthController extends Controller
{
    public function login()
    {
        if (session()->get('user_id')) {
            return redirect()->to('/dashboard');
        }
        return view('auth/login');
    }

    public function loginProcess()
    {
        $username = $this->request->getPost('username');
        $password = $this->request->getPost('password');

        $userModel = new UserModel();
        $user = $userModel->findByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            return redirect()->back()->with('error', 'Username atau password salah.')->withInput();
        }

        session()->set([
            'user_id'   => $user['id'],
            'user_name' => $user['name'],
            'username'  => $user['username'],
            'user_role' => $user['role'],
        ]);

        return redirect()->to('/dashboard');
    }

    // Registrasi publik dinonaktifkan — user dibuat oleh admin
    public function register()
    {
        return redirect()->to('/login')->with('error', 'Registrasi hanya bisa dilakukan oleh admin.');
    }

    public function registerSave()
    {
        return redirect()->to('/login');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
