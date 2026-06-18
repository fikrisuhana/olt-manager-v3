<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/olts/<?= $onu['olt_id'] ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
    <div>
        <span class="badge bg-secondary"><?= esc($onu['onu_type'] ?? '-') ?></span>
        <span class="text-muted small ms-1"><?= esc("{$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']}") ?></span>
    </div>
    <div class="ms-auto d-flex gap-2">
        <button class="btn btn-sm btn-outline-info" onclick="loadAcsInfo()" id="btnLoadAcs">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh ACS
        </button>
        <button class="btn btn-sm btn-outline-danger" id="btnReboot" onclick="reboot()">
            <i class="bi bi-power me-1"></i>Reboot
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Info ONU -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-router me-1"></i>Info ONU</h6>
                <button class="btn btn-sm btn-outline-secondary py-0" onclick="toggleEdit()" id="btnToggleEdit">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
            </div>
            <div class="card-body">
                <!-- Info display -->
                <table class="table table-sm mb-0" id="infoTable">
                    <tr><th class="text-muted" style="width:40%">SN</th><td><code><?= esc($onu['sn']) ?></code></td></tr>
                    <tr><th class="text-muted">Nama</th><td id="disp_name"><?= esc($onu['name'] ?? '-') ?></td></tr>
                    <tr><th class="text-muted">OLT</th><td><?= esc($onu['olt_name'] ?? '-') ?> <span class="text-muted small">(<?= esc($onu['olt_ip'] ?? '') ?>)</span></td></tr>
                    <tr><th class="text-muted">Port</th><td><?= esc("{$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']}") ?></td></tr>
                    <tr><th class="text-muted">Tipe</th><td><?= esc($onu['onu_type'] ?? '-') ?></td></tr>
                    <tr>
                        <th class="text-muted">VLAN</th>
                        <td class="small" id="disp_vlan">
                            <?php if ($onu['vlan_internet']): ?>
                                <span class="badge bg-primary me-1">Internet: <?= $onu['vlan_internet'] ?></span>
                            <?php endif; ?>
                            <?php if ($onu['vlan_acs']): ?>
                                <span class="badge bg-secondary">ACS: <?= $onu['vlan_acs'] ?></span>
                            <?php endif; ?>
                            <?php if (!$onu['vlan_internet'] && !$onu['vlan_acs']): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr><th class="text-muted">TCONT</th><td id="disp_tcont"><?= $onu['tcont_profile'] ? '<code>'.esc($onu['tcont_profile']).'</code>' : '<span class="text-muted">—</span>' ?></td></tr>
                    <tr><th class="text-muted">PPPoE User</th><td id="disp_pppoe"><code><?= esc($onu['pppoe_user'] ?? '—') ?></code></td></tr>
                    <tr><th class="text-muted">Terdaftar</th><td class="small"><?= date('d/m/Y H:i', strtotime($onu['registered_at'])) ?></td></tr>
                </table>

                <!-- Edit form (hidden by default) -->
                <div id="editForm" class="d-none border-top mt-3 pt-3">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small fw-medium">Nama Pelanggan</label>
                            <input type="text" id="edit_name" class="form-control form-control-sm" value="<?= esc($onu['name'] ?? '') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">VLAN Internet</label>
                            <input type="number" id="edit_vlan_internet" class="form-control form-control-sm" value="<?= esc($onu['vlan_internet'] ?? '') ?>" placeholder="misal: 100">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">VLAN ACS</label>
                            <input type="number" id="edit_vlan_acs" class="form-control form-control-sm" value="<?= esc($onu['vlan_acs'] ?? '') ?>" placeholder="misal: 155">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">TCONT Profile</label>
                            <input type="text" id="edit_tcont" class="form-control form-control-sm" value="<?= esc($onu['tcont_profile'] ?? '') ?>" placeholder="misal: 250M">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">PPPoE Username</label>
                            <input type="text" id="edit_pppoe_user" class="form-control form-control-sm" value="<?= esc($onu['pppoe_user'] ?? '') ?>" placeholder="user@isp">
                        </div>
                    </div>
                    <div id="editResult" class="mt-2 d-none"></div>
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-primary btn-sm" onclick="saveInfo()">
                            <i class="bi bi-check-circle me-1"></i>Simpan
                        </button>
                        <button class="btn btn-light btn-sm" onclick="toggleEdit()">Batal</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sinyal ONU -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-reception-4 me-1"></i>Sinyal Optik</h6>
                <button class="btn btn-sm btn-outline-secondary py-0" id="btnSignal" onclick="loadSignal()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
            <div class="card-body" id="signalBox">
                <div class="text-center py-2">
                    <span class="spinner-border spinner-border-sm text-secondary"></span>
                    <span class="text-muted small ms-2">Mengambil sinyal dari OLT...</span>
                </div>
            </div>
        </div>

        <!-- Status ACS -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-cloud-check me-1"></i>Status ACS</h6>
                <?php if ($acsUpdatedAt): ?>
                    <span class="small text-muted">Cache: <?= date('d/m H:i', strtotime($acsUpdatedAt)) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body" id="acsStatusBox">
                <?php if ($acsInfo): ?>
                <?php
                    $online  = $acsInfo['online'];
                    $lastInf = $acsInfo['last_inform'] ? date('d/m/Y H:i', strtotime($acsInfo['last_inform'])) : '-';
                ?>
                <div class="mb-2 d-flex align-items-center gap-2">
                    <?php if ($online): ?>
                        <span class="badge bg-success fs-6 py-1 px-2"><i class="bi bi-wifi me-1"></i>Online</span>
                    <?php else: ?>
                        <span class="badge bg-secondary fs-6 py-1 px-2"><i class="bi bi-wifi-off me-1"></i>Offline</span>
                    <?php endif; ?>
                    <small class="text-muted">Last inform: <?= $lastInf ?></small>
                </div>
                <table class="table table-sm mb-0">
                    <?php if (!empty($acsInfo['model'])): ?>
                    <tr><th class="text-muted" style="width:45%">Model</th>
                        <td><?= esc($acsInfo['model']) ?> (<?= esc($acsInfo['manufacturer'] ?? '-') ?>)</td></tr>
                    <?php endif; ?>
                </table>
                <div class="mt-2 small text-muted"><i class="bi bi-info-circle me-1"></i>Data dari cache. Klik "Refresh ACS" untuk info lengkap (WAN IP, PPPoE, WiFi).</div>
                <?php else: ?>
                <div class="text-muted small">
                    <?php if ($acsUpdatedAt): ?>
                        ONU tidak ditemukan di ACS cache. Klik "Refresh ACS" untuk cek live.
                    <?php else: ?>
                        Belum ada ACS cache. Klik "Sync Cache" di halaman OLT, atau klik "Refresh ACS" untuk cek live.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit PPPoE & WiFi -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-ethernet me-1"></i>Edit PPPoE (WAN)</h6>
            </div>
            <div class="card-body">
                <?php if ($onu['vlan_internet'] || $onu['vlan_acs'] || $onu['tcont_profile']): ?>
                <div class="d-flex flex-wrap gap-2 mb-3 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
                    <?php if ($onu['vlan_internet']): ?>
                    <span class="badge bg-primary py-1 px-2">
                        <i class="bi bi-diagram-3 me-1"></i>VLAN Internet: <?= $onu['vlan_internet'] ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($onu['vlan_acs']): ?>
                    <span class="badge bg-info text-dark py-1 px-2">
                        <i class="bi bi-hdd-network me-1"></i>VLAN ACS: <?= $onu['vlan_acs'] ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($onu['tcont_profile']): ?>
                    <span class="badge bg-secondary py-1 px-2">
                        <i class="bi bi-speedometer2 me-1"></i>TCONT: <?= esc($onu['tcont_profile']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning py-1 px-2 small mb-3">
                    <i class="bi bi-exclamation-triangle me-1"></i>VLAN belum diset.
                    <a href="#" onclick="toggleEdit();return false">Edit Info ONU</a> untuk mengisi VLAN/TCONT.
                </div>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label small fw-medium">PPPoE Username</label>
                        <input type="text" id="pppoe_user" class="form-control"
                               value="<?= esc($onu['pppoe_user'] ?? '') ?>" placeholder="username@isp">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-medium">PPPoE Password</label>
                        <input type="text" id="pppoe_pass" class="form-control" placeholder="password">
                    </div>
                </div>
                <div id="pppoeResult" class="mt-2 d-none"></div>
                <button class="btn btn-primary btn-sm mt-3" onclick="setPppoe()">
                    <i class="bi bi-cloud-upload me-1"></i>Push ke ONU
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-wifi me-1"></i>Edit WiFi</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label small fw-medium">SSID (Nama WiFi)</label>
                        <input type="text" id="wifi_ssid" class="form-control" placeholder="Nama-WiFi-Saya">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-medium">Password WiFi</label>
                        <input type="text" id="wifi_key" class="form-control" placeholder="min 8 karakter">
                    </div>
                </div>
                <div class="form-text">Perubahan akan langsung dipush ke ONU via TR-069/GenieACS.</div>
                <div id="wifiResult" class="mt-2 d-none"></div>
                <button class="btn btn-primary btn-sm mt-3" onclick="setWifi()">
                    <i class="bi bi-cloud-upload me-1"></i>Push ke ONU
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const ONU_ID = <?= $onu['id'] ?>;

document.addEventListener('DOMContentLoaded', () => {
    loadSignal();
});

function loadSignal() {
    const box = document.getElementById('signalBox');
    const btn = document.getElementById('btnSignal');
    box.innerHTML = '<div class="text-center py-2"><span class="spinner-border spinner-border-sm text-secondary"></span><span class="text-muted small ms-2">Mengambil sinyal dari OLT...</span></div>';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

    fetch(`/onus/${ONU_ID}/signal`)
        .then(r => r.json())
        .then(data => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refresh'; }
            if (!data.success) {
                box.innerHTML = `<div class="text-muted small"><i class="bi bi-x-circle me-1 text-danger"></i>${data.message}</div>`;
                return;
            }
            const s = data.signal;
            const qClass = { good: 'text-success', warn: 'text-warning', bad: 'text-danger' }[data.quality] ?? 'text-muted';
            const qIcon  = { good: 'bi-reception-4', warn: 'bi-reception-2', bad: 'bi-reception-0' }[data.quality] ?? 'bi-reception-4';
            box.innerHTML = `
                <div class="d-flex align-items-center gap-3 mb-2">
                    <i class="bi ${qIcon} fs-3 ${qClass}"></i>
                    <div>
                        <div class="fw-semibold ${qClass}">${s.onu_rx ?? '?'} dBm</div>
                        <div class="small text-muted">ONU RX (sinyal di pelanggan)</div>
                    </div>
                </div>
                <table class="table table-sm mb-0">
                    <tr><th class="text-muted" style="width:50%">OLT RX (dari pelanggan)</th><td class="font-monospace">${s.olt_rx ?? '?'} dBm</td></tr>
                    <tr><th class="text-muted">ONU TX (kirim ke OLT)</th><td class="font-monospace">${s.onu_tx ?? '?'} dBm</td></tr>
                    <tr><th class="text-muted">OLT TX (kirim ke pelanggan)</th><td class="font-monospace">${s.olt_tx ?? '?'} dBm</td></tr>
                </table>`;
        })
        .catch(e => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refresh'; }
            box.innerHTML = `<div class="text-muted small"><i class="bi bi-x-circle me-1 text-danger"></i>Error: ${e.message}</div>`;
        });
}

