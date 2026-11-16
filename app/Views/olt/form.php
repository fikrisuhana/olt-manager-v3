<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center space-x-3 mb-4">
        <a href="/olts" class="btn btn-secondary btn-sm btn-icon-only" title="Kembali">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight"><?= $olt ? 'Edit OLT' : 'Tambah OLT Baru' ?></h1>
            <p class="text-xs text-slate-500">Konfigurasi perangkat, koneksi telnet &amp; mode provisioning</p>
        </div>
    </div>

    <form action="<?= $olt ? "/olts/{$olt['id']}/update" : '/olts/store' ?>" method="POST" class="space-y-3">
        <?= csrf_field() ?>

        <!-- Informasi OLT -->
        <div class="card !mb-0">
            <div class="card-header">
                <div class="card-title"><i data-lucide="network" class="w-4 h-4 text-slate-400"></i> Informasi OLT</div>
            </div>
            <div class="form-grid" style="grid-template-columns:2fr 1fr">
                <div class="form-group">
                    <label class="form-label">Nama OLT <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" class="form-input"
                           value="<?= esc($olt['name'] ?? old('name')) ?>"
                           placeholder="misal: OLT-PURBALINGGA-1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Brand <span class="text-rose-500">*</span></label>
                    <select name="brand" id="oltBrand" class="form-select">
                        <?php $brands = ['ZTE', 'Fiberhome', 'Huawei', 'Nokia', 'Calix']; ?>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= $b ?>" <?= ($olt['brand'] ?? 'ZTE') === $b ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Model</label>
                    <input type="text" name="model" id="oltModel" class="form-input font-mono"
                           value="<?= esc($olt['model'] ?? old('model', 'C320')) ?>"
                           placeholder="C320, AN6000, MA5800 ...">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <input type="text" name="description" class="form-input"
                           value="<?= esc($olt['description'] ?? '') ?>"
                           placeholder="Opsional">
                </div>
            </div>
        </div>

        <!-- Koneksi Telnet -->
        <div class="card !mb-0">
            <div class="card-header">
                <div class="card-title"><i data-lucide="terminal" class="w-4 h-4 text-slate-400"></i> Koneksi Telnet</div>
            </div>
            <div class="form-grid" style="grid-template-columns:2fr 1fr">
                <div class="form-group">
                    <label class="form-label">IP Address <span class="text-rose-500">*</span></label>
                    <input type="text" name="ip" class="form-input font-mono"
                           value="<?= esc($olt['ip'] ?? old('ip')) ?>"
                           placeholder="192.168.1.1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Port Telnet</label>
                    <input type="number" name="telnet_port" class="form-input"
                           value="<?= esc($olt['telnet_port'] ?? 23) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Username <span class="text-rose-500">*</span></label>
                    <input type="text" name="telnet_user" class="form-input"
                           value="<?= esc($olt['telnet_user'] ?? old('telnet_user')) ?>"
                           placeholder="zte" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password <span class="text-rose-500"><?= $olt ? '' : '*' ?></span></label>
                    <input type="password" name="telnet_pass" class="form-input"
                           placeholder="<?= $olt ? 'Kosongkan jika tidak ganti' : 'Password' ?>"
                           <?= $olt ? '' : 'required' ?>>
                </div>
                <div class="form-group" style="grid-column: span 2">
                    <label class="form-label">Enable Password <span class="font-normal text-slate-400">(opsional — isi jika OLT minta password saat <code class="font-mono">enable</code>)</span></label>
                    <input type="password" name="enable_password" class="form-input"
                           placeholder="<?= ($olt && !empty($olt['enable_password'])) ? '(sudah diset — kosongkan jika tidak ingin mengubah)' : 'Kosongkan jika tidak ada' ?>">
                </div>
                <div class="form-group" id="fwVersionCol">
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
                    <div class="text-[11px] text-slate-400 mt-1">Kosongkan jika tidak tahu — auto detect.</div>
                </div>
            </div>
        </div>

        <!-- SNMP -->
        <div class="card !mb-0">
            <div class="card-header">
                <div class="card-title"><i data-lucide="radio-tower" class="w-4 h-4 text-slate-400"></i> SNMP <span class="font-normal text-slate-400 text-xs">(opsional)</span></div>
            </div>
            <div class="form-grid" style="grid-template-columns:2fr 1fr">
                <div class="form-group">
                    <label class="form-label">SNMP Community</label>
                    <input type="text" name="snmp_community" class="form-input font-mono"
                           value="<?= esc($olt['snmp_community'] ?? 'public') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">SNMP Port</label>
                    <input type="number" name="snmp_port" class="form-input"
                           value="<?= esc($olt['snmp_port'] ?? 161) ?>">
                </div>
            </div>
        </div>

        <!-- Mode ACS -->
        <div class="card !mb-0">
            <div class="card-header">
                <div class="card-title"><i data-lucide="cloud" class="w-4 h-4 text-slate-400"></i> Mode Pengoperasian &amp; ACS (TR-069)</div>
            </div>
            <div class="form-group">
                <label class="form-label">Gunakan ACS / TR-069 di OLT Ini?</label>
                <select name="use_acs" id="useAcsSelect" class="form-select">
                    <option value="1" <?= ($olt['use_acs'] ?? 1) == 1 ? 'selected' : '' ?>>
                        ✅ Ya — Gunakan GenieACS / TR-069 (Auto-push ACS &amp; DHCP Mgmt)
                    </option>
                    <option value="0" <?= ($olt['use_acs'] ?? 1) == 0 ? 'selected' : '' ?>>
                        🚫 Tidak — Standalone OLT / Pure OMCI (Tanpa GenieACS)
                    </option>
                </select>
                <div class="text-[11px] text-slate-400 mt-1">Jika <strong>Tidak</strong>, registrasi ONU akan murni mengonfigurasi OMCI PPPoE tanpa perintah ACS TR-069.</div>
            </div>
            <div class="form-group">
                <label class="form-label">ACS URL (TR-069)</label>
                <input type="text" name="acs_url" class="form-input font-mono"
                       value="<?= esc($olt['acs_url'] ?? '') ?>"
                       placeholder="http://136.1.1.8:7547">
                <div class="text-[11px] text-slate-400 mt-1">URL CWMP GenieACS — diset ke ONU via <code class="font-mono">tr069-mgmt 1 acs</code> saat register (ZTE).</div>
            </div>
            <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr">
                <div class="form-group">
                    <label class="form-label">ACS Username</label>
                    <input type="text" name="acs_user" class="form-input"
                           value="<?= esc($olt['acs_user'] ?? '') ?>" placeholder="acs">
                    <div class="text-[11px] text-slate-400 mt-1">Username autentikasi GenieACS (opsional).</div>
                </div>
                <div class="form-group">
                    <label class="form-label">ACS Password</label>
                    <input type="text" name="acs_pass" class="form-input"
                           value="<?= esc($olt['acs_pass'] ?? '') ?>" placeholder="acspass">
                    <div class="text-[11px] text-slate-400 mt-1">Password autentikasi GenieACS (opsional).</div>
                </div>
                <div class="form-group" id="pppoeProfileCol">
                    <label class="form-label">PPPoE VLAN Profile</label>
                    <input type="text" name="pppoe_vlan_profile" class="form-input font-mono"
                           value="<?= esc($olt['pppoe_vlan_profile'] ?? 'PPPOE') ?>" placeholder="PPPOE">
                    <div class="text-[11px] text-slate-400 mt-1">Nama vlan-profile untuk wan-ip PPPoE di pon-onu-mng.</div>
                </div>
            </div>
        </div>

        <!-- Provisioning (ZTE) -->
        <div class="card !mb-0" id="provisioningSection">
            <div class="card-header" id="provisioningHeader">
                <div class="card-title"><i data-lucide="sliders-horizontal" class="w-4 h-4 text-slate-400"></i> Konfigurasi Provisioning</div>
            </div>
            <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span class="form-label !mb-0">TCONT &amp; Traffic Profiles</span>
                <button type="button" class="btn btn-primary btn-sm" id="btnSyncTcont" onclick="syncTcont()">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Sync dari OLT
                </button>
                <span id="syncTcontResult" class="text-xs"></span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <div class="text-[11px] text-slate-400 mb-1">TCONT / DBA Profile</div>
                    <div class="border border-slate-200 rounded-lg p-2 bg-white" id="tcontContainer" style="min-height:52px">
                        <div id="tcontTags" class="flex flex-wrap gap-1 mb-1"></div>
                        <div class="flex gap-1">
                            <input type="text" id="tcontNewInput" class="form-input !min-h-[30px]" placeholder="Tambah..." style="max-width:130px"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTcontProfile();}">
                            <button type="button" class="btn btn-secondary btn-sm btn-icon-only !min-h-[30px]" onclick="addTcontProfile()">+</button>
                        </div>
                    </div>
                    <textarea name="tcont_profiles" id="tcontHidden" class="hidden"></textarea>
                </div>
                <div>
                    <div class="text-[11px] text-slate-400 mb-1">Traffic / Bandwidth Profile</div>
                    <div class="border border-slate-200 rounded-lg p-2 bg-white" id="trafficContainer" style="min-height:52px">
                        <div id="trafficTags" class="flex flex-wrap gap-1 mb-1"></div>
                        <div class="flex gap-1">
                            <input type="text" id="trafficNewInput" class="form-input !min-h-[30px]" placeholder="Tambah..." style="max-width:130px"
                                   onkeydown="if(event.key==='Enter'){event.preventDefault();addTrafficProfile();}">
                            <button type="button" class="btn btn-secondary btn-sm btn-icon-only !min-h-[30px]" onclick="addTrafficProfile()">+</button>
                        </div>
                    </div>
                    <textarea name="traffic_profiles" id="trafficHidden" class="hidden"></textarea>
                </div>
            </div>
            <div class="text-[11px] text-slate-400 mt-2">Tampil sebagai dropdown saat register ONU — klik "Sync dari OLT" untuk auto-isi.</div>
        </div>

        <!-- Aksi -->
        <div class="card !mb-0">
            <div class="flex gap-2 items-center flex-wrap">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> <?= $olt ? 'Simpan Perubahan' : 'Tambah OLT' ?>
                </button>
                <button type="button" class="btn btn-secondary" id="btnTestTelnet" onclick="testTelnet()">
                    <i data-lucide="plug" class="w-4 h-4"></i> Test Koneksi
                </button>
                <button type="button" class="btn btn-sm bg-cyan-600 hover:bg-cyan-700 text-white border-0" id="btnDebugConnect" onclick="debugConnect()">
                    <i data-lucide="terminal" class="w-3.5 h-3.5"></i> Debug Login
                </button>
                <a href="/olts" class="btn btn-secondary">Batal</a>
                <span id="testResult" class="text-xs"></span>
            </div>
        </div>
    </form>
