<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-cloud-check me-1"></i>Semua ACS Server</h6>
        <span class="badge bg-secondary"><?= count($servers) ?> ACS</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
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
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada ACS server terdaftar.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($servers as $s): ?>
                        <tr>
                            <td class="fw-medium"><?= esc($s['name']) ?></td>
                            <td>
                                <code><?= esc($s['url']) ?></code>
                            </td>
                            <td>
                                <?php if ($s['is_default']): ?>
                                    <span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>Default</span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= esc($s['user_name']) ?></div>
                                <div class="small text-muted">@<?= esc($s['username']) ?></div>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
