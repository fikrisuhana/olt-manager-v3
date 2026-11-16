<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$onlineRate = $acs_total > 0 ? round($online_onus / $acs_total * 100) : 0;
$rateClass  = $onlineRate >= 90 ? 'good' : ($onlineRate >= 70 ? 'warning' : 'critical');
$rateBg     = $onlineRate >= 90 ? '#188038' : ($onlineRate >= 70 ? '#b06000' : '#d93025');
?>

<!-- Page Header -->
<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="layout-grid" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Dashboard</h1>
            <p class="text-xs text-slate-500">Ringkasan OLT, ONU &amp; status ACS</p>
        </div>
    </div>
    <button id="btnSyncAcs" class="btn btn-secondary btn-sm" title="Update status online/offline dari GenieACS (cepat, tanpa konek OLT)">
        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Sync ACS
    </button>
</div>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="card stat-card">
        <div class="stat-icon-wrapper bg-blue-50 text-blue-600 border border-blue-100">
            <i data-lucide="network" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-val"><?= $total_olts ?></div>
            <div class="stat-label">OLT Terdaftar</div>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon-wrapper bg-emerald-50 text-emerald-600 border border-emerald-100">
            <i data-lucide="cpu" class="w-5 h-5"></i>
        </div>
        <div>
            <div class="stat-val"><?= $total_onus ?></div>
            <div class="stat-label">ONU Terdaftar</div>
            <?php if ($acs_total > 0): ?>
            <div class="flex gap-1 mt-1">
                <span class="chip chip-success">🟢 <?= $online_onus ?> online</span>
                <span class="chip chip-neutral"><?= $acs_total - $online_onus ?> offline</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon-wrapper <?= $onlineRate >= 90 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : ($onlineRate >= 70 ? 'bg-amber-50 text-amber-600 border-amber-100' : 'bg-rose-50 text-rose-600 border-rose-100') ?>">
            <i data-lucide="wifi" class="w-5 h-5"></i>
        </div>
        <div class="min-w-0 flex-1">
            <div class="stat-val"><?= $onlineRate ?>%</div>
            <div class="stat-label">ACS Online Rate</div>
            <div class="signal-meter mt-1.5" style="max-width:110px">
                <div class="signal-meter-fill" style="width:<?= $onlineRate ?>%;background:<?= $rateBg ?>"></div>
            </div>
        </div>
    </div>

    <div class="card stat-card">
        <div class="stat-icon-wrapper bg-purple-50 text-purple-600 border border-purple-100">
            <i data-lucide="zap" class="w-5 h-5"></i>
        </div>
        <div class="flex-1 flex flex-col gap-1.5">
            <div class="stat-label" style="font-weight:700;color:var(--text-primary)">Aksi Cepat</div>
            <a href="/olts" class="btn btn-primary btn-sm w-full"><i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Scan ONU Baru</a>
            <a href="/onus" class="btn btn-secondary btn-sm w-full"><i data-lucide="list" class="w-3.5 h-3.5"></i> Semua ONU</a>
        </div>
    </div>
</div>

<!-- Hasil sync / refresh -->
<div id="syncAcsResult" class="hidden"></div>

<!-- Alert ONU perlu perhatian -->
<?php if ($no_pppoe > 0 || $no_acs > 0): ?>
<div class="grid md:grid-cols-2 gap-3">
    <?php if ($no_pppoe > 0): ?>
    <div class="p-3.5 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs flex items-center space-x-3 shadow-xs">
        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 shrink-0"></i>
        <div class="flex-1">
            <strong><?= $no_pppoe ?> ONU belum ada PPPoE</strong><br>
            Username PPPoE belum diisi di database.
        </div>
        <a href="/onus?filter=no_pppoe" class="btn btn-sm bg-amber-500 hover:bg-amber-600 text-white border-0">Lihat</a>
    </div>
    <?php endif; ?>
    <?php if ($no_acs > 0): ?>
    <div class="p-3.5 bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs flex items-center space-x-3 shadow-xs">
        <i data-lucide="cloud-off" class="w-5 h-5 text-slate-500 shrink-0"></i>
        <div class="flex-1">
            <strong><?= $no_acs ?> ONU belum di ACS</strong><br>
            Belum pernah connect ke GenieACS.
        </div>
        <a href="/onus?filter=no_acs" class="btn btn-sm btn-secondary">Lihat</a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Per-OLT + Brand -->
