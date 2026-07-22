<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="<?= $olt ? "/olts/{$olt['id']}/update" : '/olts/store' ?>" method="POST">
                    <?= csrf_field() ?>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                        Informasi OLT
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label">Nama OLT <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= esc($olt['name'] ?? old('name')) ?>"
                                   placeholder="misal: OLT-PURBALINGGA-1" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Brand <span class="text-danger">*</span></label>
                            <select name="brand" id="oltBrand" class="form-select">
                                <?php $brands = ['ZTE', 'Fiberhome', 'Huawei', 'Nokia', 'Calix']; ?>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= $b ?>" <?= ($olt['brand'] ?? 'ZTE') === $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-8">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" id="oltModel" class="form-control"
                                   value="<?= esc($olt['model'] ?? old('model', 'C320')) ?>"
                                   placeholder="C320, AN6000, MA5800 ...">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Deskripsi</label>
                            <input type="text" name="description" class="form-control"
                                   value="<?= esc($olt['description'] ?? '') ?>"
                                   placeholder="Opsional">
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                        Koneksi Telnet
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-8">
                            <label class="form-label">IP Address <span class="text-danger">*</span></label>
                            <input type="text" name="ip" class="form-control"
                                   value="<?= esc($olt['ip'] ?? old('ip')) ?>"
                                   placeholder="192.168.1.1" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Port Telnet</label>
                            <input type="number" name="telnet_port" class="form-control"
                                   value="<?= esc($olt['telnet_port'] ?? 23) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="telnet_user" class="form-control"
                                   value="<?= esc($olt['telnet_user'] ?? old('telnet_user')) ?>"
                                   placeholder="zte" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Password <span class="text-danger"><?= $olt ? '' : '*' ?></span></label>
                            <input type="password" name="telnet_pass" class="form-control"
                                   placeholder="<?= $olt ? 'Kosongkan jika tidak ganti' : 'Password' ?>"
                                   <?= $olt ? '' : 'required' ?>>
                        </div>
                        <div class="col-8">
                            <label class="form-label">Enable Password <span class="text-muted small">(opsional — isi jika OLT minta password saat <code>enable</code>)</span></label>
                            <input type="password" name="enable_password" class="form-control"
                                   placeholder="<?= ($olt && !empty($olt['enable_password'])) ? '(sudah diset — kosongkan jika tidak ingin mengubah)' : 'Kosongkan jika tidak ada' ?>">
                        </div>
                        <div class="col-4" id="fwVersionCol">
                            <label class="form-label">Firmware Version</label>
                            <select name="firmware_version" class="form-select">
                                <option value="">— Auto detect —</option>
                                <?php
                                $fwSelected = is_array($olt) ? ($olt['firmware_version'] ?? '') : '';
                                foreach (['1.2' => 'v1.2 (service hsi)', '2.1' => 'v2.1 (service int)'] as $val => $label):
                                ?>
                                <option value="<?= $val ?>" <?= $fwSelected === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text text-muted">Kosongkan jika tidak tahu — auto detect.</div>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                        SNMP (Opsional)
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-8">
                            <label class="form-label">SNMP Community</label>
                            <input type="text" name="snmp_community" class="form-control"
                                   value="<?= esc($olt['snmp_community'] ?? 'public') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label">SNMP Port</label>
                            <input type="number" name="snmp_port" class="form-control"
                                   value="<?= esc($olt['snmp_port'] ?? 161) ?>">
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                        ACS / GenieACS
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label">ACS URL (TR-069)</label>
                            <input type="text" name="acs_url" class="form-control"
                                   value="<?= esc($olt['acs_url'] ?? '') ?>"
                                   placeholder="http://136.1.1.8:7547">
                            <div class="form-text">URL CWMP GenieACS — diset ke ONU via <code>tr069-mgmt 1 acs</code> saat register (ZTE).</div>
                        </div>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <label class="form-label">ACS Username</label>
                            <input type="text" name="acs_user" class="form-control"
                                   value="<?= esc($olt['acs_user'] ?? '') ?>"
                                   placeholder="acs">
                            <div class="form-text">Username autentikasi GenieACS (opsional).</div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">ACS Password</label>
                            <input type="text" name="acs_pass" class="form-control"
                                   value="<?= esc($olt['acs_pass'] ?? '') ?>"
                                   placeholder="acspass">
                            <div class="form-text">Password autentikasi GenieACS (opsional).</div>
                        </div>
                        <div class="col-4" id="pppoeProfileCol">
                            <label class="form-label">PPPoE VLAN Profile</label>
                            <input type="text" name="pppoe_vlan_profile" class="form-control"
                                   value="<?= esc($olt['pppoe_vlan_profile'] ?? 'PPPOE') ?>"
                                   placeholder="PPPOE">
                            <div class="form-text">Nama vlan-profile untuk wan-ip PPPoE di pon-onu-mng.</div>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" id="provisioningHeader" style="font-size:.75rem;letter-spacing:.05em">
                        Konfigurasi Provisioning
                    </h6>

                    <div class="mb-3" id="provisioningSection">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <label class="form-label mb-0">TCONT &amp; Traffic Profiles</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnSyncTcont" onclick="syncTcont()">
                                <i class="bi bi-arrow-repeat me-1"></i>Sync dari OLT
                            </button>
                            <span id="syncTcontResult" class="small"></span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-text mb-1">TCONT / DBA Profile</div>
                                <div class="border rounded p-2 bg-white" id="tcontContainer" style="min-height:48px">
                                    <div id="tcontTags" class="d-flex flex-wrap gap-1 mb-1"></div>
                                    <div class="d-flex gap-1">
                                        <input type="text" id="tcontNewInput" class="form-control form-control-sm"
                                               placeholder="Tambah..." style="max-width:120px"
                                               onkeydown="if(event.key==='Enter'){event.preventDefault();addTcontProfile();}">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addTcontProfile()">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <textarea name="tcont_profiles" id="tcontHidden" class="d-none"></textarea>
                            </div>
                            <div class="col-6">
                                <div class="form-text mb-1">Traffic / Bandwidth Profile</div>
                                <div class="border rounded p-2 bg-white" id="trafficContainer" style="min-height:48px">
                                    <div id="trafficTags" class="d-flex flex-wrap gap-1 mb-1"></div>
                                    <div class="d-flex gap-1">
                                        <input type="text" id="trafficNewInput" class="form-control form-control-sm"
                                               placeholder="Tambah..." style="max-width:120px"
                                               onkeydown="if(event.key==='Enter'){event.preventDefault();addTrafficProfile();}">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addTrafficProfile()">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <textarea name="traffic_profiles" id="trafficHidden" class="d-none"></textarea>
                            </div>
                        </div>
                        <div class="form-text mt-1">Tampil sebagai dropdown saat register ONU — klik "Sync dari OLT" untuk auto-isi.</div>
                    </div>

                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i><?= $olt ? 'Simpan Perubahan' : 'Tambah OLT' ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnTestTelnet" onclick="testTelnet()">
                            <i class="bi bi-plug me-1"></i>Test Koneksi
                        </button>
                        <button type="button" class="btn btn-outline-info" id="btnDebugConnect" onclick="debugConnect()">
                            <i class="bi bi-terminal me-1"></i>Debug Login
                        </button>
                        <a href="/olts" class="btn btn-light">Batal</a>
                        <span id="testResult" class="small ms-1"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Debug Login -->
