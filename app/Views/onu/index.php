<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-router me-1"></i>Semua ONU Terdaftar</h6>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary" title="Total semua ONU"><?= $totalAll ?></span>
            <button class="btn btn-sm btn-outline-secondary" id="btnSyncAllNames" title="Sync semua nama dari OLT">
                <i class="bi bi-cloud-download me-1"></i>Sync Nama
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btnSyncPppoe" title="Sync PPPoE username dari OLT/ACS ke database">
                <i class="bi bi-person-check me-1"></i>Sync PPPoE
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($onus) && !$filter && !$q): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-router fs-1 d-block mb-2"></i>
                Belum ada ONU. Scan ONU dari halaman OLT.
                <div class="mt-2"><a href="/olts" class="btn btn-primary btn-sm">Ke Halaman OLT</a></div>
            </div>
        <?php else: ?>
            <div class="p-3 pb-0">
                <!-- Filter tabs -->
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <a href="/onus" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        Semua <span class="badge bg-white text-dark ms-1"><?= $counts['all'] ?></span>
                    </a>
                    <a href="/onus?filter=no_pppoe" class="btn btn-sm <?= $filter === 'no_pppoe' ? 'btn-warning' : 'btn-outline-warning' ?>">
                        Belum PPPoE <span class="badge bg-white text-dark ms-1"><?= $counts['no_pppoe'] ?></span>
                    </a>
                    <a href="/onus?filter=no_acs" class="btn btn-sm <?= $filter === 'no_acs' ? 'btn-danger' : 'btn-outline-danger' ?>">
                        Belum di ACS <span class="badge bg-white text-dark ms-1"><?= $counts['no_acs'] ?></span>
                    </a>
                </div>
                <form method="get" action="/onus" class="d-flex gap-2 mb-3">
                    <?php if ($filter): ?><input type="hidden" name="filter" value="<?= esc($filter) ?>"><?php endif; ?>
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Cari SN, nama, OLT ..."
                           value="<?= esc($q) ?>">
                    <button class="btn btn-sm btn-outline-primary px-3">Cari</button>
                    <?php if ($q || $filter): ?>
                    <a href="/onus" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </form>
                <?php if ($q || $filter): ?>
                <div class="small text-muted mb-2">
                    <?php if ($filter === 'no_pppoe'): ?>
                        ONU belum dikonfigurasi PPPoE:
                    <?php elseif ($filter === 'no_acs'): ?>
                        ONU belum terdaftar di ACS:
                    <?php elseif ($q): ?>
                        Hasil pencarian "<strong><?= esc($q) ?></strong>":
                    <?php endif; ?>
                    <strong><?= $total ?></strong> ONU
                </div>
                <?php endif; ?>
            </div>
            <?php
            function sortLink(string $col, string $label, string $currentSort, string $currentDir, string $q, int $page, string $filter = ''): string {
                $active  = $currentSort === $col;
                $newDir  = ($active && $currentDir === 'ASC') ? 'DESC' : 'ASC';
                $icon    = $active ? ($currentDir === 'ASC' ? '↑' : '↓') : '<span style="opacity:.3">↕</span>';
                $qs      = http_build_query(array_filter(['sort' => $col, 'dir' => $newDir, 'q' => $q, 'filter' => $filter]));
                $cls     = $active ? 'fw-semibold text-primary' : 'text-dark';
                return "<a href=\"/onus?{$qs}\" class=\"text-decoration-none {$cls}\">{$label} {$icon}</a>";
            }
            ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="onuTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3"><?= sortLink('sn', 'SN', $sort, $dir, $q, $page, $filter) ?></th>
                            <th><?= sortLink('name', 'Nama', $sort, $dir, $q, $page, $filter) ?> <small class="text-muted fw-normal">(klik untuk edit)</small></th>
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
                                <td class="font-monospace small ps-3">
                                    <a href="/onus/<?= $onu['id'] ?>" class="text-decoration-none"><?= esc($onu['sn']) ?></a>
                                </td>
                                <td class="onu-name-cell" data-id="<?= $onu['id'] ?>" data-name="<?= esc($onu['name'] ?? '', 'attr') ?>">
                                    <span class="onu-name-text" style="cursor:pointer" title="Klik untuk edit nama">
                                        <?= esc($onu['name'] ?? '—') ?>
                                    </span>
                                    <button class="btn btn-link btn-sm p-0 ms-1 text-muted sync-name-btn"
                                            title="Ambil nama dari OLT" style="font-size:.7rem;vertical-align:middle">
                                        <i class="bi bi-cloud-download"></i>
                                    </button>
                                </td>
                                <td>
                                    <a href="/olts/<?= $onu['olt_id'] ?>" class="text-decoration-none small">
                                        <?= esc($onu['olt_name']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size:.7rem"><?= esc($onu['olt_ip']) ?></div>
                                </td>
                                <?php
                                    $snUp = strtoupper($onu['sn']);
                                    $pppUser = $acsData[$snUp]['pppoe_user'] ?? $onu['pppoe_user'] ?? null;
                                ?>
                                <td class="small">
                                    <?= $pppUser ? esc($pppUser) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td><span class="badge bg-light text-dark"><?= esc($onu['onu_type'] ?? '-') ?></span></td>
                                <td>
                                    <?php if ($acsInfo): ?>
                                        <?php if ($acsInfo['online']): ?>
                                            <span class="badge bg-success"><i class="bi bi-wifi me-1"></i>Online</span>
                                        <?php else: ?>
                                            <?php $lastInf = $acsInfo['last_inform'] ? date('d/m H:i', strtotime($acsInfo['last_inform'])) : '?'; ?>
                                            <span class="badge bg-secondary" title="Last inform: <?= $lastInf ?>">
                                                <i class="bi bi-wifi-off me-1"></i>Offline
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($acsInfo['model'])): ?>
                                            <div class="text-muted" style="font-size:.7rem"><?= esc($acsInfo['model']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('d/m/y H:i', strtotime($onu['registered_at'])) ?></td>
                                <td class="text-nowrap">
                                    <a href="/onus/<?= $onu['id'] ?>" class="btn btn-sm btn-outline-secondary py-0" title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if (strncasecmp($onu['sn'], 'ZTEG', 4) === 0 && !$acsInfo): ?>
                                    <button class="btn btn-sm btn-outline-primary py-0 ms-1 btn-set-acs"
                                            data-id="<?= $onu['id'] ?>" data-sn="<?= esc($onu['sn'], 'attr') ?>"
                                            title="Push ACS management ke ONU ini (pon-onu-mng)">
                                        <i class="bi bi-broadcast"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger py-0 ms-1"
                                            title="Hapus ONU dari OLT"
                                            onclick="deleteOnu(<?= $onu['id'] ?>, '<?= esc($onu['sn'], 'js') ?>', this)">
                                        <i class="bi bi-trash"></i>
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
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Menampilkan <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $total) ?> dari <?= $total ?> ONU
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $page<=1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="/onus?page=<?= $page-1 ?><?= $qStr ?>">‹</a>
                        </li>
                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($totalPages, $page + 2);
                        if ($start > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
                        for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i==$page ? 'active' : '' ?>">
                            <a class="page-link" href="/onus?page=<?= $i ?><?= $qStr ?>"><?= $i ?></a>
                        </li>
                        <?php endfor;
                        if ($end < $totalPages): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <li class="page-item <?= $page>=$totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="/onus?page=<?= $page+1 ?><?= $qStr ?>">›</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
.spin { display:inline-block; animation: spin .7s linear infinite; }
</style>
<script>
const _csrf = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };


document.querySelectorAll('.btn-set-acs').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const sn = this.dataset.sn;
        if (!confirm(`Push ACS management ke ONU ${sn}?\n\nHanya push: service acs + wan-ip 2 dhcp\nPPPoE (wan-ip 1) tidak disentuh.`)) return;
        const origHtml = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        const fd = new FormData();
        fd.append(_csrf.name, _csrf.hash);
        fetch(`/onus/${id}/set-acs`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                this.disabled = false;
                this.innerHTML = origHtml;
                if (data.success) {
                    this.classList.replace('btn-outline-primary', 'btn-outline-success');
                    this.title = 'Berhasil dipush ke ONU';
                } else {
                    alert('Gagal: ' + data.message);
                }
            })
            .catch(() => { this.disabled = false; this.innerHTML = origHtml; });
    });
});

document.getElementById('btnSyncPppoe')?.addEventListener('click', function() {
    if (!confirm('Sync PPPoE username dari OLT (ZTE) dan ACS (FH/lainnya) ke database?\nProses ini mungkin memakan waktu beberapa menit.')) return;
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...';
    const fd = new FormData();
    fd.append(_csrf.name, _csrf.hash);
    fetch('/onus/sync-pppoe-all', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-person-check me-1"></i>Sync PPPoE';
            if (data.success) {
                let msg = data.message;
                if (data.errors && data.errors.length) msg += '\n\nError:\n' + data.errors.join('\n');
                alert(msg);
                if (data.updated > 0) location.reload();
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-person-check me-1"></i>Sync PPPoE';
        });
});

