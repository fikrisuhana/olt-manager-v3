<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid px-0">
    <!-- Header Page -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-arrow-repeat text-primary"></i> Migrasi & Registrasi Massal
            </h4>
            <p class="text-secondary small mb-0">Registrasi masal seluruh ONU baru/pindahan dengan mode <b>Pure Transparent Bridge (Nembusin VLAN)</b>.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="chip chip-info" id="scannedCountBadge">
                <i class="bi bi-cpu me-1"></i> Ready Scan
            </span>
        </div>
    </div>

    <!-- Panel Konfigurasi OLT & Preset Migrasi -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-sliders text-primary"></i> 1. Pengaturan Preset Migrasi (Global)
            </h6>
            
            <div class="row g-3">
                <!-- Select OLT -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Pilih OLT Target</label>
                    <select id="oltSelect" class="form-select form-select-sm shadow-none" onchange="changeOlt(this.value)">
                        <?php foreach ($olts as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $selectedOlt && $selectedOlt['id'] == $o['id'] ? 'selected' : '' ?>>
                                <?= esc($o['name']) ?> (<?= esc($o['brand']) ?> - <?= esc($o['ip']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- VLAN Internet Target -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">VLAN Internet (PPPoE/Service)</label>
                    <div class="input-group input-group-sm">
                        <select id="vlanInternetSelect" class="form-select form-select-sm shadow-none" onchange="document.getElementById('vlanInternetInput').value = this.value">
                            <option value="150">VLAN 150 (Default)</option>
                        </select>
                        <input type="number" id="vlanInternetInput" class="form-control form-control-sm" placeholder="150" value="150" min="1" max="4094" style="max-width: 90px;" title="Atau ketik VLAN ID manual">
                    </div>
                </div>

                <!-- TCONT Profile -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">TCONT Profile</label>
                    <select id="tcontSelect" class="form-select form-select-sm shadow-none">
                        <option value="">-- Pilih TCONT --</option>
                        <?php
                        $tconts = array_values(array_filter(array_map('trim', explode("\n", $selectedOlt['tcont_profiles'] ?? ''))));
                        foreach ($tconts as $tc):
                        ?>
                            <option value="<?= esc($tc) ?>" <?= strtolower($tc) === 'default' || strtolower($tc) === '1g' ? 'selected' : '' ?>><?= esc($tc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Skema Auto-Name -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Skema Nama Pelanggan</label>
                    <select id="nameSchemeSelect" class="form-select form-select-sm shadow-none" onchange="toggleCustomPrefix()">
                        <option value="scheme_1">Pelanggan_{BOARD}_{SLOT}_{PORT}_{INDEX} (Pelanggan_1_1_1_01)</option>
                        <option value="scheme_2">Pelanggan_001 sekuensial (Pelanggan_001, Pelanggan_002)</option>
                        <option value="scheme_3">Custom Prefix (Misal: User_HW_01)</option>
                    </select>
                </div>

                <!-- Jeda Antar ONU (Safety Delay) -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Jeda Antar ONU (Prevent OLT Load)</label>
                    <select id="delaySelect" class="form-select form-select-sm shadow-none">
                        <option value="1000" selected>1 Detik (Standar)</option>
                        <option value="2000">2 Detik (Rekomendasi Aman)</option>
                        <option value="3000">3 Detik (Sangat Aman)</option>
                        <option value="0">0 Detik (Tanpa Jeda)</option>
                    </select>
                </div>

                <!-- Custom Prefix (Hidden default) -->
                <div class="col-md-3 d-none" id="customPrefixBox">
                    <label class="form-label small fw-bold">Custom Prefix Text</label>
                    <input type="text" id="customPrefixInput" class="form-control form-control-sm" placeholder="User" value="User">
                </div>
            </div>
        </div>
    </div>

    <!-- Action & Scan Bar -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-google-primary px-4 py-2" id="btnScan" onclick="startScan()">
                    <i class="bi bi-search me-1"></i> 2. Scan Semua ONU Unconfigured
                </button>
                <span class="text-secondary small ms-2" id="scanStatusText">
                    <i class="bi bi-info-circle me-1"></i> Klik Scan untuk memindai modem baru di OLT.
                </span>
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto">
                <button class="btn btn-success px-4 py-2 rounded-3 text-white fw-bold shadow-sm d-none" id="btnExecuteMass" onclick="openExecuteModal()">
                    <i class="bi bi-lightning-charge-fill me-1"></i> 3. Eksekusi Registrasi Massal (<span id="selectedCount">0</span> ONU)
                </button>
            </div>
        </div>
    </div>

    <!-- Tabel ONU Unconfigured -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="migrationTable">
                <thead class="bg-light border-bottom">
                    <tr class="small text-secondary fw-bold">
                        <th class="ps-4" style="width: 40px;">
                            <input type="checkbox" class="form-check-input" id="checkAll" onchange="toggleCheckAll(this.checked)">
                        </th>
                        <th>Vendor / Merk</th>
                        <th>PON Port</th>
                        <th>Serial Number (SN)</th>
                        <th>Suggested Index</th>
                        <th>Nama Pelanggan (Dapat Edit)</th>
                        <th class="pe-4 text-end">Status</th>
                    </tr>
                </thead>
                <tbody id="migrationTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-5 text-secondary">
                            <i class="bi bi-broadcast fs-1 text-muted d-block mb-2"></i>
                            Silakan klik tombol <b>Scan Semua ONU Unconfigured</b> di atas untuk mulai memindai.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Progress & Log Eksekusi Massal -->
<div class="modal fade" id="execModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-cpu-fill text-primary"></i> Proses Registrasi Massal ONU (Bridge Mode)
                </h5>
            </div>
            <div class="modal-body p-4">
                <!-- Progress Bar -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between small text-secondary fw-bold mb-1">
                        <span id="progressText">Memulai registrasi...</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="progress rounded-pill" style="height: 12px; background: #e2e8f0;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary rounded-pill" 
                             id="progressBar" style="width: 0%;"></div>
                    </div>
                </div>

                <!-- CLI Log Terminal -->
                <div class="rounded-3 p-3 font-monospace small" style="background:#0f172a; color:#38bdf8; max-height: 350px; overflow-y: auto;" id="execLogContent">
                    <div>! Menyiapkan koneksi Telnet ke OLT...</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-google-secondary" id="btnCloseExecModal" data-bs-dismiss="modal" disabled>Tutup</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
let currentOltId = <?= $selectedOlt ? $selectedOlt['id'] : 0 ?>;
let scannedOnus  = [];

document.addEventListener('DOMContentLoaded', () => {
    if (currentOltId) {
        loadVlanProfiles();
    }
});

function changeOlt(oltId) {
    window.location.href = `/olts/${oltId}/migration`;
}

function toggleCustomPrefix() {
    const scheme = document.getElementById('nameSchemeSelect').value;
    const box    = document.getElementById('customPrefixBox');
    if (scheme === 'scheme_3') {
        box.classList.remove('d-none');
    } else {
        box.classList.add('d-none');
    }
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
            });
            if (select.value && input) {
                input.value = select.value;
            }
        })
        .catch(e => console.error(e));
}