function toggleEdit() {
    const form = document.getElementById('editForm');
    const btn  = document.getElementById('btnToggleEdit');
    const show = form.classList.contains('d-none');
    form.classList.toggle('d-none', !show);
    btn.innerHTML = show
        ? '<i class="bi bi-x-circle me-1"></i>Batal'
        : '<i class="bi bi-pencil me-1"></i>Edit';
    if (show) document.getElementById('editResult').classList.add('d-none');
}

function saveInfo() {
    const fd = new FormData();
    fd.append('name',           document.getElementById('edit_name').value.trim());
    fd.append('vlan_internet',  document.getElementById('edit_vlan_internet').value.trim());
    fd.append('vlan_acs',       document.getElementById('edit_vlan_acs').value.trim());
    fd.append('tcont_profile',  document.getElementById('edit_tcont').value.trim());
    fd.append('pppoe_user',     document.getElementById('edit_pppoe_user').value.trim());
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    const el = document.getElementById('editResult');
    el.className = 'mt-2 small';
    el.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
    el.classList.remove('d-none');

    fetch(`/onus/${ONU_ID}/update-info`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            el.className = `mt-2 small alert alert-${data.success ? 'success' : 'danger'} py-1`;
            el.textContent = data.message || (data.success ? 'Tersimpan.' : 'Gagal.');
            if (data.success) {
                // Update tampilan langsung tanpa reload
                const name        = document.getElementById('edit_name').value.trim();
                const vInt        = document.getElementById('edit_vlan_internet').value.trim();
                const vAcs        = document.getElementById('edit_vlan_acs').value.trim();
                const tcont       = document.getElementById('edit_tcont').value.trim();
                const pppoe       = document.getElementById('edit_pppoe_user').value.trim();

                document.getElementById('disp_name').textContent  = name || '-';
                document.getElementById('disp_tcont').innerHTML   = tcont ? `<code>${tcont}</code>` : '<span class="text-muted">—</span>';
                document.getElementById('disp_pppoe').innerHTML   = `<code>${pppoe || '—'}</code>`;

                let vlanHtml = '';
                if (vInt) vlanHtml += `<span class="badge bg-primary me-1">Internet: ${vInt}</span>`;
                if (vAcs) vlanHtml += `<span class="badge bg-secondary">ACS: ${vAcs}</span>`;
                if (!vInt && !vAcs) vlanHtml = '<span class="text-muted">—</span>';
                document.getElementById('disp_vlan').innerHTML = vlanHtml;

                // Sync PPPoE field di form bawah
                if (pppoe) document.getElementById('pppoe_user').value = pppoe;
            }
        })
        .catch(e => {
            el.className = 'mt-2 small alert alert-danger py-1';
            el.textContent = 'Error: ' + e.message;
        });
}

