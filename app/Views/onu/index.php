<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Page Header -->
<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="cpu" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">ONU Terdaftar</h1>
            <p class="text-xs text-slate-500"><?= $totalAll ?> ONU di seluruh OLT</p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <button class="btn btn-secondary btn-sm" id="btnSyncAllNames" title="Sync semua nama dari OLT">
            <i data-lucide="cloud-download" class="w-3.5 h-3.5"></i> Sync Nama
        </button>
        <button class="btn btn-secondary btn-sm" id="btnSyncPppoe" title="Sync PPPoE username dari OLT/ACS ke database">
            <i data-lucide="user-check" class="w-3.5 h-3.5"></i> Sync PPPoE
        </button>
    </div>
</div>

<div class="card !mb-0">
    <?php if (empty($onus) && !$filter && !$q): ?>
        <div class="empty-state">
            <i data-lucide="cpu"></i>
            Belum ada ONU. Scan ONU dari halaman OLT.
            <div class="mt-3"><a href="/olts" class="btn btn-primary btn-sm">Ke Halaman OLT</a></div>
        </div>
    <?php else: ?>
        <div class="pb-3">
            <!-- Filter pills -->
            <div class="flex gap-2 mb-3 flex-wrap">
                <a href="/onus" class="px-3.5 py-1.5 rounded-full text-xs font-semibold border transition <?= !$filter ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
                    Semua <span class="<?= !$filter ? 'text-blue-100' : 'text-slate-400' ?> font-mono"><?= $counts['all'] ?></span>
                </a>
                <a href="/onus?filter=no_pppoe" class="px-3.5 py-1.5 rounded-full text-xs font-semibold border transition <?= $filter === 'no_pppoe' ? 'bg-amber-500 text-white border-amber-500 shadow-xs' : 'bg-white text-amber-600 border-amber-200 hover:bg-amber-50' ?>">
                    Belum PPPoE <span class="<?= $filter === 'no_pppoe' ? 'text-amber-100' : 'text-slate-400' ?> font-mono"><?= $counts['no_pppoe'] ?></span>
                </a>
                <a href="/onus?filter=no_acs" class="px-3.5 py-1.5 rounded-full text-xs font-semibold border transition <?= $filter === 'no_acs' ? 'bg-rose-600 text-white border-rose-600 shadow-xs' : 'bg-white text-rose-600 border-rose-200 hover:bg-rose-50' ?>">
                    Belum di ACS <span class="<?= $filter === 'no_acs' ? 'text-rose-100' : 'text-slate-400' ?> font-mono"><?= $counts['no_acs'] ?></span>
                </a>
            </div>

            <!-- Search -->
            <form method="get" action="/onus" class="flex gap-2 mb-3">
                <?php if ($filter): ?><input type="hidden" name="filter" value="<?= esc($filter) ?>"><?php endif; ?>
                <div class="relative flex-1 max-w-md">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="q" class="form-input pl-9" placeholder="Cari SN, nama, OLT ..." value="<?= esc($q) ?>">
                </div>
                <button class="btn btn-primary btn-sm">Cari</button>
                <?php if ($q || $filter): ?>
                <a href="/onus" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>

            <?php if ($q || $filter): ?>
            <div class="text-xs text-slate-400 mb-2">
                <?php if ($filter === 'no_pppoe'): ?>
                    ONU belum dikonfigurasi PPPoE:
                <?php elseif ($filter === 'no_acs'): ?>
                    ONU belum terdaftar di ACS:
                <?php elseif ($q): ?>
                    Hasil pencarian "<strong class="text-slate-600"><?= esc($q) ?></strong>":
                <?php endif; ?>
                <strong class="text-slate-600"><?= $total ?></strong> ONU
            </div>
            <?php endif; ?>
        </div>

        <?php
        function sortLink(string $col, string $label, string $currentSort, string $currentDir, string $q, int $page, string $filter = ''): string {
            $active  = $currentSort === $col;
            $newDir  = ($active && $currentDir === 'ASC') ? 'DESC' : 'ASC';
            $icon    = $active ? ($currentDir === 'ASC' ? '▲' : '▼') : '<span style="opacity:.3">↕</span>';
            $qs      = http_build_query(array_filter(['sort' => $col, 'dir' => $newDir, 'q' => $q, 'filter' => $filter]));
            $cls     = $active ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600';
            return "<a href=\"/onus?{$qs}\" class=\"text-decoration-none {$cls}\">{$label} <span class=\"text-[9px]\">{$icon}</span></a>";
        }
        ?>
        <div class="table-container">
            <table class="data-table" id="onuTable">
                <thead>
                    <tr>
                        <th><?= sortLink('sn', 'SN', $sort, $dir, $q, $page, $filter) ?></th>
                        <th><?= sortLink('name', 'Nama', $sort, $dir, $q, $page, $filter) ?> <span class="normal-case font-normal tracking-normal opacity-60">(klik untuk edit)</span></th>
                        <th><?= sortLink('olt_name', 'OLT', $sort, $dir, $q, $page, $filter) ?></th>
                        <th>PPPoE Username</th>
                        <th><?= sortLink('onu_type', 'Tipe', $sort, $dir, $q, $page, $filter) ?></th>
                        <th>ACS</th>
                        <th><?= sortLink('registered_at', 'Terdaftar', $sort, $dir, $q, $page, $filter) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($onus as $onu): ?>
                    <?php
                        $acsInfo = $acsData[strtoupper($onu['sn'])] ?? null;
                    ?>
                        <tr data-search="<?= esc(strtolower("{$onu['sn']} {$onu['name']} {$onu['olt_name']}")) ?>">
                            <td class="font-mono text-xs">
                                <a href="/onus/<?= $onu['id'] ?>" class="text-blue-600 hover:underline font-semibold"><?= esc($onu['sn']) ?></a>
                            </td>
                            <td class="onu-name-cell" data-id="<?= $onu['id'] ?>" data-name="<?= esc($onu['name'] ?? '', 'attr') ?>">
                                <span class="onu-name-text text-xs font-medium" style="cursor:pointer" title="Klik untuk edit nama">
                                    <?= esc($onu['name'] ?? '—') ?>
                                </span>
                                <button class="btn btn-secondary btn-sm btn-icon-only sync-name-btn !min-w-[26px] !min-h-[26px] !p-1 align-middle" title="Ambil nama dari OLT">
                                    <i data-lucide="cloud-download" class="w-3 h-3"></i>
                                </button>
                            </td>
                            <td>
                                <a href="/olts/<?= $onu['olt_id'] ?>" class="text-xs font-medium text-slate-700 hover:text-blue-600">
                                    <?= esc($onu['olt_name']) ?>
                                </a>
                                <div class="text-[10px] text-slate-400 font-mono"><?= esc($onu['olt_ip']) ?></div>
                            </td>
                            <?php
                                $snUp = strtoupper($onu['sn']);
                                $pppUser = $acsData[$snUp]['pppoe_user'] ?? $onu['pppoe_user'] ?? null;
                            ?>
                            <td class="font-mono text-xs">
                                <?= $pppUser ? esc($pppUser) : '<span class="text-slate-300">—</span>' ?>
                            </td>
                            <td><span class="chip chip-neutral"><?= esc($onu['onu_type'] ?? '-') ?></span></td>
                            <td>
                                <?php if ($acsInfo): ?>
                                    <?php if ($acsInfo['online']): ?>
                                        <span class="chip chip-success"><span class="pulsing-dot"></span> Online</span>
                                    <?php else: ?>
                                        <?php $lastInf = $acsInfo['last_inform'] ? date('d/m H:i', strtotime($acsInfo['last_inform'])) : '?'; ?>
                                        <span class="chip chip-neutral" title="Last inform: <?= $lastInf ?>">Offline</span>
                                    <?php endif; ?>
                                    <?php if (!empty($acsInfo['model'])): ?>
                                        <div class="text-[10px] text-slate-400"><?= esc($acsInfo['model']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="chip chip-warning">Belum di ACS</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-xs text-slate-400 whitespace-nowrap"><?= date('d/m/y H:i', strtotime($onu['registered_at'])) ?></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="/onus/<?= $onu['id'] ?>" class="btn btn-secondary btn-sm btn-icon-only" title="Detail">
                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                </a>
                                <?php if (strncasecmp($onu['sn'], 'ZTEG', 4) === 0 && !$acsInfo): ?>
                                <button class="btn btn-secondary btn-sm btn-icon-only btn-set-acs ml-1"
                                        data-id="<?= $onu['id'] ?>" data-sn="<?= esc($onu['sn'], 'attr') ?>"
                                        title="Push ACS management ke ONU ini (pon-onu-mng)">
                                    <i data-lucide="radio" class="w-3.5 h-3.5"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm btn-icon-only ml-1"
                                        title="Hapus ONU dari OLT"
                                        onclick="deleteOnu(<?= $onu['id'] ?>, '<?= esc($onu['sn'], 'js') ?>', this)">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $totalPages = (int)ceil($total / $perPage);
        if ($totalPages > 1):
            $extraParams = array_filter(['q' => $q, 'filter' => $filter, 'sort' => ($sort !== 'registered_at' ? $sort : null), 'dir' => ($dir !== 'DESC' ? $dir : null)]);
            $qStr = $extraParams ? '&' . http_build_query($extraParams) : '';
        ?>
        <div class="flex justify-between items-center px-4 py-3 border-t border-slate-100 flex-wrap gap-2">
            <small class="text-xs text-slate-400">
                Menampilkan <strong class="text-slate-600"><?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $total) ?></strong> dari <strong class="text-slate-600"><?= $total ?></strong> ONU
            </small>
            <nav class="flex items-center gap-1">
                <a class="px-2.5 py-1 rounded-lg text-xs font-semibold border transition <?= $page <= 1 ? 'bg-slate-50 text-slate-300 border-slate-100 pointer-events-none' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>" href="/onus?page=1<?= $qStr ?>">«</a>
                <a class="px-2.5 py-1 rounded-lg text-xs font-semibold border transition <?= $page <= 1 ? 'bg-slate-50 text-slate-300 border-slate-100 pointer-events-none' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>" href="/onus?page=<?= $page-1 ?><?= $qStr ?>">‹</a>
                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                if ($start > 1): ?><span class="px-1.5 text-slate-400 text-xs">…</span><?php endif;
                for ($i = $start; $i <= $end; $i++): ?>
                <a class="px-2.5 py-1 rounded-lg text-xs font-semibold border transition <?= $i == $page ? 'bg-blue-600 text-white border-blue-600 shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>" href="/onus?page=<?= $i ?><?= $qStr ?>"><?= $i ?></a>
                <?php endfor;
                if ($end < $totalPages): ?><span class="px-1.5 text-slate-400 text-xs">…</span><?php endif; ?>
                <a class="px-2.5 py-1 rounded-lg text-xs font-semibold border transition <?= $page >= $totalPages ? 'bg-slate-50 text-slate-300 border-slate-100 pointer-events-none' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>" href="/onus?page=<?= $page+1 ?><?= $qStr ?>">›</a>
                <a class="px-2.5 py-1 rounded-lg text-xs font-semibold border transition <?= $page >= $totalPages ? 'bg-slate-50 text-slate-300 border-slate-100 pointer-events-none' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>" href="/onus?page=<?= $totalPages ?><?= $qStr ?>">»</a>
            </nav>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.btn-set-acs').forEach(btn => {
    btn.addEventListener('click', async function() {
        const id = this.dataset.id;
        const sn = this.dataset.sn;
        if (!confirm(`Push ACS management ke ONU ${sn}?\n\nHanya push: service acs + wan-ip 2 dhcp\nPPPoE (wan-ip 1) tidak disentuh.`)) return;
        App.setLoading(this, true, '');
        try {
            const data = await App.readJson(await App.fetch(`/onus/${id}/set-acs`, { method: 'POST' }));
            App.setLoading(this, false);
            if (data.success) {
                this.style.color = 'var(--signal-good)';
                this.style.borderColor = 'rgba(24,128,56,.3)';
                this.title = 'Berhasil dipush ke ONU';
                App.showToast(`Berhasil push ACS ke ${sn}`, 'success');
            } else {
                App.showToast('Gagal: ' + data.message, 'error');
            }
        } catch (e) {
            App.setLoading(this, false);
            App.showToast('Error: ' + e.message, 'error');
        }
    });
});