<div class="modal fade" id="debugModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="debugModalTitle">Debug Login</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="debugLogBody" class="bg-dark text-light p-3 rounded" style="font-size:.85rem;max-height:400px;overflow-y:auto;white-space:pre-wrap;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
// ── Model default + field spesifik mengikuti Brand ──────────────
(function initBrandModel() {
    const brand = document.getElementById('oltBrand');
    const model = document.getElementById('oltModel');
    if (!brand || !model) return;
    const DEFAULTS = { 'ZTE':'C320', 'Fiberhome':'AN6000', 'Huawei':'MA5800', 'Nokia':'', 'Calix':'' };
    // Nilai yang boleh ditimpa otomatis (default bawaan tiap brand) — biar input manual aman
    const KNOWN = ['C320','AN6000','MA5800','AN5516'];

    // Field khusus ZTE — disembunyikan untuk Fiberhome (tidak dipakai):
    // TCONT/Traffic profile, Firmware Version (v1.2/v2.1), PPPoE VLAN Profile (pon-onu-mng).
    const ZTE_ONLY = ['fwVersionCol', 'pppoeProfileCol', 'provisioningHeader', 'provisioningSection'];
    function applyBrandFields() {
        const isFh = (brand.value === 'Fiberhome' || brand.value === 'FH');
        ZTE_ONLY.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = isFh ? 'none' : '';
        });
    }

    brand.addEventListener('change', () => {
        const cur = model.value.trim();
        if (cur === '' || KNOWN.includes(cur)) {
            model.value = DEFAULTS[brand.value] ?? '';
        }
        applyBrandFields();
    });
    applyBrandFields(); // set sesuai brand saat load (mis. edit OLT Fiberhome)
})();