function startScan() {
    const btn = document.getElementById('btnScan');
    const statusText = document.getElementById('scanStatusText');
    const tbody = document.getElementById('migrationTableBody');
    const scheme = document.getElementById('nameSchemeSelect').value;
    const customPrefix = document.getElementById('customPrefixInput')?.value || '';

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memindai OLT...';
    statusText.textContent = 'Meminta data ONU unconfigured dari OLT via Telnet...';

    fetch(`/olts/${currentOltId}/migration/scan?name_scheme=${scheme}&custom_prefix=${encodeURIComponent(customPrefix)}`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> 2. Scan Semua ONU Unconfigured';

            if (!data.success) {
                const errMsg = data.message || 'Gagal koneksi Telnet ke OLT atau respons tidak valid.';
                statusText.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Error: ${errMsg}</span>`;
                tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4"><i class="bi bi-exclamation-octagon fs-1 d-block mb-2"></i>Gagal scan: ${errMsg}</td></tr>`;
                return;
            }

            scannedOnus = data.onus || [];
            document.getElementById('scannedCountBadge').innerHTML = `<i class="bi bi-cpu me-1"></i> ${scannedOnus.length} ONU Ditemukan`;
            statusText.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i> ${scannedOnus.length} ONU unconfigured siap diregistrasi.</span>`;

            renderTable();
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> 2. Scan Semua ONU Unconfigured';
            const errMsg = e.message || 'Koneksi ke server terputus.';
            statusText.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Error: ${errMsg}</span>`;
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4"><i class="bi bi-exclamation-octagon fs-1 d-block mb-2"></i>Gagal scan: ${errMsg}</td></tr>`;
        });
}

