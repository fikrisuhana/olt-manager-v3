<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Masuk — GPON Manager</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        brand: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' }
                    }
                }
            }
        }
    </script>
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
                <p class="text-xs text-slate-500 font-medium">OLT Provisioning System</p>
            </div>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-medium flex items-center space-x-2">
                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 text-rose-500"></i>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-700 font-medium flex items-center space-x-2">
                <i data-lucide="check-circle" class="w-4 h-4 shrink-0 text-emerald-500"></i>
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="/login/process" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label for="username" class="block text-xs font-semibold text-slate-700 mb-1.5">Username</label>
                <div class="relative">
                    <i data-lucide="user" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="username" name="username" autocomplete="username" required autofocus
                           value="<?= esc(old('username')) ?>"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition placeholder-slate-400"
                           placeholder="Masukkan username">
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="password" id="password" name="password" autocomplete="current-password" required
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition placeholder-slate-400"
                           placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-xl shadow-xs hover:shadow-md transition flex items-center justify-center space-x-2">
                <span>Masuk ke Dashboard</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>

            <div class="text-center pt-2">
                <p class="text-[11px] text-slate-400">Belum punya akun? <a href="/register" class="font-semibold text-blue-600 hover:underline">Daftar</a></p>
            </div>
        </form>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
