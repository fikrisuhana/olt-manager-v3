<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<!-- Page Header -->
<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="refresh-cw" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Migrasi &amp; Registrasi Massal</h1>
            <p class="text-xs text-slate-500">Registrasi masal seluruh ONU baru/pindahan dengan mode <b>Pure Transparent Bridge (Nembusin VLAN)</b>.</p>
        </div>
    </div>
    <span class="chip chip-info" id="scannedCountBadge">Ready Scan</span>
</div>

<!-- 1. Pengaturan Preset Migrasi -->
<div class="card !mb-0">
    <div class="card-header">
        <div class="card-title"><i data-lucide="sliders-horizontal" class="w-4 h-4 text-blue-600"></i> 1. Pengaturan Preset Migrasi (Global)</div>
    </div>
    <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
        <!-- Select OLT -->
        <div class="form-group">
            <label class="form-label">Pilih OLT Target</label>
            <select id="oltSelect" class="form-select" onchange="changeOlt(this.value)">
                <?php foreach ($olts as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $selectedOlt && $selectedOlt['id'] == $o['id'] ? 'selected' : '' ?>>
                        <?= esc($o['name']) ?> (<?= esc($o['brand']) ?> - <?= esc($o['ip']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- VLAN Internet Target -->
        <div class="form-group">
            <label class="form-label">VLAN Internet (PPPoE/Service)</label>
            <div class="flex gap-1.5">
                <select id="vlanInternetSelect" class="form-select" onchange="document.getElementById('vlanInternetInput').value = this.value">
                    <option value="150">VLAN 150 (Default)</option>
                </select>
                <input type="number" id="vlanInternetInput" class="form-input" placeholder="150" value="150" min="1" max="4094" style="max-width:90px" title="Atau ketik VLAN ID manual">
            </div>
        </div>

        <!-- VLAN ACS / Management -->
        <?php $useAcsDefault = (int)($selectedOlt['use_acs'] ?? 1) === 1; ?>
        <div class="form-group">
            <label class="form-label flex items-center justify-between">
                <span>VLAN ACS / Management</span>
                <label class="flex items-center gap-1.5 cursor-pointer select-none font-normal">
                    <input type="checkbox" id="useAcsToggle" class="accent-blue-600 w-3.5 h-3.5"
                           onchange="toggleUseAcs()" <?= $useAcsDefault ? 'checked' : '' ?>>
                    <span class="text-[11px] text-slate-500">Pakai ACS</span>
                </label>
            </label>
            <input type="number" id="vlanAcsInput" class="form-input"
                   placeholder="100" value="100" min="1" max="4094"
                   <?= $useAcsDefault ? '' : 'disabled' ?>
                   title="VLAN manajemen TR-069. Harus beda dari VLAN internet.">
            <div class="text-[11px] text-slate-400 mt-1" id="vlanAcsHint">
                <?= $useAcsDefault
                    ? 'Wajib beda dari VLAN internet — dipakai <code class="font-mono">service acs</code> + <code class="font-mono">tr069-mgmt</code>.'
                    : 'ACS dimatikan di setting OLT ini — ONU diregistrasi tanpa <code class="font-mono">service acs</code>.' ?>
            </div>
        </div>

        <!-- Traffic / Bandwidth Profile -->
        <div class="form-group">
            <label class="form-label">Traffic Profile (Bandwidth)</label>
            <select id="trafficSelect" class="form-select">
                <option value="">-- Tanpa traffic-limit --</option>
                <?php
                $traffics = array_values(array_filter(array_map('trim', explode("\n", $selectedOlt['traffic_profiles'] ?? ''))));
                foreach ($traffics as $tf):
                ?>
                    <option value="<?= esc($tf) ?>"><?= esc($tf) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Tipe ONU -->
        <div class="form-group">
            <label class="form-label">Tipe ONU (onu type)</label>
            <input type="text" id="onuTypeInput" class="form-input font-mono"
                   value="ALL-ONT" list="migOnuTypeList" placeholder="ALL-ONT"
                   title="Harus nama onu-type yang ada di OLT (show gpon onu-type)">
            <datalist id="migOnuTypeList">
                <?php foreach (($onu_types ?? []) as $t): ?>
                    <option value="<?= esc($t) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <!-- TCONT Profile -->
        <div class="form-group">
            <label class="form-label">TCONT Profile</label>
            <select id="tcontSelect" class="form-select">
                <option value="">-- Pilih TCONT --</option>
                <?php
                $tconts = array_values(array_filter(array_map('trim', explode("\n", $selectedOlt['tcont_profiles'] ?? ''))));
                foreach ($tconts as $tc):
                ?>
                    <option value="<?= esc($tc) ?>"><?= esc($tc) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="text-[11px] text-slate-400 mt-1">
                Pakai profil <b>type 4 maximum</b> (best-effort). Profil <code class="font-mono">fixed</code>/<code class="font-mono">assured</code>
                mem-booking bandwidth per ONU — 1G fixed cuma muat 1 ONU per PON port.
            </div>
        </div>

        <!-- Skema Auto-Name -->
        <div class="form-group">
            <label class="form-label">Skema Nama Pelanggan</label>
            <select id="nameSchemeSelect" class="form-select" onchange="toggleCustomPrefix()">
                <option value="scheme_1">Pelanggan_{BOARD}_{SLOT}_{PORT}_{INDEX} (Pelanggan_1_1_1_01)</option>
                <option value="scheme_2">Pelanggan_001 sekuensial (Pelanggan_001, Pelanggan_002)</option>
                <option value="scheme_3">Custom Prefix (Misal: User_HW_01)</option>
            </select>
        </div>

        <!-- Jeda Antar ONU -->
        <div class="form-group">
            <label class="form-label">Jeda Antar ONU (Prevent OLT Load)</label>
            <select id="delaySelect" class="form-select">
                <option value="1000" selected>1 Detik (Standar)</option>
                <option value="2000">2 Detik (Rekomendasi Aman)</option>
                <option value="3000">3 Detik (Sangat Aman)</option>
                <option value="0">0 Detik (Tanpa Jeda)</option>
            </select>
        </div>

        <!-- Custom Prefix (hidden default) -->
        <div class="form-group hidden" id="customPrefixBox">
            <label class="form-label">Custom Prefix Text</label>
            <input type="text" id="customPrefixInput" class="form-input" placeholder="User" value="User">
        </div>
    </div>
</div>

<!-- 2 & 3. Action & Scan Bar -->
<div class="card !mb-0">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3 flex-wrap">
            <button class="btn btn-primary" id="btnScan" onclick="startScan()">
                <i data-lucide="search" class="w-4 h-4"></i> 2. Scan Semua ONU Unconfigured
            </button>
            <span class="text-xs text-slate-400" id="scanStatusText">
                Klik Scan untuk memindai modem baru di OLT.
            </span>
        </div>
        <button class="btn bg-emerald-600 hover:bg-emerald-700 text-white border-0 hidden" id="btnExecuteMass" onclick="openExecuteModal()">
            <i data-lucide="zap" class="w-4 h-4"></i> 3. Eksekusi Registrasi Massal (<span id="selectedCount">0</span> ONU)
        </button>
    </div>
</div>

<!-- Tabel ONU Unconfigured -->
<div class="card !mb-0 overflow-hidden">
    <div class="table-container">
        <table class="data-table" id="migrationTable">
            <thead>
                <tr>
                    <th style="width:40px">
                        <input type="checkbox" class="accent-blue-600 w-4 h-4" id="checkAll" onchange="toggleCheckAll(this.checked)">
                    </th>
                    <th>Vendor / Merk</th>
                    <th>PON Port</th>
                    <th>Serial Number (SN)</th>
                    <th>Suggested Index</th>
                    <th>Nama Pelanggan (Dapat Edit)</th>
                    <th class="text-right">Status</th>
                </tr>
            </thead>
            <tbody id="migrationTableBody">
                <tr>
                    <td colspan="7" class="text-center py-8 text-slate-400 text-xs">
                        <i data-lucide="radio" class="mx-auto mb-2 block w-8 h-8 opacity-40"></i>
                        Silakan klik tombol <b>Scan Semua ONU Unconfigured</b> di atas untuk mulai memindai.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Progress & Log Eksekusi Massal -->
<div id="execModal" class="modal-overlay modal-static">
    <div class="modal-card" style="max-width:760px">
        <div class="card-header !mb-3">
            <div class="card-title"><i data-lucide="cpu" class="w-4 h-4 text-blue-600"></i> Proses Registrasi Massal ONU (Bridge Mode)</div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-3">
            <div class="flex justify-between text-xs font-bold text-slate-500 mb-1">
                <span id="progressText">Memulai registrasi...</span>
                <span id="progressPercent" class="font-mono">0%</span>
            </div>
            <div class="rounded-full overflow-hidden" style="height:12px;background:#e8eaed">
                <div id="progressBar" class="h-full rounded-full transition-all duration-500" style="width:0%;background:linear-gradient(90deg,#3b82f6,#2563eb)"></div>
            </div>
        </div>

        <!-- CLI Log Terminal -->
        <div class="terminal-window">
            <div class="terminal-header">
                <span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span>
                <span class="text-slate-500 text-[10px] ml-2">mass-registration</span>
            </div>
            <div class="terminal-body" style="max-height:350px" id="execLogContent">
                <div>! Menyiapkan koneksi Telnet ke OLT...</div>
            </div>
        </div>

        <div class="flex justify-end mt-4">
            <button type="button" class="btn btn-secondary" id="btnCloseExecModal" onclick="App.closeModal('execModal')" disabled>Tutup</button>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let currentOltId = <?= $selectedOlt ? $selectedOlt['id'] : 0 ?>;
let scannedOnus  = [];
let vlanProfileMap = {};   // vlan id → nama onu vlan-profile di OLT

document.addEventListener('DOMContentLoaded', () => {
    if (currentOltId) {
        loadVlanProfiles();
    }
});

function changeOlt(oltId) {
    window.location.href = `/olts/${oltId}/migration`;
}

function toggleUseAcs() {
    const on    = document.getElementById('useAcsToggle').checked;
    const input = document.getElementById('vlanAcsInput');
    const hint  = document.getElementById('vlanAcsHint');
    input.disabled = !on;
    hint.innerHTML = on
        ? 'Wajib beda dari VLAN internet — dipakai <code class="font-mono">service acs</code> + <code class="font-mono">tr069-mgmt</code>.'
        : 'ACS dimatikan — ONU diregistrasi tanpa <code class="font-mono">service acs</code> / <code class="font-mono">tr069-mgmt</code>.';
}

function toggleCustomPrefix() {
    const scheme = document.getElementById('nameSchemeSelect').value;
    const box    = document.getElementById('customPrefixBox');
    box.classList.toggle('hidden', scheme !== 'scheme_3');
}

function loadVlanProfiles() {
    const select = document.getElementById('vlanInternetSelect');
    const input  = document.getElementById('vlanInternetInput');
    if (!select) return;

    fetch(`/olts/${currentOltId}/vlan-profiles`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.profiles || data.profiles.length === 0) return;
            select.innerHTML = '';
            data.profiles.forEach(p => {
                const opt = document.createElement('option');
                opt.value       = p.vlan;
                opt.textContent = `${p.name} — VLAN ${p.vlan}`;
                if (p.vlan == 150) opt.selected = true;
                select.appendChild(opt);
                // nama onu vlan-profile dipakai driver ZTE untuk wan-ip/vlan-profile
                vlanProfileMap[String(p.vlan)] = p.name;
            });
            if (select.value && input) {
                input.value = select.value;
            }
        })
        .catch(e => console.error(e));
}

function _scanFail(msg) {
    const statusText = document.getElementById('scanStatusText');
    const tbody = document.getElementById('migrationTableBody');
    statusText.innerHTML = `<span class="text-rose-600">✖ ${App.escapeHtml(msg)}</span>`;
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-rose-600 py-6 text-xs">
        <i data-lucide="octagon-x" class="mx-auto mb-2 block w-8 h-8"></i>
        Gagal Scan: <strong>${App.escapeHtml(msg)}</strong></td></tr>`;
    App.refreshIcons();
}

async function startScan() {
    const btn = document.getElementById('btnScan');
    const statusText = document.getElementById('scanStatusText');
    const scheme = document.getElementById('nameSchemeSelect').value;
    const customPrefix = document.getElementById('customPrefixInput')?.value || '';

    App.setLoading(btn, true, 'Memindai OLT...');
    statusText.textContent = 'Meminta data ONU unconfigured dari OLT via Telnet...';

    const finishScan = (onus) => {
        App.setLoading(btn, false);
        processScannedOnus(onus, scheme, customPrefix);
    };

    const tryFallback = async (firstErr) => {
        // Fallback otomatis memanggil endpoint OLT scan yang terbukti berhasil di detail OLT
        try {
            const r2 = await App.readJson(await App.fetch(`/olts/${currentOltId}/scan`));
            if (r2 && r2.success && Array.isArray(r2.onus)) {
                finishScan(r2.onus);
            } else {
                _scanFail((r2 && r2.message) ? r2.message : (firstErr || 'Respons OLT tidak valid.'));
            }
        } catch (e2) {
            _scanFail(e2.message || 'Koneksi ke server terputus.');
        }
    };

    try {
        const data = await App.readJson(await App.fetch(`/olts/${currentOltId}/migration/scan?name_scheme=${scheme}&custom_prefix=${encodeURIComponent(customPrefix)}`));
        if (data && data.success && Array.isArray(data.onus)) {
            App.setLoading(btn, false);
            scannedOnus = data.onus;
            document.getElementById('scannedCountBadge').textContent = `${scannedOnus.length} ONU Ditemukan`;
            statusText.innerHTML = `<span class="text-emerald-600 font-medium">✅ ${scannedOnus.length} ONU unconfigured siap diregistrasi.</span>`;
            renderTable();
        } else {
            await tryFallback(data && data.message);
        }
    } catch (e) {
        await tryFallback(e.message);
    }
}

function processScannedOnus(onus, scheme, prefix) {
    const statusText = document.getElementById('scanStatusText');
    const cleanPrefix = prefix ? prefix.replace(/[^A-Za-z0-9_]/g, '_') : 'Migrasi';
    let counter = 1;

    scannedOnus = onus.map(o => {
        const sn     = (o.sn || '').toUpperCase();
        const board  = String(o.board || '1');
        const slot   = String(o.slot || '1');
        const port   = String(o.port || '1');
        const index  = parseInt(o.next_index || o.onu_index || 1);

        let vendor = 'Generic';
        if (sn.startsWith('HWTC') || sn.startsWith('HWTS') || sn.startsWith('HWTE')) vendor = 'Huawei';
        else if (sn.startsWith('ZTE')) vendor = 'ZTE';
        else if (sn.startsWith('FHTT') || sn.startsWith('FHSC')) vendor = 'Fiberhome';
        else if (sn.startsWith('ALCL')) vendor = 'Nokia';

        let autoName = `Pelanggan_${board}_${slot}_${port}_${String(index).padStart(2, '0')}`;
        if (scheme === 'scheme_2') {
            autoName = `Pelanggan_${String(counter).padStart(3, '0')}`;
        } else if (scheme === 'scheme_3') {
            autoName = `${cleanPrefix}_${vendor.substring(0, 2).toUpperCase()}_${String(counter).padStart(2, '0')}`;
        }

        counter++;

        return {
            sn: sn,
            vendor: vendor,
            board: board,
            slot: slot,
            port: port,
            onu_index: index,
            auto_name: autoName,
            already_registered: o.already_registered || false,
            existing_id: o.existing_id || null
        };
    });

    document.getElementById('scannedCountBadge').textContent = `${scannedOnus.length} ONU Ditemukan`;
    statusText.innerHTML = `<span class="text-emerald-600 font-medium">✅ ${scannedOnus.length} ONU unconfigured siap diregistrasi.</span>`;
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('migrationTableBody');
    if (scannedOnus.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-8 text-slate-400 text-xs">
                    <i data-lucide="check-all" class="mx-auto mb-2 block w-8 h-8 text-emerald-400"></i>
                    Tidak ada ONU unconfigured yang terdeteksi di OLT saat ini.
                </td>
            </tr>`;
        document.getElementById('btnExecuteMass').classList.add('hidden');
        return;
    }

    let html = '';
    scannedOnus.forEach((o, i) => {
        const vendorBadge = o.vendor === 'Huawei' ? '<span class="chip chip-info">Huawei</span>'
                          : o.vendor === 'ZTE'    ? '<span class="chip chip-success">ZTE</span>'
                          : o.vendor === 'Fiberhome' ? '<span class="chip chip-warning">Fiberhome</span>'
                          : '<span class="chip chip-neutral">Generic China</span>';

        html += `
            <tr data-index="${i}">
                <td>
                    <input type="checkbox" class="accent-blue-600 w-4 h-4 row-check" data-idx="${i}" onchange="updateSelectedCount()" checked>
                </td>
                <td>${vendorBadge}</td>
                <td><span class="font-mono text-xs font-bold">${o.board}/${o.slot}/${o.port}</span></td>
                <td><span class="font-mono text-xs font-bold text-blue-600">${o.sn}</span></td>
                <td><span class="chip chip-neutral font-mono">Index ${o.onu_index}</span></td>
                <td>
                    <input type="text" class="form-input item-name-input !min-h-[30px] text-xs"
                           data-idx="${i}" value="${App.escapeHtml(o.auto_name)}" style="max-width:250px">
                </td>
                <td class="text-right status-cell">
                    <span class="chip chip-warning">Unconfigured</span>
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;
    document.getElementById('checkAll').checked = true;
    updateSelectedCount();
    document.getElementById('btnExecuteMass').classList.remove('hidden');
}

function toggleCheckAll(checked) {
    document.querySelectorAll('.row-check').forEach(chk => chk.checked = checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkedCount = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selectedCount').textContent = checkedCount;
}

async function openExecuteModal() {
    const selectedRows = document.querySelectorAll('.row-check:checked');
    if (selectedRows.length === 0) {
        App.showToast('Pilih minimal satu ONU untuk diregistrasi!', 'warning');
        return;
    }

    const vlanSelect = document.getElementById('vlanInternetSelect');
    const vlanInput  = document.getElementById('vlanInternetInput');
    const vlanVal    = (vlanInput && vlanInput.value) ? vlanInput.value : (vlanSelect ? vlanSelect.value : '150');
    const useAcs     = document.getElementById('useAcsToggle').checked;
    const vlanAcsVal = useAcs ? (document.getElementById('vlanAcsInput').value || '').trim() : '';
    const tcontVal   = document.getElementById('tcontSelect').value;
    const trafficVal = document.getElementById('trafficSelect').value;
    const onuTypeVal = (document.getElementById('onuTypeInput').value || '').trim() || 'ALL-ONT';
    const pppoeProf  = vlanProfileMap[String(vlanVal)] || '';
    const delayMs    = parseInt(document.getElementById('delaySelect').value) || 1000;

    if (!vlanVal) {
        App.showToast('Pilih VLAN Internet Target terlebih dahulu!', 'warning');
        return;
    }

    // TCONT wajib: kalau `tcont 1 ... profile X` gagal (profil tak ada di OLT), gemport 1
    // ikut gagal → service-port & service hsi/acs gagal → ONU cuma ke-register SN-nya,
    // VLAN tidak nembus sama sekali. Ini penyebab utama migrasi "sukses tapi mati".
    if (!tcontVal) {
        App.showToast('Pilih TCONT Profile dulu.<br><br>'
            + 'Kalau TCONT kosong, driver menebak profil dan bisa ditolak OLT — '
            + 'akibatnya gemport &amp; service-port gagal, ONU cuma terdaftar SN tanpa VLAN.', 'warning', 8000);
        return;
    }

    // Tanpa VLAN ACS, driver tidak menulis `service acs` / `ip-host dhcp` / `tr069-mgmt`
    // → ONU tak pernah nyampe ACS dan PPPoE tak pernah ter-provision. Ini bikin migrasi
    // "sukses" tapi pelanggan tetap mati. Tahan di depan, jangan diam-diam.
    if (useAcs && !vlanAcsVal) {
        App.showToast('Mode "Pakai ACS" aktif tapi VLAN ACS kosong.<br><br>'
            + 'Isi VLAN manajemen (default 100), atau matikan toggle "Pakai ACS" '
            + 'kalau OLT ini memang tidak pakai ACS.', 'warning', 8000);
        return;
    } else if (vlanAcsVal && parseInt(vlanAcsVal) === parseInt(vlanVal)) {
        App.showToast('VLAN ACS tidak boleh sama dengan VLAN Internet.<br><br>'
            + 'Kalau sama, service-port &amp; service acs rebutan gemport/VLAN yang sama di OMCI '
            + '(ZTE Code 66669 / ONU config-fail). ACS harus di VLAN manajemen terpisah (mis. 100).', 'warning', 8000);
        return;
    }

    App.openModal('execModal');

    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const progressPercent = document.getElementById('progressPercent');
    const execLog = document.getElementById('execLogContent');
    const btnClose = document.getElementById('btnCloseExecModal');

    progressBar.style.width = '0%';
    progressBar.style.background = 'linear-gradient(90deg,#3b82f6,#2563eb)';
    progressPercent.textContent = '0%';
    progressText.textContent = `Menyiapkan registrasi sekuensial (${selectedRows.length} ONU)...`;
    execLog.innerHTML = `<div>! Memulai registrasi sekuensial (Total ${selectedRows.length} ONU | Jeda: ${delayMs/1000}s per ONU)...</div>`;
    btnClose.disabled = true;

    const items = [];
    selectedRows.forEach(chk => {
        const idx = chk.dataset.idx;
        const o = scannedOnus[idx];
        const nameInput = document.querySelector(`.item-name-input[data-idx="${idx}"]`);
        const nameVal   = nameInput ? nameInput.value.trim() : o.auto_name;

        items.push({
            idx: idx,
            sn: o.sn,
            name: nameVal,
            board: o.board,
            slot: o.slot,
            port: o.port,
            onu_index: o.onu_index,
            onu_type: onuTypeVal
        });
    });

    let successCount = 0;
    let failCount = 0;
    let partialCount = 0;
    const total = items.length;

    execLog.innerHTML += `<div style="color:#94a3b8">! Preset: VLAN internet ${vlanVal} | ACS ${useAcs ? ('ON, VLAN ' + vlanAcsVal) : 'OFF'}`
                       + ` | tipe ${onuTypeVal} | tcont ${tcontVal || '-'} | traffic ${trafficVal || '-'}</div>`;

    // Dikirim per BATCH, bukan per ONU. execute-single membuka sesi Telnet baru tiap ONU;
    // untuk 50 pelanggan itu 50 kali connect/login beruntun dan VTY OLT bisa penuh
    // (telnet menolak koneksi baru sampai sesi lama idle-timeout ~7 menit). Endpoint
    // batch memakai SATU sesi untuk seluruh isi batch.
    const CHUNK = 10;
    let done = 0;

    for (let start = 0; start < total; start += CHUNK) {
        const batch = items.slice(start, start + CHUNK);

        progressText.textContent = `Memproses ${start + 1}–${start + batch.length} dari ${total} ONU (satu sesi Telnet per batch)...`;
        execLog.innerHTML += `<div style="color:#94a3b8">! Batch ${Math.floor(start / CHUNK) + 1}: ${batch.length} ONU (${batch[0].sn} ... ${batch[batch.length-1].sn})</div>`;
        execLog.scrollTop = execLog.scrollHeight;

        const fd = new FormData();
        fd.append('vlan_internet', vlanVal);
        fd.append('vlan_acs', vlanAcsVal);
        fd.append('use_acs', useAcs ? '1' : '0');
        fd.append('tcont_profile', tcontVal);
        fd.append('traffic_profile', trafficVal);
        fd.append('pppoe_vlan_profile', pppoeProf);
        batch.forEach((item, n) => {
            fd.append(`items[${n}][sn]`,        item.sn);
            fd.append(`items[${n}][name]`,      item.name);
            fd.append(`items[${n}][board]`,     item.board);
            fd.append(`items[${n}][slot]`,      item.slot);
            fd.append(`items[${n}][port]`,      item.port);
            fd.append(`items[${n}][onu_index]`, item.onu_index);
            fd.append(`items[${n}][onu_type]`,  item.onu_type);
        });

        try {
            const res  = await App.fetch(`/olts/${currentOltId}/migration/execute`, { method: 'POST', body: fd });
            const data = await res.json().catch(() => null);

            if (data && data.success) {
                (data.logs || []).forEach(l => {
                    const warn = l.includes('TIDAK LENGKAP') || l.includes('BATAL');
                    const bad  = l.includes('GAGAL');
                    const color = bad ? '#f87171' : (warn ? '#fbbf24' : (l.includes('SUKSES') ? '#81c995' : '#94a3b8'));
                    execLog.innerHTML += `<div style="color:${color}">${App.escapeHtml(l)}</div>`;
                });
                successCount += (data.success_count || 0);
                failCount    += (data.fail_count || 0);
                partialCount += (data.partial_count || 0);

                // Tandai baris tabel sesuai hasil per SN.
                (data.results || []).forEach(r => {
                    const it = batch.find(b => b.sn === r.sn);
                    if (!it) return;
                    const tr = document.querySelector(`tr[data-index="${it.idx}"]`);
                    if (!tr) return;
                    tr.querySelector('.status-cell').innerHTML = r.status === 'success'
                        ? '<span class="chip chip-success">Registered</span>'
                        : (r.status === 'partial'
                            ? '<span class="chip chip-warning">Partial</span>'
                            : '<span class="chip chip-danger">Gagal</span>');
                });
            } else {
                failCount += batch.length;
                const errMsg = (data && data.message) ? data.message : 'Respons server tidak valid.';
                execLog.innerHTML += `<div style="color:#f87171">✘ Batch gagal: ${App.escapeHtml(errMsg)}</div>`;
            }
        } catch (e) {
            failCount += batch.length;
            execLog.innerHTML += `<div style="color:#f87171">❌ ERROR batch: ${App.escapeHtml(e.message)}</div>`;
        }

        done += batch.length;
        const pct = Math.round((done / total) * 100);
        progressBar.style.width = `${pct}%`;
        progressPercent.textContent = `${pct}%`;
        execLog.scrollTop = execLog.scrollHeight;

        // Jeda antar batch: beri waktu VTY OLT melepas sesi sebelumnya.
        if (done < total && delayMs > 0) {
            await new Promise(r => setTimeout(r, delayMs));
        }
    }

    progressBar.style.width = '100%';
    progressPercent.textContent = '100%';
    progressText.textContent = `Selesai! (${successCount - partialCount} Sukses, ${partialCount} Tidak Lengkap, ${failCount} Gagal)`;
    progressBar.style.background = (failCount > 0 || partialCount > 0) ? '#f59e0b' : '#188038';
    execLog.innerHTML += `<div class="font-bold mt-2" style="color:${(failCount || partialCount) ? '#fbbf24' : '#81c995'}">`
                       + `✔ Selesai memproses ${total} ONU (${successCount - partialCount} Sukses, ${partialCount} Tidak Lengkap, ${failCount} Gagal).</div>`;
    btnClose.disabled = false;
}
</script>
<?= $this->endSection() ?>