</div>

<!-- Modal Debug Login -->
<div id="debugModal" class="modal-overlay">
    <div class="modal-card" style="max-width:760px">
        <div class="card-header !mb-3">
            <div class="card-title" id="debugModalTitle">Debug Login</div>
            <button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="App.closeModal('debugModal')">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="terminal-window">
            <div class="terminal-header">
                <span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span>
                <span class="text-slate-500 text-[10px] ml-2">debug-login</span>
            </div>
            <pre id="debugLogBody" class="terminal-body" style="max-height:400px;white-space:pre-wrap;margin:0"></pre>
        </div>
        <div class="flex justify-end mt-4">
            <button type="button" class="btn btn-secondary" onclick="App.closeModal('debugModal')">Tutup</button>
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
    const ZTE_ONLY = ['fwVersionCol', 'pppoeProfileCol', 'provisioningSection'];
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
    tag.className = 'chip chip-neutral font-mono';
    tag.dataset.name = name;
    tag.innerHTML = `<span>${App.escapeHtml(name)}</span>`
        + `<button type="button" class="text-slate-400 hover:text-rose-600 font-bold ml-0.5"
            onclick="this.closest('[data-name]').remove();updateTcontHidden()">✕</button>`;
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
    tag.className = 'chip chip-neutral font-mono';
    tag.dataset.name = name;
    tag.innerHTML = `<span>${App.escapeHtml(name)}</span>`
        + `<button type="button" class="text-slate-400 hover:text-rose-600 font-bold ml-0.5"
            onclick="this.closest('[data-name]').remove();updateTrafficHidden()">✕</button>`;
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

