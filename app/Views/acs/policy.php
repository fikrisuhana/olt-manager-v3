<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
    $i = $settings['inform'];
    $r = $settings['refresh'];
    $c = $settings['credentials'];
    $pwHint = fn(string $v) => $v !== '' ? 'Tersimpan — kosongkan untuk tetap pakai yang lama' : 'Belum diisi';
?>

<!-- Page Header -->
<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <a href="/acs" class="btn btn-secondary btn-sm btn-icon-only" title="Kembali">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Kebijakan ACS</h1>
            <p class="text-xs text-slate-500"><?= esc($server['name']) ?> · <span class="font-mono"><?= esc($server['url']) ?></span></p>
        </div>
    </div>
    <form action="/acs/<?= $server['id'] ?>/policy/push" method="POST">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary btn-sm" <?= $row ? '' : 'disabled' ?> title="Kirim ulang kebijakan tersimpan ke GenieACS">
            <i data-lucide="upload-cloud" class="w-4 h-4"></i> Push ulang
        </button>
    </form>
</div>

<div class="p-3.5 bg-blue-50 border border-blue-100 text-blue-800 rounded-xl text-xs flex items-start space-x-3">
    <i data-lucide="info" class="w-4 h-4 text-blue-500 shrink-0 mt-0.5"></i>
    <div class="space-y-1">
        <p>ACS mandiri: gpon-manager menulis provision &amp; preset berawalan <code class="font-mono bg-blue-100 px-1 rounded">gm-</code> langsung ke GenieACS lewat NBI. Tidak ada sinkronisasi ke layanan luar.</p>
        <p>Berlaku untuk <b>semua modem</b> di ACS ini, dijalankan saat modem inform. Penulisan ke modem dibatasi maksimal 1× per hari per parameter.</p>
    </div>
</div>

<!-- Status push terakhir -->
<div class="card !mb-0">
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="font-semibold text-slate-700">Status di GenieACS:</span>
        <?php if ($acsErr): ?>
            <span class="chip chip-danger">Tidak bisa dihubungi: <?= esc($acsErr) ?></span>
        <?php else: ?>
            <?php foreach ($plan as $f): $live = in_array($f['name'], $gmLive, true); ?>
                <?php if ($f['enabled'] && $live): ?>
                    <span class="chip chip-success"><?= esc($f['label']) ?>: aktif</span>
                <?php elseif ($f['enabled'] && !$live): ?>
                    <span class="chip chip-warning"><?= esc($f['label']) ?>: belum ter-push</span>
                <?php elseif (!$f['enabled'] && $live): ?>
                    <span class="chip chip-warning"><?= esc($f['label']) ?>: masih aktif di GenieACS</span>
                <?php else: ?>
                    <span class="chip chip-neutral"><?= esc($f['label']) ?>: nonaktif</span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if (!empty($row['pushed_at'])): ?>
        <div class="mt-2 text-[11px] <?= $row['push_ok'] ? 'text-slate-500' : 'text-rose-600' ?>">
            Push terakhir <?= esc($row['pushed_at']) ?> — <?= esc($row['push_message']) ?>
        </div>
    <?php endif; ?>
</div>

