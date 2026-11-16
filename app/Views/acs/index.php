<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Page Header -->
<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="cloud" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">ACS Server</h1>
            <p class="text-xs text-slate-500">GenieACS untuk provisioning ONU via TR-069</p>
        </div>
    </div>
    <a href="/acs/create" class="btn btn-primary btn-sm">
        <i data-lucide="plus-circle" class="w-4 h-4"></i> Tambah ACS Server
    </a>
</div>

<div class="p-3.5 bg-blue-50 border border-blue-100 text-blue-800 rounded-xl text-xs flex items-start space-x-3 shadow-xs">
    <i data-lucide="info" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5"></i>
    <div>
        ACS (Auto Configuration Server) digunakan untuk provisioning WAN ONU non-ZTE via TR-069.
        Biasanya GenieACS di port 7557. Contoh URL: <code class="font-mono bg-blue-100 px-1 rounded">http://136.1.1.8:7557</code>
    </div>
</div>

<?php if (empty($servers)): ?>
    <div class="card">
        <div class="empty-state">
            <i data-lucide="cloud-off"></i>
            <p class="text-xs mb-4">Belum ada ACS server.</p>
            <a href="/acs/create" class="btn btn-primary btn-sm mx-auto">Tambah ACS Server</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid md:grid-cols-2 gap-3">
        <?php foreach ($servers as $s): ?>
        <div class="card !mb-0">
            <div class="flex justify-between items-start">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-bold text-slate-900 text-sm"><?= esc($s['name']) ?></span>
                        <?php if ($s['is_default']): ?>
                            <span class="chip chip-success">★ Default</span>
                        <?php endif; ?>
                    </div>
                    <code class="text-xs text-slate-400 font-mono break-all"><?= esc($s['url']) ?></code>
                </div>
                <div class="flex gap-1 shrink-0">
                    <?php if (!$s['is_default']): ?>
                    <a href="/acs/<?= $s['id'] ?>/default" class="btn btn-secondary btn-sm btn-icon-only" title="Set Default">
                        <i data-lucide="star" class="w-3.5 h-3.5"></i>
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-secondary btn-sm" onclick="testAcs(<?= $s['id'] ?>, this)" title="Test koneksi ke ACS">
                        <i data-lucide="plug" class="w-3.5 h-3.5"></i> Test
                    </button>
                    <a href="/acs/<?= $s['id'] ?>/delete" class="btn btn-danger btn-sm btn-icon-only" title="Hapus"
                       onclick="return confirm('Hapus ACS server ini?')">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
            <div class="mt-2 text-xs" id="testResult_<?= $s['id'] ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
async function testAcs(id, btn) {
    const result = document.getElementById('testResult_' + id);
    App.setLoading(btn, true, 'Testing...');
    result.innerHTML = '<span class="text-slate-400 flex items-center gap-1.5"><span class="spin spin-dark"></span> Testing...</span>';
    try {
        const data = await App.readJson(await App.fetch('/acs/' + id + '/test'));
        App.setLoading(btn, false);
        result.innerHTML = data.success
            ? `<span class="text-emerald-600 font-medium">✅ ${App.escapeHtml(data.message)}</span>`
            : `<span class="text-rose-600 font-medium">✖ ${App.escapeHtml(data.message)}</span>`;
    } catch (e) {
        App.setLoading(btn, false);
        result.innerHTML = `<span class="text-rose-600 font-medium">✖ Error: ${App.escapeHtml(e.message)}</span>`;
    }
}
</script>
<?= $this->endSection() ?>
