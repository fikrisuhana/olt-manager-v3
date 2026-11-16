<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="users" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Daftar User</h1>
            <p class="text-xs text-slate-500">Kelola akun pengguna GPON Manager</p>
        </div>
    </div>
    <a href="/admin/users/create" class="btn btn-primary btn-sm">
        <i data-lucide="user-plus" class="w-4 h-4"></i> Tambah User
    </a>
</div>

<div class="card !mb-0">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Dibuat</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="font-medium text-xs"><?= esc($u['name']) ?></td>
                    <td>
                        <span class="font-mono text-xs"><?= esc($u['username']) ?></span>
                        <?php if ($u['id'] == session()->get('user_id')): ?>
                            <span class="chip chip-info ml-1">Kamu</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-xs text-slate-400"><?= esc($u['email']) ?></td>
                    <td>
                        <span class="chip <?= $u['role'] === 'admin' ? 'chip-danger' : 'chip-neutral' ?>">
                            <?= esc($u['role']) ?>
                        </span>
                    </td>
                    <td class="text-xs text-slate-400"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td class="text-right">
                        <div class="flex gap-1 justify-end">
                            <a href="/admin/users/<?= $u['id'] ?>/edit" class="btn btn-secondary btn-sm btn-icon-only" title="Edit">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </a>
                            <?php if ($u['id'] != session()->get('user_id')): ?>
                                <a href="/admin/users/<?= $u['id'] ?>/delete"
                                   onclick="return confirm('Hapus user <?= esc($u['username'], 'js') ?>? Semua OLT dan ONU-nya ikut terhapus!')"
                                   class="btn btn-danger btn-sm btn-icon-only" title="Hapus">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
