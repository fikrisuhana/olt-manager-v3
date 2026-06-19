<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-router me-1"></i>Semua ONU Terdaftar</h6>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary"><?= count($onus) ?></span>
            <button class="btn btn-sm btn-outline-secondary" id="btnSyncAllNames" title="Sync semua nama dari cache OLT">
                <i class="bi bi-cloud-download me-1"></i>Sync Semua Nama
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($onus)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-router fs-1 d-block mb-2"></i>
                Belum ada ONU. Scan ONU dari halaman OLT.
                <div class="mt-2"><a href="/olts" class="btn btn-primary btn-sm">Ke Halaman OLT</a></div>
            </div>
        <?php else: ?>
            <div class="p-3 pb-0">
                <input type="text" class="form-control form-control-sm mb-3"
                       id="searchOnu" placeholder="Cari SN, nama, OLT ...">
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="onuTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">SN</th>
                            <th>Nama <small class="text-muted fw-normal">(klik untuk edit)</small></th>
                            <th>OLT</th>
                            <th>Port</th>
                            <th>Tipe</th>
                            <th>ACS</th>
                            <th>Terdaftar</th>
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
                                <td class="small text-muted font-monospace">
                                    <?= esc("{$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']}") ?>
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

document.getElementById('searchOnu')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#onuTable tbody tr').forEach(row => {
        row.style.display = (!q || (row.dataset.search || '').includes(q)) ? '' : 'none';
    });
});

document.getElementById('btnSyncAllNames')?.addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...';
    const fd = new FormData();
    fd.append(_csrf.name, _csrf.hash);
    fetch('/onus/sync-all-names', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Sync Semua Nama';
            if (data.success) {
                alert(`${data.updated} nama berhasil diupdate dari cache OLT.`);
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
