<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center space-x-3 mb-4">
        <a href="/admin/users" class="btn btn-secondary btn-sm btn-icon-only" title="Kembali">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight"><?= $user ? 'Edit User: ' . esc($user['username']) : 'Tambah User Baru' ?></h1>
            <p class="text-xs text-slate-500">Data akun &amp; hak akses</p>
        </div>
    </div>

    <div class="card !mb-0">
        <form method="POST" action="<?= $user ? '/admin/users/' . $user['id'] . '/update' : '/admin/users/store' ?>">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="name" class="form-input"
                       value="<?= esc(old('name', $user['name'] ?? '')) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input font-mono"
                       value="<?= esc(old('username', $user['username'] ?? '')) ?>"
                       <?= $user ? 'readonly' : 'required' ?>>
                <?php if ($user): ?>
                    <div class="text-[11px] text-slate-400 mt-1">Username tidak bisa diubah.</div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input"
                       value="<?= esc(old('email', $user['email'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">
                    Password <?= $user ? '<span class="font-normal text-slate-400">(kosongkan jika tidak diubah)</span>' : '' ?>
                </label>
                <input type="password" name="password" class="form-input"
                       <?= $user ? '' : 'required' ?> autocomplete="new-password">
            </div>

            <div class="mb-4">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="user"  <?= (old('role', $user['role'] ?? 'user') === 'user')  ? 'selected' : '' ?>>User</option>
                    <option value="admin" <?= (old('role', $user['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="check" class="w-4 h-4"></i> <?= $user ? 'Simpan Perubahan' : 'Buat User' ?>
                </button>
                <a href="/admin/users" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