// ── TCONT + Traffic Profile tag-input ───────────────────────────
(function initProfiles() {
    const tcont = `<?= esc($olt['tcont_profiles'] ?? '', 'js') ?>`.trim();
    if (tcont) tcont.split('\n').forEach(p => addTcontTag(p.trim()));
    const traffic = `<?= esc($olt['traffic_profiles'] ?? '', 'js') ?>`.trim();
    if (traffic) traffic.split('\n').forEach(p => addTrafficTag(p.trim()));
})();

function addTcontTag(name) {
    name = name.trim();
    if (!name) return;
    const existing = document.querySelectorAll('#tcontTags [data-name]');
    for (const el of existing) {
        if (el.dataset.name.toLowerCase() === name.toLowerCase()) return;
    }
    const tag = document.createElement('span');
    tag.className = 'badge bg-light text-dark border d-inline-flex align-items-center gap-1 py-1 px-2';
    tag.dataset.name = name;
    tag.innerHTML = `<span class="font-monospace">${name}</span>`
        + `<button type="button" class="btn-close ms-1" style="font-size:.55rem"
            onclick="this.closest('[data-name]').remove();updateTcontHidden()"></button>`;
    document.getElementById('tcontTags').appendChild(tag);
    updateTcontHidden();
}

function addTcontProfile() {
    const input = document.getElementById('tcontNewInput');
    addTcontTag(input.value);
    input.value = '';
    input.focus();
}

function updateTcontHidden() {
    const tags  = document.querySelectorAll('#tcontTags [data-name]');
    document.getElementById('tcontHidden').value = Array.from(tags).map(t => t.dataset.name).join('\n');
}

function addTrafficTag(name) {
    name = name.trim();
    if (!name) return;
    for (const el of document.querySelectorAll('#trafficTags [data-name]')) {
        if (el.dataset.name.toLowerCase() === name.toLowerCase()) return;
    }
    const tag = document.createElement('span');
    tag.className = 'badge bg-light text-dark border d-inline-flex align-items-center gap-1 py-1 px-2';
    tag.dataset.name = name;
    tag.innerHTML = `<span class="font-monospace">${name}</span>`
        + `<button type="button" class="btn-close ms-1" style="font-size:.55rem"
            onclick="this.closest('[data-name]').remove();updateTrafficHidden()"></button>`;
    document.getElementById('trafficTags').appendChild(tag);
    updateTrafficHidden();
}

function addTrafficProfile() {
    const input = document.getElementById('trafficNewInput');
    addTrafficTag(input.value);
    input.value = '';
    input.focus();
}

function updateTrafficHidden() {
    const tags = document.querySelectorAll('#trafficTags [data-name]');
    document.getElementById('trafficHidden').value = Array.from(tags).map(t => t.dataset.name).join('\n');
}