<div class="grid lg:grid-cols-3 gap-3 items-start">
    <!-- Status per OLT -->
    <div class="card lg:col-span-2 !mb-0">
        <div class="card-header">
            <div class="card-title"><i data-lucide="network" class="w-4 h-4 text-slate-400"></i> Status per OLT</div>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>OLT</th>
                        <th class="text-center">ONU DB</th>
                        <th class="text-center">ACS Online</th>
                        <th class="text-center">ACS Offline</th>
                        <th>Rate</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="oltStatsBody">
                    <?php foreach ($olt_stats as $s): ?>
                    <?php
                        $r = $s['acs_total'] > 0 ? round($s['acs_online'] / $s['acs_total'] * 100) : null;
                        $rc = $r === null ? '#64748b' : ($r >= 90 ? '#188038' : ($r >= 70 ? '#b06000' : '#d93025'));
                    ?>
                    <tr>
                        <td>
                            <a href="/olts/<?= $s['olt']['id'] ?>" class="font-semibold text-blue-600 hover:underline">
                                <?= esc($s['olt']['name']) ?>
                            </a>
                            <div class="text-[10px] text-slate-400 font-mono"><?= esc($s['olt']['ip']) ?> · <?= esc($s['olt']['brand']) ?></div>
                        </td>
                        <td class="text-center">
                            <span class="chip chip-neutral"><?= $s['onu_count'] ?></span>
                        </td>
                        <td class="text-center acs-online-cell">
                            <?php if ($s['acs_online'] > 0): ?>
                            <span class="chip chip-success">🟢 <?= $s['acs_online'] ?></span>
                            <?php else: ?>
                            <span class="text-slate-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center acs-offline-cell">
                            <?php if ($s['acs_offline'] > 0): ?>
                            <span class="chip chip-neutral"><?= $s['acs_offline'] ?></span>
                            <?php else: ?>
                            <span class="text-slate-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="min-width:110px">
                            <?php if ($r !== null): ?>
                            <div class="flex items-center gap-2">
                                <div class="signal-meter flex-1">
                                    <div class="signal-meter-fill" style="width:<?= $r ?>%;background:<?= $rc ?>"></div>
                                </div>
                                <span class="text-[11px] font-bold mono" style="color:<?= $rc ?>"><?= $r ?>%</span>
                            </div>
                            <?php else: ?>
                            <span class="text-[11px] text-slate-400">Belum ada ACS</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <button class="btn btn-secondary btn-sm btn-icon-only btn-refresh-olt" data-olt-id="<?= $s['olt']['id'] ?>" title="Full refresh: scan OLT via Telnet + update ACS (lambat)">
                                <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Distribusi brand -->
    <div class="card !mb-0">
        <div class="card-header">
            <div class="card-title"><i data-lucide="chart-pie" class="w-4 h-4 text-slate-400"></i> Distribusi Brand ONU</div>
        </div>
        <div>
            <?php
            $total = array_sum($brands);
            $colors = ['ZTE' => '#2563eb', 'Fiberhome' => '#16a34a', 'Nokia' => '#0891b2', 'Huawei' => '#dc2626', 'Lainnya' => '#9ca3af'];
            ?>
            <?php foreach ($brands as $brand => $count): ?>
            <?php $pct = $total > 0 ? round($count / $total * 100) : 0; ?>
            <?php $color = $colors[$brand] ?? '#9ca3af'; ?>
            <div class="mb-3">
                <div class="flex justify-between mb-1 text-xs">
                    <span class="font-semibold"><?= esc($brand) ?></span>
                    <span class="text-slate-400"><?= $count ?> ONU (<?= $pct ?>%)</span>
                </div>
                <div class="signal-meter" style="height:8px">
                    <div class="signal-meter-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($brands)): ?>
            <div class="empty-state">
                <i data-lucide="inbox"></i>
                Belum ada data ONU.
            </div>
            <?php endif; ?>
            <div class="border-t border-slate-100 pt-2 mt-2 flex justify-between text-xs">
                <span class="text-slate-400">Total ONU</span>
                <span class="font-bold"><?= $total ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Log terbaru -->
