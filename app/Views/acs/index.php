<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex justify-content-end mb-4">
    <a href="/acs/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Tambah ACS Server
    </a>
</div>

<div class="alert alert-info border-0 small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    ACS (Auto Configuration Server) digunakan untuk provisioning WAN ONU non-ZTE via TR-069.
    Biasanya GenieACS di port 7557. Contoh URL: <code>http://136.1.1.8:7557</code>
</div>

<?php if (empty($servers)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <i class="bi bi-cloud-x fs-1 text-muted d-block mb-2"></i>
        <p class="text-muted">Belum ada ACS server.</p>
        <a href="/acs/create" class="btn btn-primary btn-sm mx-auto" style="width:180px">Tambah ACS Server</a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($servers as $s): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1 fw-semibold">
                                    <?= esc($s['name']) ?>
                                    <?php if ($s['is_default']): ?>
                                        <span class="badge bg-success ms-1">Default</span>
                                    <?php endif; ?>
                                </h6>
                                <code class="small text-muted"><?= esc($s['url']) ?></code>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php if (!$s['is_default']): ?>
                                        <li><a class="dropdown-item" href="/acs/<?= $s['id'] ?>/default">
                                            <i class="bi bi-star me-2"></i>Set Default</a></li>
                                    <?php endif; ?>
                                    <li>
                                        <button class="dropdown-item" onclick="testAcs(<?= $s['id'] ?>, this)">
                                            <i class="bi bi-plug me-2"></i>Test Koneksi
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="/acs/<?= $s['id'] ?>/delete"
                                           onclick="return confirm('Hapus ACS server ini?')">
                                        <i class="bi bi-trash me-2"></i>Hapus</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="text-muted small mt-2" id="testResult_<?= $s['id'] ?>"></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
function testAcs(id, btn) {
    const result = document.getElementById('testResult_' + id);
    result.innerHTML = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Testing...</span>';
    fetch('/acs/' + id + '/test')
        .then(r => r.json())
        .then(data => {
            result.innerHTML = data.success
                ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${data.message}</span>`
                : `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${data.message}</span>`;
        });
}
</script>
<?= $this->endSection() ?>
