<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-journal-text me-1"></i>Log Aktivitas Global</h6>
        <span class="text-muted small">200 log terakhir</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
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
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada log.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-muted small text-nowrap">
                                <?= date('d/m H:i', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="small"><?= esc($log['username'] ?? '—') ?></td>
                            <td class="small"><?= esc($log['olt_name'] ?? '—') ?></td>
                            <td><code class="small"><?= esc($log['sn'] ?? '—') ?></code></td>
                            <td class="small"><?= esc($log['action'] ?? '') ?></td>
                            <td>
                                <?php
                                    $status = $log['status'] ?? '';
                                    $cls = match($status) {
                                        'success' => 'bg-success',
                                        'failed'  => 'bg-danger',
                                        default   => 'bg-secondary',
                                    };
                                ?>
                                <span class="badge <?= $cls ?> small"><?= esc($status) ?></span>
                            </td>
                            <td class="small text-muted"><?= esc($log['message'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