document.getElementById('btnSyncAllNames')?.addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing... (bisa beberapa menit)';
    const fd = new FormData();
    fd.append(_csrf.name, _csrf.hash);
    fetch('/onus/sync-all-names', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Sync Semua Nama';
            if (data.success) {
                let msg = `${data.updated} nama berhasil diupdate dari OLT.`;
                if (data.errors && data.errors.length) msg += '\n\nError:\n' + data.errors.join('\n');
                alert(msg);
                location.reload();
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(() => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Sync Semua Nama';
        });
});

document.querySelectorAll('.sync-name-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const cell = this.closest('.onu-name-cell');
        const icon = this.querySelector('i');
        icon.className = 'bi bi-arrow-repeat spin';
        this.disabled = true;

        const fd = new FormData();
        fd.append(_csrf.name, _csrf.hash);
        fetch(`/onus/${cell.dataset.id}/sync-name`, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                icon.className = 'bi bi-cloud-download';
                this.disabled = false;
                if (data.success) {
                    cell.dataset.name = data.name;
                    cell.querySelector('.onu-name-text').textContent = data.name;
                    const tr = cell.closest('tr');
                    tr.dataset.search = tr.dataset.search + ' ' + data.name.toLowerCase();
                } else {
                    alert('Sync gagal: ' + data.message);
                }
            })
            .catch(() => { icon.className = 'bi bi-cloud-download'; this.disabled = false; });
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
        input.className = 'form-control form-control-sm py-0';
        input.style.minWidth = '140px';
        span.replaceWith(input);
        input.focus();
        input.select();

        const save = () => {
            const newName = input.value.trim();
            if (!newName || newName === current) {
                input.replaceWith(createSpan(current));
                return;
            }
            const fd = new FormData();
            fd.append('name', newName);
            fd.append(_csrf.name, _csrf.hash);
            fetch(`/onus/${cell.dataset.id}/update-info`, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        cell.dataset.name = newName;
                        const tr = cell.closest('tr');
                        tr.dataset.search = tr.dataset.search.replace(current.toLowerCase(), newName.toLowerCase());
                    }
                    input.replaceWith(createSpan(data.success ? newName : current));
                })
                .catch(() => input.replaceWith(createSpan(current)));
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
    s.className = 'onu-name-text text-muted fst-italic';
    s.style.cursor = 'pointer';
    s.title = 'Klik untuk edit nama';
    s.textContent = name || '—';
    return s;
}

function deleteOnu(onuId, sn, btn) {
    if (!confirm(`Hapus ONU ${sn} dari OLT?\nAksi ini akan menghapus konfigurasi ONU dari OLT.`)) return;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const fd = new FormData();
    fd.append(_csrf.name, _csrf.hash);

    fetch(`/onus/${onuId}/delete`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.closest('tr').remove();
            } else {
                btn.disabled = false;
                btn.innerHTML = origHtml;
                alert('Gagal: ' + data.message);
            }
        })
        .catch(e => { btn.disabled = false; btn.innerHTML = origHtml; alert('Error: ' + e.message); });
}
</script>
<?= $this->endSection() ?>