<div class="card !mb-0">
    <div class="card-header">
        <div class="card-title"><i data-lucide="history" class="w-4 h-4 text-slate-400"></i> Log Provisioning Terbaru</div>
        <a href="/admin/logs" class="btn btn-secondary btn-sm">Semua Log</a>
    </div>
    <?php if (empty($recent_logs)): ?>
        <div class="empty-state">
            <i data-lucide="inbox"></i>
            Belum ada aktivitas provisioning.
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Aksi</th>
                        <th>ONU / SN</th>
                        <th>OLT</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                    <tr>
                        <td class="text-slate-400 text-xs whitespace-nowrap"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                        <td><span class="chip chip-neutral"><?= esc($log['action']) ?></span></td>
                        <td class="font-mono text-xs">
                            <?php if (!empty($log['onu_id'])): ?>
                            <a href="/onus/<?= $log['onu_id'] ?>" class="text-blue-600 hover:underline">
                                <?= esc($log['sn'] ?? '-') ?>
                            </a>
                            <?php else: ?>
                            <?= esc($log['sn'] ?? '-') ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-xs"><?= esc($log['olt_name'] ?? '-') ?></td>
                        <td>
                            <?php if ($log['status'] === 'success'): ?>
                            <span class="chip chip-success">OK</span>
                            <?php else: ?>
                            <span class="chip chip-danger">Gagal</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
// Sync ACS semua OLT (cepat)
document.getElementById('btnSyncAcs')?.addEventListener('click', async function() {
    const btn = this;
    const res = document.getElementById('syncAcsResult');
    App.setLoading(btn, true, 'Syncing...');
    res.className = 'p-3 bg-blue-50 border border-blue-100 text-blue-700 rounded-xl text-xs';
    res.textContent = 'Menghubungi GenieACS...';

    try {
        const data = await App.readJson(await App.fetch('/dashboard/sync-acs', { method: 'POST' }));
        App.setLoading(btn, false);
        if (data.success) {
            res.className = 'p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs';
            res.innerHTML = `✅ ${App.escapeHtml(data.message)} — <a href="" class="font-bold underline">Muat ulang halaman</a> untuk lihat perubahan.`;
        } else {
            res.className = 'p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs';
            res.textContent = 'Gagal: ' + data.message;
        }
    } catch (e) {
        App.setLoading(btn, false);
        res.className = 'p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs';
        res.textContent = 'Error: ' + e.message;
    }
});

// Full refresh per OLT (berat — konek Telnet + ACS)
document.querySelectorAll('.btn-refresh-olt').forEach(btn => {
    btn.addEventListener('click', async function() {
        const oltId = this.dataset.oltId;
        if (!confirm('Full refresh OLT ini?\nAkan konek ke OLT via Telnet dan update ACS cache.\nProses bisa memakan waktu 1-3 menit.')) return;
        App.setLoading(this, true, '');
        try {
            const data = await App.readJson(await App.fetch(`/olts/${oltId}/refresh-cache`));
            App.setLoading(this, false);
            if (data.success) {
                const res = document.getElementById('syncAcsResult');
                res.className = 'p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs';
                res.innerHTML = `✅ ${App.escapeHtml(data.message)} — <a href="" class="font-bold underline">Muat ulang halaman</a>.`;
            } else {
                App.showToast('Gagal: ' + data.message, 'error');
            }
        } catch (e) {
            App.setLoading(this, false);
            App.showToast('Error: ' + e.message, 'error');
        }
    });
});
</script>
<?= $this->endSection() ?>
