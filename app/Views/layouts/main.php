<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'GPON Manager') ?> — GPON Manager</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-width: 240px;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #3b82f6;
            --topbar-h: 56px;
        }
        body { background: #f1f5f9; }
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            z-index: 1040;
            display: flex;
            flex-direction: column;
        }
        #sidebar .brand {
            height: var(--topbar-h);
            display: flex; align-items: center; gap: 10px;
            padding: 0 20px;
            color: #fff;
            font-weight: 700;
            font-size: 1.05rem;
            border-bottom: 1px solid #1e293b;
            text-decoration: none;
        }
        #sidebar .brand span { color: #3b82f6; }
        #sidebar .nav-label {
            font-size: .68rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #64748b;
            padding: 16px 20px 4px;
        }
        #sidebar .nav-link {
            color: #94a3b8;
            padding: 9px 20px;
            border-radius: 6px;
            margin: 1px 8px;
            display: flex; align-items: center; gap: 10px;
            font-size: .875rem;
            transition: background .15s, color .15s;
        }
        #sidebar .nav-link:hover { background: var(--sidebar-hover); color: #e2e8f0; }
        #sidebar .nav-link.active { background: var(--sidebar-active); color: #fff; }
        #sidebar .nav-link i { font-size: 1rem; width: 18px; text-align: center; }
        #sidebar .sidebar-footer {
            margin-top: auto;
            padding: 12px;
            border-top: 1px solid #1e293b;
        }
        #topbar {
            height: var(--topbar-h);
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 24px;
            position: fixed;
            top: 0; left: var(--sidebar-width); right: 0;
            z-index: 1030;
        }
        #main-content {
            margin-left: var(--sidebar-width);
            padding-top: calc(var(--topbar-h) + 24px);
            padding-bottom: 40px;
            min-height: 100vh;
        }
        .page-container { padding: 0 24px; }
        .stat-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .badge-brand-zte { background: #3b82f6; }
        .badge-brand-fiberhome { background: #10b981; }
        .badge-brand-huawei { background: #f59e0b; }
        .signal-good { color: #10b981; }
        .signal-warn { color: #f59e0b; }
        .signal-bad  { color: #ef4444; }
        .table th { font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
        pre.cli-output {
            background: #0f172a;
            color: #86efac;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: .8rem;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
    <a href="/dashboard" class="brand">
        <i class="bi bi-broadcast-pin"></i>
        GPON <span>Manager</span>
    </a>

    <div class="nav-label">Menu</div>

    <a href="/dashboard" class="nav-link <?= uri_string() === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="/olts" class="nav-link <?= str_starts_with(uri_string(), 'olts') ? 'active' : '' ?>">
        <i class="bi bi-hdd-network"></i> OLT
    </a>
    <a href="/onus" class="nav-link <?= str_starts_with(uri_string(), 'onus') ? 'active' : '' ?>">
        <i class="bi bi-router"></i> ONU Terdaftar
    </a>

    <div class="nav-label">Pengaturan</div>

    <a href="/templates" class="nav-link <?= str_starts_with(uri_string(), 'templates') ? 'active' : '' ?>">
        <i class="bi bi-file-code"></i> Template Config
    </a>
    <a href="/acs" class="nav-link <?= str_starts_with(uri_string(), 'acs') ? 'active' : '' ?>">
        <i class="bi bi-cloud-check"></i> ACS Server
    </a>

    <?php if (session()->get('user_role') === 'admin'): ?>
        <div class="nav-label">Admin</div>
        <a href="/admin/users" class="nav-link <?= str_starts_with(uri_string(), 'admin/users') ? 'active' : '' ?>">
            <i class="bi bi-people"></i> Users
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
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>
</nav>

<!-- Topbar -->
<div id="topbar">
    <h6 class="mb-0 fw-semibold text-dark"><?= esc($title ?? '') ?></h6>
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-person-circle text-secondary"></i>
        <span class="small text-secondary"><?= esc(session()->get('user_name')) ?></span>
    </div>
</div>

<!-- Main content -->
<div id="main-content">
    <div class="page-container">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1"></i>
                <?= esc(session()->getFlashdata('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-1"></i>
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
