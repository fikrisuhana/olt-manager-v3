<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — GPON Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { width: 420px; background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px; }
        .brand { color: #fff; font-weight: 700; font-size: 1.3rem; }
        .brand span { color: #3b82f6; }
        .form-control { background: #0f172a; border-color: #334155; color: #e2e8f0; }
        .form-control:focus { background: #0f172a; border-color: #3b82f6; color: #e2e8f0; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .form-label { color: #94a3b8; font-size: .875rem; }
        ::placeholder { color: #475569; }
    </style>
</head>
<body>
<div class="card">
    <div class="text-center mb-4">
        <div class="brand mb-1"><i class="bi bi-broadcast-pin me-1"></i>GPON <span>Manager</span></div>
        <small class="text-secondary">Daftar akun baru</small>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger py-2 small"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <form action="/register/save" method="POST">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="name" class="form-control" placeholder="Nama lengkap"
                   value="<?= esc(old('name')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Username"
                   value="<?= esc(old('username')) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="email@domain.com"
                   value="<?= esc(old('email')) ?>" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Min. 6 karakter" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-person-plus me-1"></i> Daftar
        </button>
    </form>

    <div class="text-center mt-3">
        <small class="text-secondary">Sudah punya akun? <a href="/login" class="text-primary">Login</a></small>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
