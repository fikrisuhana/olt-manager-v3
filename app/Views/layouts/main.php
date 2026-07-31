<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'GPON Manager') ?> — GPON Manager</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-width: 256px;
            --topbar-h: 64px;
            --google-blue: #1a73e8;
            --google-blue-hover: #1557b0;
            --google-blue-light: #e8f0fe;
            --google-gray-bg: #f8f9fa;
            --google-border: #dadce0;
            --google-text-primary: #202124;
            --google-text-secondary: #5f6368;
            --google-card-shadow: 0 1px 3px rgba(60,64,67,0.08), 0 1px 2px rgba(60,64,67,0.12);
        }

        * { font-family: 'Roboto', 'Google Sans', -apple-system, BlinkMacSystemFont, sans-serif; }

        body {
            background-color: var(--google-gray-bg);
            color: var(--google-text-primary);
            font-size: 0.9rem;
        }

        /* Sidebar Google Style */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: #ffffff;
            border-right: 1px solid var(--google-border);
            position: fixed;
            top: 0; left: 0;
            z-index: 1040;
            display: flex;
            flex-direction: column;
            padding: 0 12px;
        }

        #sidebar .brand {
            height: var(--topbar-h);
            display: flex; align-items: center; gap: 12px;
            padding: 0 12px;
            color: var(--google-text-primary);
            font-weight: 700;
            font-size: 1.15rem;
            font-family: 'Google Sans', sans-serif;
            text-decoration: none;
        }

        #sidebar .brand i {
            color: var(--google-blue);
            font-size: 1.4rem;
        }

        #sidebar .brand span {
            color: var(--google-blue);
        }

        #sidebar .nav-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--google-text-secondary);
            padding: 18px 16px 6px;
        }

        #sidebar .nav-link {
            color: var(--google-text-primary);
            padding: 10px 16px;
            border-radius: 24px;
            margin: 2px 0;
            display: flex; align-items: center; gap: 14px;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        #sidebar .nav-link:hover {
            background-color: #f1f3f4;
            color: var(--google-text-primary);
        }

        #sidebar .nav-link.active {
            background-color: var(--google-blue-light);
            color: var(--google-blue);
            font-weight: 700;
        }

        #sidebar .nav-link i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        #sidebar .sidebar-footer {
            margin-top: auto;
            padding: 16px 0;
            border-top: 1px solid var(--google-border);
        }

        /* Topbar Google Style */
        #topbar {
            height: var(--topbar-h);
            background: #ffffff;
            border-bottom: 1px solid var(--google-border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px;
            position: fixed;
            top: 0; left: var(--sidebar-width); right: 0;
            z-index: 1030;
        }

        #topbar .page-title {
            font-family: 'Google Sans', sans-serif;
            font-weight: 600;
            font-size: 1.15rem;
            color: var(--google-text-primary);
        }

        .user-chip {
            background: var(--google-gray-bg);
            border: 1px solid var(--google-border);
            border-radius: 20px;
            padding: 4px 14px 4px 6px;
            display: flex; align-items: center; gap: 8px;
            font-weight: 500;
            color: var(--google-text-primary);
        }

        .user-avatar {
            width: 28px; height: 28px;
            background: var(--google-blue);
            color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
        }

        /* Main Container */
        #main-content {
            margin-left: var(--sidebar-width);
            padding-top: calc(var(--topbar-h) + 24px);
            padding-bottom: 48px;
            min-height: 100vh;
        }

        .page-container { padding: 0 32px; }

        /* Google Material Cards */
        .card {
            background: #ffffff;
            border: 1px solid var(--google-border);
            border-radius: 12px;
            box-shadow: var(--google-card-shadow);
        }

        .card-header {
            background: #ffffff;
            border-bottom: 1px solid var(--google-border);
            border-top-left-radius: 12px !important;
            border-top-right-radius: 12px !important;
            padding: 16px 20px;
        }

        /* Google Material Buttons */
        .btn-google-primary {
            background-color: var(--google-blue);
            color: #ffffff;
            border: none;
            border-radius: 20px;
            padding: 7px 20px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-google-primary:hover {
            background-color: var(--google-blue-hover);
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .btn-google-secondary {
            background-color: #ffffff;
            color: var(--google-text-primary);
            border: 1px solid var(--google-border);
            border-radius: 20px;
            padding: 7px 18px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: background 0.2s;
        }
        .btn-google-secondary:hover {
            background-color: #f1f3f4;
            color: var(--google-text-primary);
        }

        /* Google Chips / Badges */
        .chip {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.78rem;
            font-weight: 500;
        }
        .chip-success { background: #e6f4ea; color: #137333; }
        .chip-warning { background: #fef7e0; color: #b06000; }
        .chip-danger  { background: #fce8e6; color: #c5221f; }
        .chip-info    { background: #e8f0fe; color: #1a73e8; }
        .chip-neutral { background: #f1f3f4; color: #5f6368; }

        /* Form Controls */
        .form-control, .form-select {
            border: 1px solid var(--google-border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.875rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--google-blue);
            box-shadow: 0 0 0 2px var(--google-blue-light);
        }

        /* Tables */
        .table > :not(caption) > * > * {
            padding: 12px 16px;
            vertical-align: middle;
        }
        .table thead th {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--google-text-secondary);
            background: #f8f9fa;
            border-bottom: 1px solid var(--google-border);
        }

        /* Modals */
        .modal-content {
            border-radius: 16px;
            border: 1px solid var(--google-border);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        .modal-header {
            border-bottom: 1px solid var(--google-border);
            padding: 20px 24px;
        }
        .modal-footer {
            border-top: 1px solid var(--google-border);
            padding: 16px 24px;
        }

        /* CLI Output Code Block */
        pre.cli-output {
            background: #202124;
            color: #81c995;
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 0.82rem;
            max-height: 320px;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<!-- Sidebar Google Light Style -->
<nav id="sidebar">
    <a href="/dashboard" class="brand">
        <i class="bi bi-broadcast"></i>
        GPON <span>Manager</span>
    </a>

    <div class="nav-label">Menu Utama</div>

    <a href="/dashboard" class="nav-link <?= uri_string() === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="/olts" class="nav-link <?= str_starts_with(uri_string(), 'olts') ? 'active' : '' ?>">
        <i class="bi bi-hdd-network"></i> Manajemen OLT
    </a>
    <a href="/onus" class="nav-link <?= str_starts_with(uri_string(), 'onus') ? 'active' : '' ?>">
        <i class="bi bi-router"></i> ONU Terdaftar
    </a>
    <a href="/migration" class="nav-link <?= str_starts_with(uri_string(), 'migration') ? 'active' : '' ?>">
        <i class="bi bi-arrow-repeat"></i> Migrasi Massal
    </a>

    <div class="nav-label">Pengaturan</div>

    <a href="/templates" class="nav-link <?= str_starts_with(uri_string(), 'templates') ? 'active' : '' ?>">
        <i class="bi bi-file-code"></i> Template Config
    </a>
    <a href="/acs" class="nav-link <?= str_starts_with(uri_string(), 'acs') ? 'active' : '' ?>">
        <i class="bi bi-cloud-check"></i> ACS Server
    </a>

    <?php if (session()->get('user_role') === 'admin'): ?>
        <div class="nav-label">Administrator</div>
        <a href="/admin/users" class="nav-link <?= str_starts_with(uri_string(), 'admin/users') ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Kelola User
        </a>
        <a href="/admin/olts" class="nav-link <?= uri_string() === 'admin/olts' ? 'active' : '' ?>">
            <i class="bi bi-hdd-stack"></i> Semua OLT
        </a>
        <a href="/admin/acs" class="nav-link <?= uri_string() === 'admin/acs' ? 'active' : '' ?>">
            <i class="bi bi-server"></i> Semua ACS
        </a>
        <a href="/admin/logs" class="nav-link <?= uri_string() === 'admin/logs' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Log Global
        </a>
    <?php endif; ?>

    <div class="sidebar-footer">
        <a href="/logout" class="nav-link text-danger">
            <i class="bi bi-box-arrow-left"></i> Keluar
        </a>
    </div>
</nav>

<!-- Topbar Google Light Style -->
<div id="topbar">
    <div class="page-title"><?= esc($title ?? '') ?></div>
    <div class="d-flex align-items-center gap-3">
        <div class="user-chip">
            <div class="user-avatar">
                <?= strtoupper(substr(session()->get('user_name') ?? 'U', 0, 1)) ?>
            </div>
            <span><?= esc(session()->get('user_name')) ?></span>
        </div>
    </div>
</div>

<!-- Main content -->
<div id="main-content">
    <div class="page-container">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= esc(session()->getFlashdata('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= esc(session()->getFlashdata('error')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