document.getElementById('btnSyncPppoe')?.addEventListener('click', async function() {
    if (!confirm('Sync PPPoE username dari OLT (ZTE) dan ACS (FH/lainnya) ke database?\nProses ini mungkin memakan waktu beberapa menit.')) return;
    App.setLoading(this, true, 'Syncing...');
    try {
        const data = await App.readJson(await App.fetch('/onus/sync-pppoe-all', { method: 'POST' }));
        App.setLoading(this, false);
        if (data.success) {
            let msg = App.escapeHtml(data.message);
            if (data.errors && data.errors.length) msg += '<br><br>' + data.errors.map(e => '• ' + App.escapeHtml(e)).join('<br>');
            App.showToast(msg, 'success', 8000);
            if (data.updated > 0) setTimeout(() => location.reload(), 2500);
        } else {
            App.showToast('Gagal: ' + data.message, 'error');
        }
    } catch (e) {
        App.setLoading(this, false);
        App.showToast('Error: ' + e.message, 'error');
    }
});

document.getElementById('btnSyncAllNames')?.addEventListener('click', async function() {
    App.setLoading(this, true, 'Syncing... (bisa beberapa menit)');
    try {
        const data = await App.readJson(await App.fetch('/onus/sync-all-names', { method: 'POST' }));
        App.setLoading(this, false);
        if (data.success) {
            let msg = `${data.updated} nama berhasil diupdate dari OLT.`;
            if (data.errors && data.errors.length) msg += '<br>' + data.errors.map(e => '• ' + App.escapeHtml(e)).join('<br>');
            App.showToast(msg, 'success', 8000);
            setTimeout(() => location.reload(), 2500);
        } else {
            App.showToast('Gagal: ' + data.message, 'error');
        }
    } catch (e) {
        App.setLoading(this, false);
        App.showToast('Error: ' + e.message, 'error');
    }
});