function syncTcont() {
    const btn = document.getElementById('btnSyncTcont');
    const res = document.getElementById('syncTcontResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    res.textContent = '';

    const fd = new FormData();
    fd.append('ip',          document.querySelector('[name="ip"]').value.trim());
    fd.append('telnet_port', document.querySelector('[name="telnet_port"]').value || '23');
    fd.append('telnet_user', document.querySelector('[name="telnet_user"]').value.trim());
    fd.append('telnet_pass', document.querySelector('[name="telnet_pass"]').value);
    fd.append('brand',       document.querySelector('[name="brand"]').value);
    fd.append('model',       document.querySelector('[name="model"]').value);
    fd.append('olt_id',      '<?= $olt['id'] ?? 0 ?>');
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch('/olts/fetch-tcont', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sync dari OLT';
            if (data.success) {
                document.getElementById('tcontTags').innerHTML = '';
                data.profiles.forEach(p => addTcontTag(p));
                document.getElementById('trafficTags').innerHTML = '';
                (data.traffic_profiles || []).forEach(p => addTrafficTag(p));
                res.className = 'small text-success';
                res.innerHTML = `<i class="bi bi-check-circle me-1"></i>TCONT: ${data.count} | Traffic: ${(data.traffic_profiles||[]).length} profile`;
            } else {
                res.className = 'small text-danger';
                res.innerHTML = `<i class="bi bi-x-circle me-1"></i>${data.message}`;
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Sync dari OLT';
            res.className = 'small text-danger';
            res.textContent = 'Error: ' + e.message;
        });
}

// ── Debug Login Step-by-Step ────────────────────────────────────
function debugConnect() {
    const btn = document.getElementById('btnDebugConnect');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Connecting...';

    const fd = new FormData();
    fd.append('ip',              document.querySelector('[name="ip"]').value.trim());
    fd.append('telnet_port',     document.querySelector('[name="telnet_port"]').value || '23');
    fd.append('telnet_user',     document.querySelector('[name="telnet_user"]').value.trim());
    fd.append('telnet_pass',     document.querySelector('[name="telnet_pass"]').value);
    fd.append('enable_password', document.querySelector('[name="enable_password"]').value);
    fd.append('olt_id',          '<?= $olt['id'] ?? 0 ?>');
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch('/olts/debug-connect', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-terminal me-1"></i>Debug Login';

            const logHtml = (data.log || []).map(line => {
                let cls = 'text-secondary';
                if (line.startsWith('[OK]'))    cls = 'text-success';
                if (line.startsWith('[ERROR]')) cls = 'text-danger fw-bold';
                if (line.startsWith('[WARN]'))  cls = 'text-warning';
                if (line.startsWith('[INFO]'))  cls = 'text-info';
                return `<div class="${cls}">${line}</div>`;
            }).join('');

            document.getElementById('debugLogBody').innerHTML = logHtml || '<div class="text-muted">Tidak ada log.</div>';
            document.getElementById('debugModalTitle').textContent = data.success ? '✓ Debug Login — Berhasil' : '✗ Debug Login — Gagal';
            new bootstrap.Modal(document.getElementById('debugModal')).show();
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-terminal me-1"></i>Debug Login';
            alert('Error: ' + e.message);
        });
}

// ── Test Koneksi Telnet ─────────────────────────────────────────
function testTelnet() {
    const btn = document.getElementById('btnTestTelnet');
    const res = document.getElementById('testResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
    res.textContent = '';

    const fd = new FormData();
    fd.append('ip',          document.querySelector('[name="ip"]').value.trim());
    fd.append('telnet_port', document.querySelector('[name="telnet_port"]').value || '23');
    fd.append('telnet_user', document.querySelector('[name="telnet_user"]').value.trim());
    fd.append('telnet_pass', document.querySelector('[name="telnet_pass"]').value);
    fd.append('olt_id',      '<?= $olt['id'] ?? 0 ?>');
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch('/olts/test-telnet', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plug me-1"></i>Test Koneksi';
            res.className = 'small ms-1 ' + (data.success ? 'text-success' : 'text-danger');
            res.innerHTML = (data.success ? '<i class="bi bi-check-circle me-1"></i>' : '<i class="bi bi-x-circle me-1"></i>') + data.message;
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plug me-1"></i>Test Koneksi';
            res.className = 'small ms-1 text-danger';
            res.textContent = 'Error: ' + e.message;
        });
}
</script>
<?= $this->endSection() ?>
