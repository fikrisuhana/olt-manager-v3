<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2" style="background:#eff6ff">
                    <i class="bi bi-hdd-network fs-4 text-primary"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold"><?= $total_olts ?></div>
                    <div class="text-muted small">OLT Terdaftar</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2" style="background:#f0fdf4">
                    <i class="bi bi-router fs-4 text-success"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold"><?= $total_onus ?></div>
                    <div class="text-muted small">ONU Terprovisi</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2" style="background:#fefce8">
                    <i class="bi bi-file-code fs-4 text-warning"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold"><?= $total_templates ?></div>
                    <div class="text-muted small">Template Config</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-lightning-charge-fill text-primary"></i>
                <span class="fw-semibold small">Aksi Cepat</span>
            </div>
            <a href="/olts" class="btn btn-sm btn-primary w-100 mb-1">
                <i class="bi bi-plus-circle me-1"></i>Scan ONU
            </a>
            <a href="/olts/create" class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-plus me-1"></i>Tambah OLT
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1"></i>Log Provisioning Terbaru</h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recent_logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                Belum ada aktivitas provisioning.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Aksi</th>
                            <th>ONU</th>
                            <th>OLT</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td class="text-muted small"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                                <td><span class="badge bg-secondary"><?= esc($log['action']) ?></span></td>
                                <td class="font-monospace small"><?= esc($log['sn'] ?? '-') ?></td>
                                <td class="small"><?= esc($log['olt_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="badge bg-success">Berhasil</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Gagal</span>
                                    <?php endif; ?>
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
