<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-hdd-network me-1"></i>Semua OLT</h6>
        <span class="badge bg-secondary"><?= count($olts) ?> OLT</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama OLT</th>
                        <th>IP</th>
                        <th>Brand / Model</th>
                        <th>ONU</th>
                        <th>Pemilik</th>
                        <th>Dibuat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($olts)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada OLT terdaftar.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($olts as $olt): ?>
                        <tr>
                            <td>
                                <div class="fw-medium"><?= esc($olt['name']) ?></div>
                                <div class="small text-muted"><?= esc($olt['location'] ?? '') ?></div>
                            </td>
                            <td><code><?= esc($olt['host']) ?>:<?= esc($olt['port']) ?></code></td>
                            <td>
                                <?php
                                    $brand = strtolower($olt['brand'] ?? '');
                                    $cls = match($brand) {
                                        'zte' => 'badge-brand-zte',
                                        'fiberhome', 'fh' => 'badge-brand-fiberhome',
                                        'huawei' => 'badge-brand-huawei',
                                        default  => 'bg-secondary',
                                    };
                                ?>
                                <span class="badge <?= $cls ?>"><?= esc(strtoupper($olt['brand'] ?? '')) ?></span>
                                <span class="small text-muted ms-1"><?= esc($olt['model'] ?? '') ?></span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= (int)$olt['onu_count'] ?></span>
                            </td>
                            <td>
                                <div><?= esc($olt['user_name']) ?></div>
                                <div class="small text-muted">@<?= esc($olt['username']) ?></div>
                            </td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($olt['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
