<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/olts/<?= $onu['olt_id'] ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
    <div>
        <span class="badge bg-secondary"><?= esc($onu['onu_type'] ?? '-') ?></span>
        <span class="text-muted small ms-1"><?= esc("{$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']}") ?></span>
    </div>
    <div class="ms-auto d-flex gap-2">
        <button class="btn btn-sm btn-outline-info" onclick="loadAcsInfo()">
            <i class="bi bi-cloud-download me-1"></i>Muat Info ACS
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
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-router me-1"></i>Info ONU</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th class="text-muted" style="width:40%">SN</th><td><code><?= esc($onu['sn']) ?></code></td></tr>
                    <tr><th class="text-muted">Nama</th><td><?= esc($onu['name'] ?? '-') ?></td></tr>
                    <tr><th class="text-muted">OLT</th><td><?= esc($onu['olt_name'] ?? '-') ?> <span class="text-muted small">(<?= esc($onu['olt_ip'] ?? '') ?>)</span></td></tr>
                    <tr><th class="text-muted">Port</th><td><?= esc("{$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']}") ?></td></tr>
                    <tr><th class="text-muted">Tipe</th><td><?= esc($onu['onu_type'] ?? '-') ?></td></tr>
                    <?php if (!empty($onu['vlan_internet']) || !empty($onu['vlan_acs'])): ?>
                    <tr>
                        <th class="text-muted">VLAN</th>
                        <td class="small">
                            <?php if ($onu['vlan_internet']): ?>
                                <span class="badge bg-primary me-1">Internet: <?= $onu['vlan_internet'] ?></span>
                            <?php endif; ?>
                            <?php if ($onu['vlan_acs']): ?>
                                <span class="badge bg-secondary">ACS: <?= $onu['vlan_acs'] ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($onu['tcont_profile'])): ?>
                    <tr><th class="text-muted">TCONT</th><td><code><?= esc($onu['tcont_profile']) ?></code></td></tr>
                    <?php endif; ?>
                    <tr><th class="text-muted">Terdaftar</th><td class="small"><?= date('d/m/Y H:i', strtotime($onu['registered_at'])) ?></td></tr>
                    <?php if (!empty($onu['pppoe_user'])): ?>
                    <tr><th class="text-muted">PPPoE User</th><td><code><?= esc($onu['pppoe_user']) ?></code></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Status ACS -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-cloud-check me-1"></i>Status ACS</h6>
            </div>
            <div class="card-body" id="acsStatusBox">
                <div class="text-muted small">Klik "Muat Info ACS" untuk melihat status real-time.</div>
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

function loadAcsInfo() {
    const box = document.getElementById('acsStatusBox');
    box.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Memuat...</div>';

    fetch(`/onus/${ONU_ID}/acs-info`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                box.innerHTML = `<div class="text-danger small">${data.message}</div>`;
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
            box.innerHTML = `<div class="text-danger small">Error: ${e.message}</div>`;
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
