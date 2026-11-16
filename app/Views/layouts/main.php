<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'GPON Manager') ?> — GPON Manager</title>

    <!-- Aset lokal (offline) — build: `npm run build`, lihat package.json -->
    <link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <script src="/assets/vendor/lucide.min.js"></script>
</head>
<body class="bg-[#f8fafc] text-slate-800 min-h-screen flex font-sans antialiased text-sm">

<div class="flex-1 flex w-full min-h-screen">

    <!-- ================= SIDEBAR ================= -->
    <!-- Backdrop drawer (HP) -->
    <div id="sidebarBackdrop" class="fixed inset-0 bg-slate-900/40 z-30 hidden md:hidden" onclick="toggleSidebar(false)"></div>

    <aside id="sidebar" class="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0 h-screen z-40 fixed inset-y-0 left-0 -translate-x-full transition-transform duration-200 md:translate-x-0 md:sticky md:top-0">
        <!-- Brand Header -->
        <div class="h-16 px-5 flex items-center space-x-3 border-b border-slate-100 shrink-0">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-brand-600 to-purple-500 flex items-center justify-center text-white shadow-xs shrink-0">
                <i data-lucide="radio" class="w-[18px] h-[18px]"></i>
            </div>
            <div class="min-w-0">
                <span class="font-bold text-slate-900 text-base tracking-tight leading-tight block truncate">GPON Manager</span>
                <span class="text-[11px] text-slate-400 font-medium block">OLT &amp; ACS Management</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="p-3 space-y-1 flex-1 overflow-y-auto no-scrollbar">
            <div class="section-label">Menu Utama</div>

            <a href="/dashboard" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= uri_string() === 'dashboard' ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                <i data-lucide="layout-grid" class="w-4 h-4 <?= uri_string() === 'dashboard' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                <span>Dashboard</span>
            </a>
            <a href="/olts" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= str_starts_with(uri_string(), 'olts') && !str_contains(uri_string(), '/settings') && !str_contains(uri_string(), '/migration') ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                <i data-lucide="network" class="w-4 h-4 <?= str_starts_with(uri_string(), 'olts') && !str_contains(uri_string(), '/settings') && !str_contains(uri_string(), '/migration') ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                <span>Manajemen OLT</span>
            </a>
            <a href="/onus" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= str_starts_with(uri_string(), 'onus') ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                <i data-lucide="cpu" class="w-4 h-4 <?= str_starts_with(uri_string(), 'onus') ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                <span>ONU Terdaftar</span>
            </a>
            <a href="/migration" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= str_starts_with(uri_string(), 'migration') || str_contains(uri_string(), '/migration') ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                <i data-lucide="refresh-cw" class="w-4 h-4 <?= str_starts_with(uri_string(), 'migration') || str_contains(uri_string(), '/migration') ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                <span>Migrasi Massal</span>
            </a>
            <a href="/olt-settings" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= (str_starts_with(uri_string(), 'olt-settings') || str_ends_with(uri_string(), '/settings')) ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                <i data-lucide="wrench" class="w-4 h-4 <?= (str_starts_with(uri_string(), 'olt-settings') || str_ends_with(uri_string(), '/settings')) ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                <span>Setting OLT</span>
            </a>

            <div class="section-label">Pengaturan</div>

            <a href="/templates" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= str_starts_with(uri_string(), 'templates') ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                <i data-lucide="file-code" class="w-4 h-4 <?= str_starts_with(uri_string(), 'templates') ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                <span>Template Config</span>
            </a>
            <a href="/acs" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= str_starts_with(uri_string(), 'acs') ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                <i data-lucide="cloud" class="w-4 h-4 <?= str_starts_with(uri_string(), 'acs') ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                <span>ACS Server</span>
            </a>

            <?php if (session()->get('user_role') === 'admin'): ?>
                <div class="section-label">Administrator</div>
                <a href="/admin/users" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= str_starts_with(uri_string(), 'admin/users') ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i data-lucide="users" class="w-4 h-4 <?= str_starts_with(uri_string(), 'admin/users') ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                    <span>Kelola User</span>
                </a>
                <a href="/admin/olts" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= uri_string() === 'admin/olts' ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i data-lucide="hard-drive" class="w-4 h-4 <?= uri_string() === 'admin/olts' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                    <span>Semua OLT</span>
                </a>
                <a href="/admin/acs" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= uri_string() === 'admin/acs' ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i data-lucide="server" class="w-4 h-4 <?= uri_string() === 'admin/acs' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                    <span>Semua ACS</span>
                </a>
                <a href="/admin/logs" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition <?= uri_string() === 'admin/logs' ? 'text-blue-600 bg-blue-50/80 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50' ?>">
                    <i data-lucide="scroll-text" class="w-4 h-4 <?= uri_string() === 'admin/logs' ? 'text-blue-600' : 'text-slate-400' ?>"></i>
                    <span>Log Global</span>
                </a>
            <?php endif; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-slate-100 space-y-2 shrink-0">
            <a href="/logout" class="w-full flex items-center justify-center space-x-2 py-2 text-xs text-rose-600 hover:bg-rose-50 rounded-lg transition font-semibold">
                <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                <span>Keluar</span>
            </a>
            <div class="text-[10px] text-slate-400 flex justify-between items-center px-1 font-medium">
                <span>GPON Manager</span>
                <span>OLT &amp; ACS</span>
            </div>
        </div>
    </aside>

    <!-- ================= MAIN CONTENT AREA ================= -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- Top Header Bar -->
        <header class="h-16 bg-white border-b border-slate-200 px-4 md:px-8 flex items-center justify-between sticky top-0 z-10 shrink-0">
            <!-- Left: Breadcrumb -->
            <div class="flex items-center space-x-2 text-xs text-slate-500 min-w-0">
                <button type="button" class="md:hidden -ml-1 mr-1 p-2 rounded-lg text-slate-600 hover:bg-slate-100 shrink-0" onclick="toggleSidebar()" aria-label="Buka menu">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>
                <span class="font-bold text-slate-800 text-sm truncate">GPON Manager</span>
                <span class="hidden sm:inline">/</span>
                <span class="font-semibold text-slate-700 truncate hidden sm:inline"><?= esc($title ?? 'Dashboard') ?></span>
            </div>

            <!-- Right: User Badge -->
            <div class="flex items-center space-x-2 pl-2 border-l border-slate-200">
                <div class="w-8 h-8 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center shadow-xs uppercase" title="<?= esc(session()->get('user_role') ?? 'user') ?>">
                    <?= strtoupper(substr(session()->get('user_name') ?? 'U', 0, 1)) ?>
                </div>
                <div class="hidden lg:block text-left">
                    <div class="text-xs font-bold text-slate-900 leading-tight"><?= esc(session()->get('user_name')) ?></div>
                    <div class="text-[10px] text-slate-400 font-medium capitalize"><?= esc(session()->get('user_role') ?? 'user') ?></div>
                </div>
            </div>
        </header>

        <!-- Content Body -->
        <main class="flex-1 p-4 md:p-8 max-w-[1440px] w-full mx-auto space-y-6">
            <?= $this->renderSection('content') ?>
        </main>
    </div>
</div>

<!-- Toast Notification Hub -->
<div id="toastContainer" class="toast-container"></div>

<script src="/assets/js/app.js"></script>
<script>
    // CSRF token untuk App.fetch()
    App.csrf = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };

    // Sidebar drawer untuk layar kecil (< md). Di desktop sidebar selalu tampil.
    function toggleSidebar(open) {
        const sb = document.getElementById('sidebar');
        const bd = document.getElementById('sidebarBackdrop');
        const show = open ?? sb.classList.contains('-translate-x-full');
        sb.classList.toggle('-translate-x-full', !show);
        bd.classList.toggle('hidden', !show);
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') toggleSidebar(false); });

    // Inisialisasi Lucide Icons
    document.addEventListener("DOMContentLoaded", () => {
        if (window.lucide) {
            lucide.createIcons();
        }
    });

    // Flashdata CI4 → toast
    <?php if (session()->getFlashdata('success')): ?>
        App.showToast(<?= json_encode(session()->getFlashdata('success')) ?>, 'success');
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        App.showToast(<?= json_encode(session()->getFlashdata('error')) ?>, 'error', 6000);
    <?php endif; ?>
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