function loadAcsInfo() {
    const box = document.getElementById('acsStatusBox');
    const btn = document.getElementById('btnLoadAcs');
    box.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Memuat dari ACS...</div>';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...'; }

    fetch(`/onus/${ONU_ID}/acs-info`)
        .then(r => r.json())
        .then(data => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refresh ACS'; }
            if (!data.success) {
                box.innerHTML = `<div class="text-danger small"><i class="bi bi-x-circle me-1"></i>${data.message}</div>`;
                return;
            }
            const i = data.info;
            const online = i.online
                ? '<span class="badge bg-success">Online</span>'
                : '<span class="badge bg-secondary">Offline</span>';
            const lastInf = i.last_inform ? new Date(i.last_inform).toLocaleString('id') : '-';

            box.innerHTML = `
                <div class="mb-2">${online} <small class="text-muted ms-2">Last Inform: ${lastInf}</small></div>
                <table class="table table-sm mb-0">
                    <tr><th class="text-muted" style="width:45%">Model</th><td>${i.model || '-'} (${i.manufacturer || '-'})</td></tr>
                    <tr><th class="text-muted">IP (PPPoE)</th><td>${i.wan?.ip || '-'}</td></tr>
                    <tr><th class="text-muted">PPPoE User</th><td>${i.wan?.pppoe_user || '-'}</td></tr>
                    <tr><th class="text-muted">WAN Status</th><td>${i.wan?.status || '-'}</td></tr>
                    <tr><th class="text-muted">Uptime</th><td>${i.wan?.uptime ? Math.floor(i.wan.uptime/3600)+'j '+Math.floor((i.wan.uptime%3600)/60)+'m' : '-'}</td></tr>
                    <tr><th class="text-muted">WiFi SSID</th><td>${i.wifi?.ssid || '-'}</td></tr>
                </table>`;

            // Pre-fill WiFi SSID field
            if (i.wifi?.ssid) document.getElementById('wifi_ssid').value = i.wifi.ssid;
            if (i.wan?.pppoe_user) document.getElementById('pppoe_user').value = i.wan.pppoe_user;
        })
        .catch(e => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refresh ACS'; }
            box.innerHTML = `<div class="text-danger small"><i class="bi bi-x-circle me-1"></i>Error: ${e.message}</div>`;
        });
}

