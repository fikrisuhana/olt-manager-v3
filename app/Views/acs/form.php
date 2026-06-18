<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="/acs/store" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               placeholder="misal: GenieACS Production" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL ACS <span class="text-danger">*</span></label>
                        <input type="url" name="url" class="form-control"
                               placeholder="http://136.1.1.8:7557" required>
                        <div class="form-text">Port default GenieACS: 7557</div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Username (jika ada)</label>
                            <input type="text" name="username" class="form-control" placeholder="admin">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" name="is_default" value="1"
                                   class="form-check-input" id="isDefault">
                            <label class="form-check-label" for="isDefault">Set sebagai ACS default</label>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Simpan
                        </button>
                        <a href="/acs" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
