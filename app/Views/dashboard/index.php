<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$onlineRate = $acs_total > 0 ? round($online_onus / $acs_total * 100) : 0;
$rateClass  = $onlineRate >= 90 ? 'success' : ($onlineRate >= 70 ? 'warning' : 'danger');
?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100">
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
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2" style="background:#f0fdf4">
                    <i class="bi bi-router fs-4 text-success"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold"><?= $total_onus ?></div>
                    <div class="text-muted small">ONU Terdaftar</div>
                    <?php if ($acs_total > 0): ?>
                    <div class="mt-1">
                        <span class="badge bg-success"><?= $online_onus ?> online</span>
                        <span class="badge bg-secondary ms-1"><?= $acs_total - $online_onus ?> offline</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-3 p-2" style="background:<?= $onlineRate >= 90 ? '#f0fdf4' : ($onlineRate >= 70 ? '#fefce8' : '#fef2f2') ?>">
                    <i class="bi bi-wifi fs-4 text-<?= $rateClass ?>"></i>
                </div>
                <div>
                    <div class="fs-2 fw-bold text-<?= $rateClass ?>"><?= $onlineRate ?>%</div>
                    <div class="text-muted small">ACS Online Rate</div>
                    <div class="mt-1" style="height:4px;background:#e5e7eb;border-radius:2px;width:80px">
                        <div style="height:4px;border-radius:2px;width:<?= $onlineRate ?>%;background:var(--bs-<?= $rateClass ?>)"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-lightning-charge-fill text-primary"></i>
                <span class="fw-semibold small">Aksi Cepat</span>
            </div>
            <a href="/olts" class="btn btn-sm btn-primary w-100 mb-1">
                <i class="bi bi-plus-circle me-1"></i>Scan ONU Baru
            </a>
            <a href="/onus" class="btn btn-sm btn-outline-secondary w-100">
                <i class="bi bi-list-ul me-1"></i>Semua ONU
            </a>
        </div>
    </div>
</div>

<!-- Alert ONU perlu perhatian -->
<?php if ($no_pppoe > 0 || $no_acs > 0): ?>
<div class="row g-3 mb-4">
    <?php if ($no_pppoe > 0): ?>
    <div class="col-sm-6">
        <div class="alert alert-warning d-flex align-items-center gap-3 mb-0 border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
            <div>
                <div class="fw-semibold"><?= $no_pppoe ?> ONU belum ada PPPoE</div>
                <div class="small">Username PPPoE belum diisi di database.</div>
            </div>
            <a href="/onus" class="btn btn-sm btn-warning ms-auto text-nowrap">Lihat</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($no_acs > 0): ?>
    <div class="col-sm-6">
        <div class="alert alert-secondary d-flex align-items-center gap-3 mb-0 border-0 shadow-sm">
            <i class="bi bi-cloud-slash-fill fs-4"></i>
            <div>
                <div class="fw-semibold"><?= $no_acs ?> ONU belum di ACS</div>
                <div class="small">Belum pernah connect ke GenieACS.</div>
            </div>
            <a href="/onus" class="btn btn-sm btn-outline-secondary ms-auto text-nowrap">Lihat</a>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Per-OLT + Brand -->
<div class="row g-3 mb-4">
    <!-- Per-OLT cards -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-hdd-network me-1"></i>Status per OLT</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">OLT</th>
                                <th class="text-center">ONU DB</th>
                                <th class="text-center">ACS Online</th>
                                <th class="text-center">ACS Offline</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($olt_stats as $s): ?>
                            <?php
                                $r = $s['acs_total'] > 0 ? round($s['acs_online'] / $s['acs_total'] * 100) : null;
                                $rc = $r === null ? 'secondary' : ($r >= 90 ? 'success' : ($r >= 70 ? 'warning' : 'danger'));
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <a href="/olts/<?= $s['olt']['id'] ?>" class="text-decoration-none fw-medium">
                                        <?= esc($s['olt']['name']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size:.7rem"><?= esc($s['olt']['ip']) ?> · <?= esc($s['olt']['brand']) ?></div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= $s['onu_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['acs_online'] > 0): ?>
                                    <span class="badge bg-success"><?= $s['acs_online'] ?></span>
                                    <?php else: ?>
                                    <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['acs_offline'] > 0): ?>
                                    <span class="badge bg-secondary"><?= $s['acs_offline'] ?></span>
                                    <?php else: ?>
                                    <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width:100px">
                                    <?php if ($r !== null): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="height:6px;background:#e5e7eb;border-radius:3px;flex:1">
                                            <div style="height:6px;border-radius:3px;width:<?= $r ?>%;background:var(--bs-<?= $rc ?>)"></div>
                                        </div>
                                        <small class="text-<?= $rc ?> fw-medium"><?= $r ?>%</small>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted small">Belum ada ACS</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Brand breakdown -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-pie-chart me-1"></i>Distribusi Brand ONU</h6>
            </div>
            <div class="card-body">
                <?php
                $total = array_sum($brands);
                $colors = ['ZTE' => '#2563eb', 'Fiberhome' => '#16a34a', 'Nokia' => '#0891b2', 'Huawei' => '#dc2626', 'Lainnya' => '#9ca3af'];
                ?>
                <?php foreach ($brands as $brand => $count): ?>
                <?php $pct = $total > 0 ? round($count / $total * 100) : 0; ?>
                <?php $color = $colors[$brand] ?? '#9ca3af'; ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small fw-medium"><?= esc($brand) ?></span>
                        <span class="small text-muted"><?= $count ?> ONU (<?= $pct ?>%)</span>
                    </div>
                    <div style="height:8px;background:#f3f4f6;border-radius:4px">
                        <div style="height:8px;border-radius:4px;width:<?= $pct ?>%;background:<?= $color ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($brands)): ?>
                <div class="text-muted small text-center py-3">Belum ada data ONU.</div>
                <?php endif; ?>
                <div class="border-top pt-2 mt-2">
                    <div class="d-flex justify-content-between">
                        <span class="small text-muted">Total ONU</span>
                        <span class="small fw-bold"><?= $total ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log terbaru -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1"></i>Log Provisioning Terbaru</h6>
        <a href="/admin/logs" class="btn btn-sm btn-outline-secondary py-0">Semua Log</a>
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
                            <th class="ps-3">Waktu</th>
                            <th>Aksi</th>
                            <th>ONU / SN</th>
                            <th>OLT</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td class="text-muted small ps-3"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                                <td><span class="badge bg-secondary"><?= esc($log['action']) ?></span></td>
                                <td class="font-monospace small">
                                    <?php if (!empty($log['onu_id'])): ?>
                                    <a href="/onus/<?= $log['onu_id'] ?>" class="text-decoration-none">
                                        <?= esc($log['sn'] ?? '-') ?>
                                    </a>
                                    <?php else: ?>
                                    <?= esc($log['sn'] ?? '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= esc($log['olt_name'] ?? '-') ?></td>
                                <td>
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="badge bg-success">OK</span>
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