function renderTable() {
    const tbody = document.getElementById('migrationTableBody');
    if (scannedOnus.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-5 text-secondary">
                    <i class="bi bi-check-all fs-1 text-success d-block mb-2"></i>
                    Tidak ada ONU unconfigured yang terdeteksi di OLT saat ini.
                </td>
            </tr>`;
        document.getElementById('btnExecuteMass').classList.add('d-none');
        return;
    }

    let html = '';
    scannedOnus.forEach((o, i) => {
        const vendorBadge = o.vendor === 'Huawei' ? '<span class="chip chip-info"><i class="bi bi-router me-1"></i>Huawei</span>'
                          : o.vendor === 'ZTE'    ? '<span class="chip chip-success"><i class="bi bi-broadcast me-1"></i>ZTE</span>'
                          : o.vendor === 'Fiberhome' ? '<span class="chip chip-warning"><i class="bi bi-cpu me-1"></i>Fiberhome</span>'
                          : '<span class="chip chip-neutral">Generic China</span>';

        html += `
            <tr data-index="${i}">
                <td class="ps-4">
                    <input type="checkbox" class="form-check-input row-check" data-idx="${i}" onchange="updateSelectedCount()" checked>
                </td>
                <td>${vendorBadge}</td>
                <td><span class="font-monospace fw-bold">${o.board}/${o.slot}/${o.port}</span></td>
                <td><span class="font-monospace text-primary fw-bold">${o.sn}</span></td>
                <td><span class="chip chip-neutral font-monospace">Index ${o.onu_index}</span></td>
                <td>
                    <input type="text" class="form-control form-control-sm item-name-input shadow-none" 
                           data-idx="${i}" value="${o.auto_name}" style="max-width: 250px;">
                </td>
                <td class="pe-4 text-end">
                    <span class="chip chip-warning">Unconfigured</span>
                </td>
            </tr>`;
    });

    tbody.innerHTML = html;
    document.getElementById('checkAll').checked = true;
    updateSelectedCount();
    document.getElementById('btnExecuteMass').classList.remove('d-none');
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
        alert('Pilih minimal satu ONU untuk diregistrasi!');
        return;
    }

    const vlanSelect = document.getElementById('vlanInternetSelect');
    const vlanInput  = document.getElementById('vlanInternetInput');
    const vlanVal    = (vlanInput && vlanInput.value) ? vlanInput.value : (vlanSelect ? vlanSelect.value : '150');
    const tcontVal   = document.getElementById('tcontSelect').value;
    const delayMs    = parseInt(document.getElementById('delaySelect').value) || 1000;

    if (!vlanVal) {
        alert('Pilih VLAN Internet Target terlebih dahulu!');
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('execModal'));
    modal.show();

    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const progressPercent = document.getElementById('progressPercent');
    const execLog = document.getElementById('execLogContent');
    const btnClose = document.getElementById('btnCloseExecModal');

    progressBar.style.width = '0%';
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
            onu_type: 'ALL-ONT'
        });
    });

    let successCount = 0;
    let failCount = 0;
    const total = items.length;

    for (let i = 0; i < total; i++) {
        const item = items[i];
        const currentPct = Math.round(((i + 1) / total) * 100);
        
        progressText.textContent = `[${i + 1}/${total}] Memproses ${item.sn} (${item.name})...`;
        progressBar.style.width = `${currentPct}%`;
        progressPercent.textContent = `${currentPct}%`;

        const fd = new FormData();
        fd.append('sn', item.sn);
        fd.append('name', item.name);
        fd.append('board', item.board);
        fd.append('slot', item.slot);
        fd.append('port', item.port);
        fd.append('onu_index', item.onu_index);
        fd.append('onu_type', item.onu_type);
        fd.append('vlan_internet', vlanVal);
        fd.append('tcont_profile', tcontVal);

        try {
            const res = await fetch(`/olts/${currentOltId}/migration/execute-single`, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                successCount++;
                execLog.innerHTML += `<div style="color:#81c995">▶ [${i+1}/${total}] ✔ SUKSES: ${item.sn} (${item.name}) terdaftar di ${item.board}/${item.slot}/${item.port}:${item.onu_index}</div>`;
                const rowTr = document.querySelector(`tr[data-index="${item.idx}"]`);
                if (rowTr) {
                    rowTr.querySelector('.pe-4').innerHTML = '<span class="chip chip-success">Registered</span>';
                }
            } else {
                failCount++;
                execLog.innerHTML += `<div style="color:#f87171">▶ [${i+1}/${total}] ✘ GAGAL: ${item.sn} → ${data.message}</div>`;
            }
        } catch (e) {
            failCount++;
            execLog.innerHTML += `<div style="color:#f87171">▶ [${i+1}/${total}] ❌ ERROR: ${item.sn} → ${e.message}</div>`;
        }

        execLog.scrollTop = execLog.scrollHeight;

        if (i < total - 1 && delayMs > 0) {
            await new Promise(r => setTimeout(r, delayMs));
        }
    }

    progressBar.style.width = '100%';
    progressPercent.textContent = '100%';
    progressText.textContent = `Selesai! (${successCount} Sukses, ${failCount} Gagal)`;
    progressBar.classList.remove('bg-primary');
    progressBar.classList.add(failCount > 0 ? 'bg-warning' : 'bg-success');
    execLog.innerHTML += `<div class="text-success fw-bold mt-2">✔ Selesai memproses ${total} ONU (${successCount} Sukses, ${failCount} Gagal).</div>`;
    btnClose.disabled = false;
}
</script>
<?= $this->endSection() ?>
