<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-end mb-4">
    <a href="/templates/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Tambah Template
    </a>
</div>

<?php if (empty($templates)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <i class="bi bi-file-code fs-1 text-muted d-block mb-2"></i>
        <p class="text-muted">Belum ada template. Buat template untuk otomatisasi provisioning ONU.</p>
        <a href="/templates/create" class="btn btn-primary btn-sm mx-auto" style="width:160px">Tambah Template</a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($templates as $t): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-1 fw-semibold"><?= esc($t['name']) ?></h6>
                                <span class="badge bg-primary"><?= esc($t['brand']) ?></span>
                                <span class="badge bg-light text-dark"><?= strtoupper($t['wan_type']) ?></span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="/templates/<?= $t['id'] ?>/edit">
                                        <i class="bi bi-pencil me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item text-danger" href="/templates/<?= $t['id'] ?>/delete"
                                           onclick="return confirm('Hapus template ini?')">
                                        <i class="bi bi-trash me-2"></i>Hapus</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="text-muted small mb-2">
                            <?php if ($t['vlan_internet']): ?>
                                <span class="me-2"><i class="bi bi-tag me-1"></i>VLAN Internet: <?= $t['vlan_internet'] ?></span>
                            <?php endif; ?>
                            <?php if ($t['tcont_profile']): ?>
                                <span><i class="bi bi-speedometer2 me-1"></i><?= esc($t['tcont_profile']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($t['description']): ?>
                            <p class="text-muted small mb-0"><?= esc($t['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($t['gpon_onu_script']): ?>
                        <div class="card-footer bg-transparent border-top">
                            <details>
                                <summary class="text-muted small" style="cursor:pointer">Lihat Script</summary>
                                <pre class="cli-output mt-2 mb-0" style="max-height:120px;font-size:.75rem"><?= esc($t['gpon_onu_script']) ?></pre>
                            </details>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