document.querySelectorAll('.sync-name-btn').forEach(btn => {
    btn.addEventListener('click', async function(e) {
        e.stopPropagation();
        const cell = this.closest('.onu-name-cell');
        App.setLoading(this, true, '');
        try {
            const data = await App.readJson(await App.fetch(`/onus/${cell.dataset.id}/sync-name`, { method: 'POST' }));
            App.setLoading(this, false);
            if (data.success) {
                cell.dataset.name = data.name;
                cell.querySelector('.onu-name-text').textContent = data.name;
                const tr = cell.closest('tr');
                tr.dataset.search = tr.dataset.search + ' ' + data.name.toLowerCase();
            } else {
                App.showToast('Sync gagal: ' + data.message, 'error');
            }
        } catch (err) {
            App.setLoading(this, false);
            App.showToast('Error: ' + err.message, 'error');
        }
    });
});

document.querySelectorAll('.onu-name-cell').forEach(cell => {
    cell.addEventListener('click', function() {
        if (this.querySelector('input')) return;
        const span = this.querySelector('.onu-name-text');
        const current = this.dataset.name || '';
        const input = document.createElement('input');
        input.type = 'text';
        input.value = current;
        input.className = 'form-input !min-h-[30px] !py-0.5 text-xs';
        input.style.minWidth = '140px';
        span.replaceWith(input);
        input.focus();
        input.select();

        const save = async () => {
            const newName = input.value.trim();
            if (!newName || newName === current) {
                input.replaceWith(createSpan(current));
                return;
            }
            const fd = new FormData();
            fd.append('name', newName);
            try {
                const data = await App.readJson(await App.fetch(`/onus/${cell.dataset.id}/update-info`, { method: 'POST', body: fd }));
                if (data.success) {
                    cell.dataset.name = newName;
                    const tr = cell.closest('tr');
                    tr.dataset.search = tr.dataset.search.replace(current.toLowerCase(), newName.toLowerCase());
                }
                input.replaceWith(createSpan(data.success ? newName : current));
            } catch (e) {
                input.replaceWith(createSpan(current));
            }
        };

        input.addEventListener('blur', save);
        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            if (e.key === 'Escape') { input.removeEventListener('blur', save); input.replaceWith(createSpan(current)); }
        });
    });
});

function createSpan(name) {
    const s = document.createElement('span');
    s.className = 'onu-name-text text-xs font-medium';
    s.style.cursor = 'pointer';
    s.title = 'Klik untuk edit nama';
    s.textContent = name || '—';
    return s;
}

async function deleteOnu(onuId, sn, btn) {
    if (!confirm(`Hapus ONU ${sn} dari OLT?\nAksi ini akan menghapus konfigurasi ONU dari OLT.`)) return;
    App.setLoading(btn, true, '');
    try {
        const data = await App.readJson(await App.fetch(`/onus/${onuId}/delete`, { method: 'POST' }));
        if (data.success) {
            btn.closest('tr').remove();
            App.showToast(`ONU ${sn} dihapus dari OLT`, 'success');
        } else {
            App.setLoading(btn, false);
            App.showToast('Gagal: ' + data.message, 'error');
        }
    } catch (e) {
        App.setLoading(btn, false);
        App.showToast('Error: ' + e.message, 'error');
    }
}
</script>
<?= $this->endSection() ?>
