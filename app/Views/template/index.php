<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="file-code" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Template Config</h1>
            <p class="text-xs text-slate-500">Template script untuk otomatisasi provisioning ONU</p>
        </div>
    </div>
    <a href="/templates/create" class="btn btn-primary btn-sm">
        <i data-lucide="plus-circle" class="w-4 h-4"></i> Tambah Template
    </a>
</div>

<?php if (empty($templates)): ?>
    <div class="card">
        <div class="empty-state">
            <i data-lucide="file-code"></i>
            <p class="text-xs mb-4">Belum ada template. Buat template untuk otomatisasi provisioning ONU.</p>
            <a href="/templates/create" class="btn btn-primary btn-sm mx-auto">Tambah Template</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-3">
        <?php foreach ($templates as $t): ?>
        <div class="card !mb-0 flex flex-col hover:shadow-md transition-shadow">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <div class="font-bold text-slate-900 text-sm mb-1.5"><?= esc($t['name']) ?></div>
                    <div class="flex gap-1">
                        <span class="chip chip-info"><?= esc($t['brand']) ?></span>
                        <span class="chip chip-neutral font-mono"><?= strtoupper($t['wan_type']) ?></span>
                    </div>
                </div>
                <div class="flex gap-1 shrink-0">
                    <a href="/templates/<?= $t['id'] ?>/edit" class="btn btn-secondary btn-sm btn-icon-only" title="Edit">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </a>
                    <a href="/templates/<?= $t['id'] ?>/delete" class="btn btn-danger btn-sm btn-icon-only" title="Hapus"
                       onclick="return confirm('Hapus template ini?')">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
            <div class="text-[11px] text-slate-400 mb-2 flex flex-wrap gap-x-3">
                <?php if ($t['vlan_internet']): ?>
                    <span class="flex items-center gap-1"><i data-lucide="tag" class="w-3 h-3"></i>VLAN Internet: <?= $t['vlan_internet'] ?></span>
                <?php endif; ?>
                <?php if ($t['tcont_profile']): ?>
                    <span class="flex items-center gap-1 font-mono"><?= esc($t['tcont_profile']) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($t['description']): ?>
                <p class="text-xs text-slate-500 mb-0 flex-1"><?= esc($t['description']) ?></p>
            <?php else: ?>
                <div class="flex-1"></div>
            <?php endif; ?>
            <?php if ($t['gpon_onu_script']): ?>
            <div class="border-t border-slate-100 mt-3 pt-2">
                <details>
                    <summary class="text-[11px] text-slate-400 cursor-pointer hover:text-slate-600 select-none">▸ Lihat Script</summary>
                    <div class="terminal-window mt-2">
                        <div class="terminal-header">
                            <span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span>
                            <span class="text-slate-500 text-[10px] ml-2">gpon-onu script</span>
                        </div>
                        <pre class="terminal-body" style="max-height:120px;margin:0"><?= esc($t['gpon_onu_script']) ?></pre>
                    </div>
                </details>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
