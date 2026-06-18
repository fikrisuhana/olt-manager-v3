<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div></div>
    <a href="/olts/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Tambah OLT
    </a>
</div>

<?php if (empty($olts)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <i class="bi bi-hdd-network fs-1 text-muted d-block mb-2"></i>
        <p class="text-muted">Belum ada OLT. Tambahkan OLT pertama kamu.</p>
        <a href="/olts/create" class="btn btn-primary btn-sm mx-auto" style="width:160px">Tambah OLT</a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($olts as $olt): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1 fw-semibold"><?= esc($olt['name']) ?></h6>
                                <span class="badge rounded-pill text-bg-primary"><?= esc($olt['brand']) ?></span>
                                <span class="badge rounded-pill text-bg-secondary"><?= esc($olt['model']) ?></span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="/olts/<?= $olt['id'] ?>"><i class="bi bi-eye me-2"></i>Lihat ONU</a></li>
                                    <li><a class="dropdown-item" href="/olts/<?= $olt['id'] ?>/edit"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="/olts/<?= $olt['id'] ?>/delete"
                                           onclick="return confirm('Hapus OLT <?= esc($olt['name'], 'js') ?>?')">
                                        <i class="bi bi-trash me-2"></i>Hapus</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="text-muted small mb-3">
                            <i class="bi bi-ethernet me-1"></i><?= esc($olt['ip']) ?>:<?= esc($olt['telnet_port']) ?>
                        </div>
                        <?php if ($olt['description']): ?>
                            <p class="text-muted small mb-3"><?= esc($olt['description']) ?></p>
                        <?php endif; ?>
                        <a href="/olts/<?= $olt['id'] ?>" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-search me-1"></i>Scan &amp; Kelola ONU
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
