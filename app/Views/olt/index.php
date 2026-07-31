<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1" style="font-family:'Google Sans',sans-serif;">Daftar Perangkat OLT</h5>
        <div class="text-secondary small">Kelola OLT GPON, scan ONU unconfigured, dan atur profil OLT</div>
    </div>
    <a href="/olts/create" class="btn btn-google-primary">
        <i class="bi bi-plus-circle me-1"></i> Tambah OLT Baru
    </a>
</div>

<?php if (empty($olts)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <i class="bi bi-hdd-network fs-1 text-muted d-block mb-3"></i>
        <h6 class="fw-bold text-dark">Belum Ada Perangkat OLT</h6>
        <p class="text-secondary small mb-4">Tambahkan OLT pertama kamu untuk mulai memanajemeni ONU/ONT.</p>
        <a href="/olts/create" class="btn btn-google-primary mx-auto" style="width:180px">Tambah OLT Baru</a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($olts as $olt): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-2 fw-bold text-dark" style="font-family:'Google Sans',sans-serif; font-size:1.05rem;"><?= esc($olt['name']) ?></h6>
                                <div class="d-flex gap-1">
                                    <span class="chip chip-info font-monospace fw-bold"><?= esc($olt['brand']) ?></span>
                                    <span class="chip chip-neutral font-monospace"><?= esc($olt['model']) ?></span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-google-secondary px-2" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3">
                                    <li><a class="dropdown-item py-2" href="/olts/<?= $olt['id'] ?>"><i class="bi bi-eye me-2 text-primary"></i>Kelola ONU</a></li>
                                    <li><a class="dropdown-item py-2" href="/olts/<?= $olt['id'] ?>/edit"><i class="bi bi-pencil me-2 text-secondary"></i>Edit OLT</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 text-danger" href="/olts/<?= $olt['id'] ?>/delete"
                                           onclick="return confirm('Hapus OLT <?= esc($olt['name'], 'js') ?>?')">
                                        <i class="bi bi-trash me-2"></i>Hapus</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="text-secondary small font-monospace mb-3">
                            <i class="bi bi-ethernet me-1 text-primary"></i><?= esc($olt['ip']) ?>:<?= esc($olt['telnet_port']) ?>
                        </div>

                        <?php if ($olt['description']): ?>
                            <p class="text-secondary small mb-4 flex-grow-1"><?= esc($olt['description']) ?></p>
                        <?php else: ?>
                            <div class="flex-grow-1"></div>
                        <?php endif; ?>

                        <a href="/olts/<?= $olt['id'] ?>" class="btn btn-google-secondary w-100 mt-3 text-center">
                            <i class="bi bi-search me-1 text-primary"></i> Scan &amp; Kelola ONU
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
