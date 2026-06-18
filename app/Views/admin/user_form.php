<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person<?= $user ? '-gear' : '-plus' ?> me-1"></i>
                    <?= $user ? 'Edit User: ' . esc($user['username']) : 'Tambah User Baru' ?>
                </h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= $user ? '/admin/users/' . $user['id'] . '/update' : '/admin/users/store' ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Nama Lengkap</label>
                        <input type="text" name="name" class="form-control"
                               value="<?= esc(old('name', $user['name'] ?? '')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Username</label>
                        <input type="text" name="username" class="form-control"
                               value="<?= esc(old('username', $user['username'] ?? '')) ?>"
                               <?= $user ? 'readonly' : 'required' ?>>
                        <?php if ($user): ?>
                            <div class="form-text">Username tidak bisa diubah.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= esc(old('email', $user['email'] ?? '')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">
                            Password <?= $user ? '<span class="text-muted fw-normal">(kosongkan jika tidak diubah)</span>' : '' ?>
                        </label>
                        <input type="password" name="password" class="form-control"
                               <?= $user ? '' : 'required' ?> autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium">Role</label>
                        <select name="role" class="form-select">
                            <option value="user"  <?= (old('role', $user['role'] ?? 'user') === 'user')  ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= (old('role', $user['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-lg me-1"></i><?= $user ? 'Simpan Perubahan' : 'Buat User' ?>
                        </button>
                        <a href="/admin/users" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
