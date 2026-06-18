<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-end mb-4">
    <a href="/admin/users/create" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>Tambah User
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-people me-1"></i>Daftar User</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
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
                            <td><?= esc($u['name']) ?></td>
                            <td>
                                <code><?= esc($u['username']) ?></code>
                                <?php if ($u['id'] == session()->get('user_id')): ?>
                                    <span class="badge bg-info ms-1">Kamu</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= esc($u['email']) ?></td>
                            <td>
                                <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                                    <?= esc($u['role']) ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="/admin/users/<?= $u['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($u['id'] != session()->get('user_id')): ?>
                                        <a href="/admin/users/<?= $u['id'] ?>/delete"
                                           onclick="return confirm('Hapus user <?= esc($u['username'], 'js') ?>? Semua OLT dan ONU-nya ikut terhapus!')"
                                           class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
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
</div>

<?= $this->endSection() ?>
