<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Daftar — GPON Manager</title>
    <!-- Aset lokal (offline) — build: `npm run build`, lihat package.json -->
    <link rel="preload" href="/assets/fonts/inter-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/tailwind.css">
    <script src="/assets/lib/lucide.min.js"></script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-xl p-8 space-y-6">
        <!-- Brand Header -->
        <div class="flex items-center space-x-3.5 pb-4 border-b border-slate-100">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-600 to-purple-500 flex items-center justify-center text-white shadow-xs shrink-0">
                <i data-lucide="radio" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-base font-extrabold text-slate-900 leading-tight">GPON MANAGER</h1>
                <p class="text-xs text-slate-500 font-medium">Daftar akun baru</p>
            </div>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-medium flex items-center space-x-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-500"></i>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>

        <form action="/register/save" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label for="name" class="block text-xs font-semibold text-slate-700 mb-1.5">Nama Lengkap</label>
                <div class="relative">
                    <i data-lucide="id-card" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="name" name="name" required value="<?= esc(old('name')) ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition placeholder-slate-400"
                           placeholder="Nama lengkap">
                </div>
            </div>
            <div>
                <label for="username" class="block text-xs font-semibold text-slate-700 mb-1.5">Username</label>
                <div class="relative">
                    <i data-lucide="user" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="username" name="username" required value="<?= esc(old('username')) ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition placeholder-slate-400"
                           placeholder="Username">
                </div>
            </div>
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">Email</label>
                <div class="relative">
                    <i data-lucide="mail" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="email" id="email" name="email" required value="<?= esc(old('email')) ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition placeholder-slate-400"
                           placeholder="email@domain.com">
                </div>
            </div>
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="password" id="password" name="password" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition placeholder-slate-400"
                           placeholder="Min. 6 karakter">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-xl shadow-xs hover:shadow-md transition flex items-center justify-center space-x-2">
                <span>Daftar</span>
                <i data-lucide="user-plus" class="w-4 h-4"></i>
            </button>

            <div class="text-center pt-2">
                <p class="text-[11px] text-slate-400">Sudah punya akun? <a href="/login" class="font-semibold text-blue-600 hover:underline">Login</a></p>
            </div>
        </form>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
