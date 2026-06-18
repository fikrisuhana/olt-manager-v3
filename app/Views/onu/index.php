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
            <div class="p-3">
                <input type="text" class="form-control form-control-sm mb-3"
                       id="searchOnu" placeholder="Cari SN, nama, OLT ...">
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="onuTable">
                    <thead class="table-light">
                        <tr>
                            <th>SN</th>
                            <th>Nama</th>
                            <th>OLT</th>
                            <th>Brand</th>
                            <th>Port</th>
                            <th>Tipe ONU</th>
                            <th>Status</th>
                            <th>Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($onus as $onu): ?>
                            <tr>
                                <td class="font-monospace small">
                                    <a href="/onus/<?= $onu['id'] ?>" class="text-decoration-none"><?= esc($onu['sn']) ?></a>
                                </td>
                                <td><?= esc($onu['name'] ?? '-') ?></td>
                                <td>
                                    <a href="/olts/<?= $onu['olt_id'] ?>" class="text-decoration-none small">
                                        <?= esc($onu['olt_name']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size:.7rem"><?= esc($onu['olt_ip']) ?></div>
                                </td>
                                <td><span class="badge bg-primary"><?= esc($onu['brand']) ?></span></td>
                                <td class="small text-muted font-monospace">
                                    <?= esc("{$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']}") ?>
                                </td>
                                <td><span class="badge bg-light text-dark"><?= esc($onu['onu_type'] ?? '-') ?></span></td>
                                <td>
                                    <?php $cls = ['registered'=>'secondary','active'=>'success','offline'=>'danger','pending'=>'warning']; ?>
                                    <span class="badge bg-<?= $cls[$onu['status']] ?? 'secondary' ?>">
                                        <?= esc($onu['status']) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= date('d/m/y H:i', strtotime($onu['registered_at'])) ?></td>
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
document.getElementById('searchOnu')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#onuTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
<?= $this->endSection() ?>
