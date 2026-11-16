<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="server" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Semua ACS Server</h1>
            <p class="text-xs text-slate-500">Seluruh GenieACS milik semua user</p>
        </div>
    </div>
    <span class="chip chip-neutral font-mono"><?= count($servers) ?> ACS</span>
</div>

<div class="card !mb-0">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>URL / Port</th>
                    <th>Default</th>
                    <th>Pemilik</th>
                    <th>Dibuat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($servers)): ?>
                    <tr><td colspan="5" class="text-center text-slate-400 py-6 text-xs">Belum ada ACS server terdaftar.</td></tr>
                <?php endif; ?>
                <?php foreach ($servers as $s): ?>
                <tr>
                    <td class="font-medium text-xs"><?= esc($s['name']) ?></td>
                    <td><span class="font-mono text-xs"><?= esc($s['url']) ?></span></td>
                    <td>
                        <?php if ($s['is_default']): ?>
                            <span class="chip chip-success">★ Default</span>
                        <?php else: ?>
                            <span class="text-slate-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="text-xs"><?= esc($s['user_name']) ?></div>
                        <div class="text-[10px] text-slate-400">@<?= esc($s['username']) ?></div>
                    </td>
                    <td class="text-xs text-slate-400"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
