<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="hard-drive" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Semua OLT</h1>
            <p class="text-xs text-slate-500">Seluruh perangkat OLT milik semua user</p>
        </div>
    </div>
    <span class="chip chip-neutral font-mono"><?= count($olts) ?> OLT</span>
</div>

<div class="card !mb-0">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama OLT</th>
                    <th>IP</th>
                    <th>Brand / Model</th>
                    <th>ONU</th>
                    <th>Pemilik</th>
                    <th>Dibuat</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($olts)): ?>
                    <tr><td colspan="6" class="text-center text-slate-400 py-6 text-xs">Belum ada OLT terdaftar.</td></tr>
                <?php endif; ?>
                <?php foreach ($olts as $olt): ?>
                <tr>
                    <td>
                        <div class="font-medium text-xs"><?= esc($olt['name']) ?></div>
                        <div class="text-[10px] text-slate-400"><?= esc($olt['location'] ?? '') ?></div>
                    </td>
                    <td><span class="font-mono text-xs"><?= esc($olt['ip'] ?? '') ?>:<?= esc($olt['telnet_port'] ?? '') ?></span></td>
                    <td>
                        <?php
                            $brand = strtolower($olt['brand'] ?? '');
                            $cls = match($brand) {
                                'zte' => 'chip-info',
                                'fiberhome', 'fh' => 'chip-success',
                                'huawei' => 'chip-danger',
                                default  => 'chip-neutral',
                            };
                        ?>
                        <span class="chip <?= $cls ?> font-mono"><?= esc(strtoupper($olt['brand'] ?? '')) ?></span>
                        <span class="text-[10px] text-slate-400 ml-1"><?= esc($olt['model'] ?? '') ?></span>
                    </td>
                    <td>
                        <span class="chip chip-neutral font-mono"><?= (int)$olt['onu_count'] ?></span>
                    </td>
                    <td>
                        <div class="text-xs"><?= esc($olt['user_name']) ?></div>
                        <div class="text-[10px] text-slate-400">@<?= esc($olt['username']) ?></div>
                    </td>
                    <td class="text-xs text-slate-400"><?= date('d/m/Y', strtotime($olt['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
