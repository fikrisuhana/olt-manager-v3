<?php

namespace App\Controllers;

use App\Models\AcsServerModel;
use App\Models\AcsPolicyModel;
use App\Libraries\AcsService;
use App\Libraries\AcsPolicyService;
use CodeIgniter\Controller;

class AcsController extends Controller
{
    private int $userId;

    public function __construct()
    {
        $this->userId = (int) session()->get('user_id');
        // JANGAN session_write_close() di sini: redirect()->with() (flash sukses/error)
        // jadi tidak tersimpan. Lepas lock sesi hanya di endpoint AJAX yang lama (test()).
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
        session_write_close(); // AJAX bisa lama (timeout NBI) — jangan kunci sesi tab lain
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

    // ── Kebijakan ACS mandiri (provision/preset gm-* di GenieACS) ───────────

    public function policy(int $id)
    {
        $server = (new AcsServerModel())->getByUserAndId($this->userId, $id);
        if (!$server) return redirect()->to('/acs')->with('error', 'ACS server tidak ditemukan.');

        AcsPolicyModel::ensureTable();
        $row      = (new AcsPolicyModel())->getSettings($id);
        $settings = AcsPolicyService::withDefaults($row['settings'] ?? null);

        // Info preset di GenieACS (boleh gagal — halaman tetap tampil)
        $foreign = null;
        $gmLive  = [];
        $acsErr  = null;
        try {
            $acs = new AcsService($server);
            foreach ($acs->listPresets() as $p) {
                if (str_starts_with((string)($p['_id'] ?? ''), AcsPolicyService::PREFIX)) $gmLive[] = $p['_id'];
            }
            $foreign = AcsPolicyService::foreignPresets($acs);
        } catch (\Throwable $e) {
            $acsErr = $e->getMessage();
        }

        return view('acs/policy', [
            'title'    => 'Kebijakan ACS',
            'server'   => $server,
            'settings' => $settings,
            'row'      => $row,
            'plan'     => AcsPolicyService::plan($settings),
            'gmLive'   => $gmLive,
            'foreign'  => $foreign,
            'acsErr'   => $acsErr,
        ]);
    }

    public function policySave(int $id)
    {
        $server = (new AcsServerModel())->getByUserAndId($this->userId, $id);
        if (!$server) return redirect()->to('/acs')->with('error', 'ACS server tidak ditemukan.');

        AcsPolicyModel::ensureTable();
        $model   = new AcsPolicyModel();
        $current = $model->getSettings($id)['settings'] ?? [];

        [$settings, $errors] = AcsPolicyService::fromPost($this->request->getPost() ?? [], $current);
        if ($errors) {
            return redirect()->to("/acs/{$id}/policy")->with('error', implode(' ', $errors));
        }
        $model->saveSettings($id, $settings);

        return $this->doPush($id, $server, $settings, 'Kebijakan disimpan');
    }

    /** Push ulang kebijakan tersimpan (mis. setelah GenieACS di-reset). */
    public function policyPush(int $id)
    {
        $server = (new AcsServerModel())->getByUserAndId($this->userId, $id);
        if (!$server) return redirect()->to('/acs')->with('error', 'ACS server tidak ditemukan.');

        AcsPolicyModel::ensureTable();
        $row = (new AcsPolicyModel())->getSettings($id);
        if (!$row) {
            return redirect()->to("/acs/{$id}/policy")->with('error', 'Belum ada kebijakan tersimpan. Simpan dulu.');
        }
        return $this->doPush($id, $server, $row['settings'], 'Push ulang');
    }

    private function doPush(int $id, array $server, array $settings, string $what)
    {
        $model = new AcsPolicyModel();
        try {
            $result = AcsPolicyService::push(new AcsService($server), $settings);
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'log' => ['✖ ' . $e->getMessage()]];
        }
        $msg = implode(' · ', $result['log']);
        $model->savePushResult($id, $result['ok'], $msg);

        return redirect()->to("/acs/{$id}/policy")->with(
            $result['ok'] ? 'success' : 'error',
            "{$what}. " . ($result['ok'] ? 'Push ke GenieACS berhasil: ' : 'Push ke GenieACS GAGAL: ') . $msg
        );
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
