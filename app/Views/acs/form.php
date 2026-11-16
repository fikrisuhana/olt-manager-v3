<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center space-x-3 mb-4">
        <a href="/acs" class="btn btn-secondary btn-sm btn-icon-only" title="Kembali">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Tambah ACS Server</h1>
            <p class="text-xs text-slate-500">GenieACS untuk provisioning TR-069</p>
        </div>
    </div>

    <div class="card !mb-0">
        <form action="/acs/store" method="POST">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Nama <span class="text-rose-500">*</span></label>
                <input type="text" name="name" class="form-input" placeholder="misal: GenieACS Production" required>
            </div>
            <div class="form-group">
                <label class="form-label">URL ACS <span class="text-rose-500">*</span></label>
                <input type="url" name="url" class="form-input font-mono" placeholder="http://host.docker.internal:7557" required>
                <div class="text-[11px] text-slate-400 mt-1">Port default GenieACS: 7557</div>
            </div>
            <div class="form-grid" style="grid-template-columns:1fr 1fr">
                <div class="form-group">
                    <label class="form-label">Username (jika ada)</label>
                    <input type="text" name="username" class="form-input" placeholder="admin">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input">
                </div>
            </div>
            <div class="mb-4">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" name="is_default" value="1" class="accent-blue-600 w-4 h-4" id="isDefault">
                    <span class="text-xs text-slate-600 font-medium">Set sebagai ACS default</span>
                </label>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Simpan
                </button>
                <a href="/acs" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