async function syncTcont() {
    const btn = document.getElementById('btnSyncTcont');
    const res = document.getElementById('syncTcontResult');
    App.setLoading(btn, true, '');
    res.textContent = '';

    const fd = new FormData();
    fd.append('ip',          document.querySelector('[name="ip"]').value.trim());
    fd.append('telnet_port', document.querySelector('[name="telnet_port"]').value || '23');
    fd.append('telnet_user', document.querySelector('[name="telnet_user"]').value.trim());
    fd.append('telnet_pass', document.querySelector('[name="telnet_pass"]').value);
    fd.append('brand',       document.querySelector('[name="brand"]').value);
    fd.append('model',       document.querySelector('[name="model"]').value);
    fd.append('olt_id',      '<?= $olt['id'] ?? 0 ?>');

    try {
        const data = await App.readJson(await App.fetch('/olts/fetch-tcont', { method: 'POST', body: fd }));
        App.setLoading(btn, false);
        if (data.success) {
            document.getElementById('tcontTags').innerHTML = '';
            data.profiles.forEach(p => addTcontTag(p));
            document.getElementById('trafficTags').innerHTML = '';
            (data.traffic_profiles || []).forEach(p => addTrafficTag(p));
            res.className = 'text-xs text-emerald-600 font-medium';
            res.textContent = `✅ TCONT: ${data.count} | Traffic: ${(data.traffic_profiles||[]).length} profile`;
        } else {
            res.className = 'text-xs text-rose-600 font-medium';
            res.textContent = '✖ ' + data.message;
        }
    } catch (e) {
        App.setLoading(btn, false);
        res.className = 'text-xs text-rose-600 font-medium';
        res.textContent = 'Error: ' + e.message;
    }
}

