<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Page Header -->
<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="network" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Daftar Perangkat OLT</h1>
            <p class="text-xs text-slate-500">Kelola OLT GPON, scan ONU unconfigured, dan atur profil OLT</p>
        </div>
    </div>
    <a href="/olts/create" class="btn btn-primary">
        <i data-lucide="plus-circle" class="w-4 h-4"></i> Tambah OLT Baru
    </a>
</div>

<?php if (empty($olts)): ?>
    <div class="card">
        <div class="empty-state">
            <i data-lucide="network"></i>
            <h3 class="text-sm font-bold text-slate-700 mb-1">Belum Ada Perangkat OLT</h3>
            <p class="text-xs mb-4">Tambahkan OLT pertama kamu untuk mulai memanajemeni ONU/ONT.</p>
            <a href="/olts/create" class="btn btn-primary btn-sm mx-auto">Tambah OLT Baru</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-3">
        <?php foreach ($olts as $olt): ?>
        <div class="card !mb-0 flex flex-col hover:shadow-md transition-shadow">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <a href="/olts/<?= $olt['id'] ?>" class="font-bold text-slate-900 hover:text-blue-600 text-[15px]"><?= esc($olt['name']) ?></a>
                    <div class="flex gap-1 mt-1.5">
                        <span class="chip chip-info font-mono"><?= esc($olt['brand']) ?></span>
                        <span class="chip chip-neutral font-mono"><?= esc($olt['model']) ?></span>
                    </div>
                </div>
                <div class="flex gap-1 shrink-0">
                    <a href="/olts/<?= $olt['id'] ?>/edit" class="btn btn-secondary btn-sm btn-icon-only" title="Edit OLT">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    </a>
                    <a href="/olts/<?= $olt['id'] ?>/delete" class="btn btn-danger btn-sm btn-icon-only" title="Hapus"
                       onclick="return confirm('Hapus OLT <?= esc($olt['name'], 'js') ?>?')">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            <div class="text-xs text-slate-400 font-mono mb-3 flex items-center gap-1.5">
                <i data-lucide="ethernet" class="w-3.5 h-3.5 text-blue-500"></i><?= esc($olt['ip']) ?>:<?= esc($olt['telnet_port']) ?>
            </div>

            <?php if ($olt['description']): ?>
                <p class="text-xs text-slate-500 mb-3 flex-1"><?= esc($olt['description']) ?></p>
            <?php else: ?>
                <div class="flex-1"></div>
            <?php endif; ?>

            <a href="/olts/<?= $olt['id'] ?>" class="btn btn-secondary w-full mt-1">
                <i data-lucide="search" class="w-4 h-4 text-blue-600"></i> Scan &amp; Kelola ONU
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