function setPppoe() {
    const fd = new FormData();
    fd.append('action', 'pppoe');
    fd.append('pppoe_user', document.getElementById('pppoe_user').value.trim());
    fd.append('pppoe_pass', document.getElementById('pppoe_pass').value.trim());
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    const el = document.getElementById('pppoeResult');
    el.className = 'mt-2';
    el.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';

    fetch(`/onus/${ONU_ID}/acs-set`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            el.className = `mt-2 small alert alert-${data.success ? 'success' : 'danger'}`;
            el.textContent = data.success ? 'PPPoE berhasil dipush ke ONU.' : (data.message || 'Gagal.');
        });
}

function setWifi() {
    const fd = new FormData();
    fd.append('action', 'wifi');
    fd.append('ssid',     document.getElementById('wifi_ssid').value.trim());
    fd.append('wifi_key', document.getElementById('wifi_key').value.trim());
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    const el = document.getElementById('wifiResult');
    el.className = 'mt-2';
    el.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';

    fetch(`/onus/${ONU_ID}/acs-set`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            el.className = `mt-2 small alert alert-${data.success ? 'success' : 'danger'}`;
            el.textContent = data.success ? 'WiFi berhasil diubah.' : (data.message || 'Gagal.');
        });
}

function reboot() {
    if (!confirm('Reboot ONU ini via ACS? ONU akan mati sebentar.')) return;
    const btn = document.getElementById('btnReboot');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'reboot');
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch(`/onus/${ONU_ID}/acs-set`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            alert(data.success ? 'Perintah reboot terkirim ke ONU.' : ('Gagal: ' + data.message));
        });
}
</script>
<?= $this->endSection() ?>