<form action="/acs/<?= $server['id'] ?>/policy/save" method="POST" class="space-y-6" autocomplete="off">
    <?= csrf_field() ?>

    <!-- 1. Inform & Connection Request -->
    <div class="card !mb-0">
        <div class="card-header">
            <div class="card-title"><i data-lucide="radio-tower" class="w-4 h-4 text-slate-400"></i> Inform &amp; Connection Request</div>
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-600">
                <input type="checkbox" name="inform_enabled" value="1" class="accent-blue-600 w-4 h-4" <?= $i['enabled'] ? 'checked' : '' ?>> Aktif
            </label>
        </div>
        <p class="text-[11px] text-slate-500 mb-3">Modem lapor rutin ke ACS dan bisa dihubungi ACS kapan saja (push PPPoE/WiFi/reboot langsung jalan, tidak menunggu inform berikutnya).</p>
        <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
            <div class="form-group">
                <label class="form-label">Interval inform (detik)</label>
                <input type="number" name="inform_interval" min="60" max="86400" class="form-input font-mono" value="<?= esc($i['interval']) ?>">
                <div class="text-[11px] text-slate-400 mt-1">60–86400. Umumnya 300 (5 menit).</div>
            </div>
            <div class="form-group">
                <label class="form-label">Username Connection Request</label>
                <input type="text" name="cr_user" class="form-input font-mono" value="<?= esc($i['cr_user']) ?>" placeholder="kosong = tidak diubah">
            </div>
            <div class="form-group">
                <label class="form-label">Password Connection Request</label>
                <input type="password" name="cr_pass" class="form-input font-mono" placeholder="<?= esc($pwHint($i['cr_pass'])) ?>" autocomplete="new-password">
                <?php if ($i['cr_pass'] !== ''): ?>
                <label class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-500"><input type="checkbox" name="cr_pass_clear" value="1" class="accent-rose-600"> Hapus password tersimpan</label>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group !mb-0">
            <label class="form-label">URL ACS untuk modem <span class="text-slate-400 font-normal">(opsional)</span></label>
            <input type="text" name="acs_url" class="form-input font-mono" value="<?= esc($i['acs_url']) ?>" placeholder="kosong = URL ACS di modem tidak diubah">
            <div class="text-[11px] text-amber-700 mt-1 flex items-start gap-1">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-px"></i>
                Isi hanya untuk memindahkan modem ke alamat ACS lain (mis. setelah IP server ganti). Alamat salah = modem tidak bisa kembali ke ACS ini. Lebih aman pakai nama domain daripada IP.
            </div>
        </div>
    </div>

    <!-- 2. Refresh info modem -->
    <div class="card !mb-0">
        <div class="card-header">
            <div class="card-title"><i data-lucide="refresh-cw" class="w-4 h-4 text-slate-400"></i> Refresh info modem</div>
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-600">
                <input type="checkbox" name="refresh_enabled" value="1" class="accent-blue-600 w-4 h-4" <?= $r['enabled'] ? 'checked' : '' ?>> Aktif
            </label>
        </div>
        <p class="text-[11px] text-slate-500 mb-3">Rutin membaca PPPoE, IP WAN, redaman RX, suhu, uptime, SSID dan perangkat terhubung — data yang ditampilkan gpon-manager.</p>
        <div class="form-group !mb-0 max-w-xs">
            <label class="form-label">Interval refresh (menit)</label>
            <input type="number" name="refresh_interval" min="5" max="1440" class="form-input font-mono" value="<?= esc($r['interval_min']) ?>">
            <div class="text-[11px] text-slate-400 mt-1">5–1440. Tidak bisa lebih cepat dari interval inform modem.</div>
        </div>
    </div>

    <!-- 3. Password admin modem -->
    <div class="card !mb-0">
        <div class="card-header">
            <div class="card-title"><i data-lucide="key-round" class="w-4 h-4 text-slate-400"></i> Password admin modem</div>
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-600">
                <input type="checkbox" name="cred_enabled" value="1" class="accent-blue-600 w-4 h-4" <?= $c['enabled'] ? 'checked' : '' ?>> Aktif
            </label>
        </div>
        <p class="text-[11px] text-slate-500 mb-3">Memaksa akun login web modem. Akun yang username atau password-nya kosong tidak disentuh. Huawei menolak password &lt; 8 karakter.</p>
        <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
            <div class="form-group">
                <label class="form-label">Username akun user</label>
                <input type="text" name="cred_user_name" class="form-input font-mono" value="<?= esc($c['user_name']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Password akun user</label>
                <input type="password" name="cred_user_pass" class="form-input font-mono" placeholder="<?= esc($pwHint($c['user_pass'])) ?>" autocomplete="new-password">
                <?php if ($c['user_pass'] !== ''): ?>
                <label class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-500"><input type="checkbox" name="cred_user_pass_clear" value="1" class="accent-rose-600"> Hapus password tersimpan</label>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label">Username akun admin</label>
                <input type="text" name="cred_admin_name" class="form-input font-mono" value="<?= esc($c['admin_name']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Password akun admin</label>
                <input type="password" name="cred_admin_pass" class="form-input font-mono" placeholder="<?= esc($pwHint($c['admin_pass'])) ?>" autocomplete="new-password">
                <?php if ($c['admin_pass'] !== ''): ?>
                <label class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-500"><input type="checkbox" name="cred_admin_pass_clear" value="1" class="accent-rose-600"> Hapus password tersimpan</label>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group !mb-0">
            <label class="form-label">Model dikecualikan</label>
            <textarea name="cred_exclude_models" rows="2" class="form-input font-mono text-xs"><?= esc($c['exclude_models']) ?></textarea>
            <div class="text-[11px] text-slate-400 mt-1">Pisahkan dengan koma. Dicocokkan ke ProductClass modem. Default = model yang menolak/mengabaikan perubahan akun.</div>
        </div>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i data-lucide="save" class="w-4 h-4"></i> Simpan &amp; Push ke GenieACS
        </button>
        <a href="/acs" class="btn btn-secondary">Batal</a>
    </div>
</form>

<!-- Preset lain di GenieACS -->
<?php if (is_array($foreign) && $foreign): ?>
<div class="card !mb-0">
    <div class="card-header">
        <div class="card-title"><i data-lucide="layers" class="w-4 h-4 text-slate-400"></i> Preset lain di GenieACS</div>
    </div>
    <p class="text-[11px] text-slate-500 mb-2">Bukan milik gpon-manager — tidak disentuh halaman ini. Preset bertanda <span class="chip chip-danger">ext</span> memanggil layanan luar lewat extension GenieACS.</p>
    <div class="flex flex-wrap gap-1.5">
        <?php foreach ($foreign as $p): ?>
            <span class="chip <?= $p['ext'] ? 'chip-danger' : 'chip-neutral' ?>" title="provision: <?= esc(implode(', ', $p['provisions'])) ?>">
                <?= esc($p['name']) ?><?= $p['ext'] ? ' · ext' : '' ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