// ── Debug Login Step-by-Step ────────────────────────────────────
async function debugConnect() {
    const btn = document.getElementById('btnDebugConnect');
    App.setLoading(btn, true, 'Connecting...');

    const fd = new FormData();
    fd.append('ip',              document.querySelector('[name="ip"]').value.trim());
    fd.append('telnet_port',     document.querySelector('[name="telnet_port"]').value || '23');
    fd.append('telnet_user',     document.querySelector('[name="telnet_user"]').value.trim());
    fd.append('telnet_pass',     document.querySelector('[name="telnet_pass"]').value);
    fd.append('enable_password', document.querySelector('[name="enable_password"]').value);
    fd.append('olt_id',          '<?= $olt['id'] ?? 0 ?>');

    try {
        const data = await App.readJson(await App.fetch('/olts/debug-connect', { method: 'POST', body: fd }));
        App.setLoading(btn, false);

        const logHtml = (data.log || []).map(line => {
            let cls = 'text-slate-400';
            if (line.startsWith('[OK]'))    cls = 'text-emerald-400';
            if (line.startsWith('[ERROR]')) cls = 'text-rose-400 font-bold';
            if (line.startsWith('[WARN]'))  cls = 'text-amber-400';
            if (line.startsWith('[INFO]'))  cls = 'text-sky-400';
            return `<div class="${cls}">${App.escapeHtml(line)}</div>`;
        }).join('');

        document.getElementById('debugLogBody').innerHTML = logHtml || '<div class="text-slate-500">Tidak ada log.</div>';
        document.getElementById('debugModalTitle').textContent = data.success ? '✓ Debug Login — Berhasil' : '✗ Debug Login — Gagal';
        App.openModal('debugModal');
    } catch (e) {
        App.setLoading(btn, false);
        App.showToast('Error: ' + e.message, 'error');
    }
}

// ── Test Koneksi Telnet ─────────────────────────────────────────
async function testTelnet() {
    const btn = document.getElementById('btnTestTelnet');
    const res = document.getElementById('testResult');
    App.setLoading(btn, true, 'Testing...');
    res.textContent = '';

    const fd = new FormData();
    fd.append('ip',          document.querySelector('[name="ip"]').value.trim());
    fd.append('telnet_port', document.querySelector('[name="telnet_port"]').value || '23');
    fd.append('telnet_user', document.querySelector('[name="telnet_user"]').value.trim());
    fd.append('telnet_pass', document.querySelector('[name="telnet_pass"]').value);
    fd.append('olt_id',      '<?= $olt['id'] ?? 0 ?>');

    try {
        const data = await App.readJson(await App.fetch('/olts/test-telnet', { method: 'POST', body: fd }));
        App.setLoading(btn, false);
        res.className = 'text-xs font-medium ml-1 ' + (data.success ? 'text-emerald-600' : 'text-rose-600');
        res.textContent = (data.success ? '✅ ' : '✖ ') + data.message;
    } catch (e) {
        App.setLoading(btn, false);
        res.className = 'text-xs font-medium ml-1 text-rose-600';
        res.textContent = 'Error: ' + e.message;
    }
}
</script>
<?= $this->endSection() ?>
