<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-router me-1"></i>Semua ONU Terdaftar</h6>
        <span class="badge bg-primary"><?= count($onus) ?></span>
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
                            <th>Nama</th>
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
                                <td><?= esc($onu['name'] ?? '-') ?></td>
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
<script>
const _csrf = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };

document.getElementById('searchOnu')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#onuTable tbody tr').forEach(row => {
        row.style.display = (!q || (row.dataset.search || '').includes(q)) ? '' : 'none';
    });
});

function deleteOnu(onuId, sn, btn) {
    if (!confirm(`Hapus ONU ${sn} dari OLT?\nAksi ini akan menghapus konfigurasi ONU dari OLT.`)) return;
    btn.disabled = true;

    const fd = new FormData();
    fd.append(_csrf.name, _csrf.hash);

    fetch(`/onus/${onuId}/delete`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.closest('tr').remove();
            } else {
                btn.disabled = false;
                alert('Gagal: ' + data.message);
            }
        })
        .catch(e => { btn.disabled = false; alert('Error: ' + e.message); });
}
</script>
<?= $this->endSection() ?>
