<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="scroll-text" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Log Aktivitas Global</h1>
            <p class="text-xs text-slate-500">Aktivitas provisioning semua user</p>
        </div>
    </div>
    <span class="chip chip-neutral font-mono">200 log terakhir</span>
</div>

<div class="card !mb-0">
    <div class="table-container scroll-slim">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>User</th>
                    <th>OLT</th>
                    <th>SN ONU</th>
                    <th>Aksi</th>
                    <th>Status</th>
                    <th>Pesan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-slate-400 py-6 text-xs">Belum ada log.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-xs text-slate-400 whitespace-nowrap">
                        <?= date('d/m H:i', strtotime($log['created_at'])) ?>
                    </td>
                    <td class="text-xs"><?= esc($log['username'] ?? '—') ?></td>
                    <td class="text-xs"><?= esc($log['olt_name'] ?? '—') ?></td>
                    <td><span class="font-mono text-xs"><?= esc($log['sn'] ?? '—') ?></span></td>
                    <td><span class="chip chip-neutral"><?= esc($log['action'] ?? '') ?></span></td>
                    <td>
                        <?php
                            $status = $log['status'] ?? '';
                            $cls = match($status) {
                                'success' => 'chip-success',
                                'failed'  => 'chip-danger',
                                default   => 'chip-neutral',
                            };
                        ?>
                        <span class="chip <?= $cls ?>"><?= esc($status) ?></span>
                    </td>
                    <td class="text-xs text-slate-400"><?= esc($log['message'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
