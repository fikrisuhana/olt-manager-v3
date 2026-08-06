<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $isZte = strtoupper($selectedOlt['brand'] ?? '') === 'ZTE'; ?>
<div class="container-fluid px-0">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-hdd-network text-primary"></i> Setting OLT
            </h4>
            <p class="text-secondary small mb-0">Perawatan perangkat: status &amp; enable/disable port PON, VLAN database, info sistem.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <select id="oltSelect" class="form-select form-select-sm shadow-none" style="width:auto"
                    onchange="location.href='/olts/'+this.value+'/settings'">
                <?php foreach ($olts as $o): ?>
                    <option value="<?= $o['id'] ?>" <?= $selectedOlt['id'] == $o['id'] ? 'selected' : '' ?>>
                        <?= esc($o['name']) ?> (<?= esc($o['brand']) ?> - <?= esc($o['ip']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-google-primary btn-sm px-3" id="btnLoad" onclick="loadStatus()">
                <i class="bi bi-arrow-clockwise me-1"></i> Muat Status
            </button>
        </div>
    </div>

    <?php if (!$isZte): ?>
        <div class="alert alert-warning border-0 rounded-4">
            <i class="bi bi-exclamation-triangle me-1"></i>
            OLT <b><?= esc($selectedOlt['brand']) ?></b> belum didukung menu ini. Perintah setup Fiberhome
            berbeda dan belum diverifikasi di perangkat — daripada menebak perintah di OLT produksi,
            fiturnya dinonaktifkan dulu.
        </div>
    <?php else: ?>

    <div id="loadState" class="text-secondary small mb-3">
        <i class="bi bi-info-circle me-1"></i> Klik <b>Muat Status</b> — semua data diambil dalam satu sesi Telnet.
    </div>

    <!-- Info sistem -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 d-none" id="cardSystem">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-cpu text-primary me-1"></i> Info Sistem</h6>
            <div class="row g-3" id="systemBox"></div>
            <div class="mt-3">
                <div class="small fw-bold text-secondary mb-2">Interface Manajemen</div>
                <div class="table-responsive"><table class="table table-sm mb-0" id="mgmtTable"></table></div>
                <div class="form-text">
                    Perubahan IP manajemen belum dibuka dari aplikasi — jalur akses aplikasi ke OLT lewat
                    interface ini, salah isi berarti OLT tidak bisa dihubungi lagi sampai didatangi langsung.
                </div>
            </div>
            <div class="mt-3">
                <div class="small fw-bold text-secondary mb-2">Kartu / Slot</div>
                <div class="table-responsive"><table class="table table-sm mb-0" id="cardTable"></table></div>
            </div>
        </div>
    </div>

    <!-- Port PON -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 d-none" id="cardPon">
        <div class="card-body p-4 pb-2">
            <h6 class="fw-bold text-dark mb-1"><i class="bi bi-ethernet text-primary me-1"></i> Port PON</h6>
            <p class="text-secondary small mb-3">Mematikan port memutus seluruh pelanggan di port tersebut.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom">
                    <tr class="small text-secondary fw-bold">
                        <th class="ps-4">Port</th>
                        <th>Nama / Deskripsi</th>
                        <th class="text-center">ONU Terdaftar</th>
                        <th class="text-center">ONU Working</th>
                        <th class="text-center">Status</th>
                        <th class="pe-4 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="ponBody"></tbody>
            </table>
        </div>
    </div>

    <!-- VLAN database -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 d-none" id="cardVlan">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-diagram-3 text-primary me-1"></i> VLAN Database</h6>
                <div class="d-flex gap-2">
                    <input type="number" id="newVlanId" class="form-control form-control-sm" placeholder="VLAN ID" min="1" max="4094" style="width:120px">
                    <button class="btn btn-sm btn-google-primary" onclick="addVlan()">
                        <i class="bi bi-plus-lg me-1"></i>Tambah
                    </button>
                </div>
            </div>
            <div id="vlanBox" class="d-flex flex-wrap gap-2"></div>
        </div>
    </div>

    <!-- Log aksi -->
    <div class="card border-0 shadow-sm rounded-4 d-none" id="cardLog">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-terminal text-primary me-1"></i> Log Perintah</h6>
            <div class="rounded-3 p-3 font-monospace small" style="background:#0f172a;color:#38bdf8;max-height:260px;overflow-y:auto" id="logBox"></div>
        </div>
    </div>

    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const OLT_ID   = <?= (int)$selectedOlt['id'] ?>;
const CSRF_NAME = '<?= csrf_token() ?>';
const CSRF_HASH = '<?= csrf_hash() ?>';
let vlansInUse = [];

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function pushLog(lines, ok = true) {
    const box = document.getElementById('logBox');
    document.getElementById('cardLog').classList.remove('d-none');
    (Array.isArray(lines) ? lines : [lines]).forEach(l => {
        box.innerHTML += `<div style="color:${ok ? '#81c995' : '#f87171'}">${esc(l)}</div>`;
    });
    box.scrollTop = box.scrollHeight;
}

function loadStatus() {
    const btn = document.getElementById('btnLoad');
    const st  = document.getElementById('loadState');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Membaca OLT...';
    st.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Membaca info sistem, port PON, dan VLAN database (satu sesi Telnet, bisa ½–1 menit untuk 16 port)...';

    fetch(`/olts/${OLT_ID}/settings/status`)
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Muat Status';
            if (!d.success) {
                st.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${esc(d.message)}</span>`;
                return;
            }
            st.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i> Data terbaca dari OLT.</span>';
            renderSystem(d.system);
            renderPorts(d.ports || []);
            renderVlans(d.vlans || []);
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Muat Status';
            st.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>${esc(e.message)}</span>`;
        });
}

function renderSystem(sys) {
    if (!sys) return;
    document.getElementById('cardSystem').classList.remove('d-none');
    const item = (label, val) => `
        <div class="col-md-3">
            <div class="small text-secondary">${esc(label)}</div>
            <div class="fw-bold">${esc(val || '—')}</div>
        </div>`;
    document.getElementById('systemBox').innerHTML =
        item('Nama Sistem', sys.name) + item('Model', sys.model) +
        item('Versi', sys.version) + item('Uptime', sys.uptime);

    let mgmt = `<thead><tr class="small text-secondary"><th>Interface</th><th>IP</th><th>Netmask</th><th>Admin</th><th>Protokol</th></tr></thead><tbody>`;
    (sys.mgmt || []).forEach(m => {
        mgmt += `<tr><td class="font-monospace">${esc(m.interface)}</td><td class="font-monospace">${esc(m.ip)}</td>`
             +  `<td class="font-monospace">${esc(m.mask)}</td><td>${esc(m.admin)}</td><td>${esc(m.proto)}</td></tr>`;
    });
    document.getElementById('mgmtTable').innerHTML = mgmt + '</tbody>';

    let cards = `<thead><tr class="small text-secondary"><th>Rack/Shelf/Slot</th><th>Tipe</th><th>Terpasang</th><th>Port</th><th>Status</th></tr></thead><tbody>`;
    (sys.cards || []).forEach(c => {
        const badge = c.status === 'INSERVICE' ? 'chip-success' : 'chip-warning';
        cards += `<tr><td class="font-monospace">${esc(c.rack)}/${esc(c.shelf)}/${esc(c.slot)}</td><td>${esc(c.type)}</td>`
              +  `<td>${esc(c.real_type || '—')}</td><td>${esc(c.ports || '—')}</td>`
              +  `<td><span class="chip ${badge}">${esc(c.status || '—')}</span></td></tr>`;
    });
    document.getElementById('cardTable').innerHTML = cards + '</tbody>';
}

function renderPorts(ports) {
    document.getElementById('cardPon').classList.remove('d-none');
    const body = document.getElementById('ponBody');
    if (!ports.length) {
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-secondary">Tidak ada port PON terbaca.</td></tr>';
        return;
    }
    let html = '';
    ports.forEach(p => {
        const id = `${p.board}/${p.slot}/${p.port}`;
        html += `
        <tr>
            <td class="ps-4 font-monospace fw-bold">gpon-olt_${esc(id)}</td>
            <td>
                <div class="fw-medium">${esc(p.name || '—')}</div>
                <div class="small text-secondary">${esc(p.description || '')}</div>
            </td>
            <td class="text-center">${p.onu_configured}</td>
            <td class="text-center">${p.onu_working}</td>
            <td class="text-center">
                <span class="chip ${p.enabled ? 'chip-success' : 'chip-neutral'}">${p.enabled ? 'Aktif' : 'Shutdown'}</span>
            </td>
            <td class="pe-4 text-end">
                <button class="btn btn-sm ${p.enabled ? 'btn-outline-danger' : 'btn-outline-success'} py-1 px-3"
                        onclick="setPon('${p.board}','${p.slot}','${p.port}',${p.enabled ? 0 : 1},${p.onu_configured})">
                    <i class="bi bi-power me-1"></i>${p.enabled ? 'Disable' : 'Enable'}
                </button>
            </td>
        </tr>`;
    });
    body.innerHTML = html;
}

function renderVlans(vlans) {
    document.getElementById('cardVlan').classList.remove('d-none');
    const box = document.getElementById('vlanBox');
    if (!vlans.length) {
        box.innerHTML = '<span class="text-secondary small">VLAN database kosong / tidak terbaca.</span>';
        return;
    }
    box.innerHTML = vlans.map(v => `
        <span class="chip chip-neutral d-inline-flex align-items-center gap-2 font-monospace">
            ${v}
            <button class="btn btn-sm btn-link text-danger p-0 lh-1" title="Hapus VLAN ${v}"
                    onclick="delVlan(${v})"><i class="bi bi-x-circle"></i></button>
        </span>`).join('');
}

function setPon(board, slot, port, enable, onuCount) {
    const id = `gpon-olt_${board}/${slot}/${port}`;
    if (!enable) {
        const warn = onuCount > 0
            ? `\n\n${onuCount} ONU terdaftar di port ini akan PUTUS seketika.`
            : '';
        if (!confirm(`Matikan ${id}?${warn}`)) return;
    }
    const fd = new FormData();
    fd.append(CSRF_NAME, CSRF_HASH);
    fd.append('board', board); fd.append('slot', slot); fd.append('port', port);
    fd.append('enable', enable ? '1' : '0');

    fetch(`/olts/${OLT_ID}/settings/pon-state`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            pushLog(d.log && d.log.length ? d.log : [d.message], !!d.success);
            if (d.success) loadStatus();
        })
        .catch(e => pushLog('Error: ' + e.message, false));
}

function addVlan() {
    const el = document.getElementById('newVlanId');
    const v  = parseInt(el.value);
    if (!v || v < 1 || v > 4094) { alert('VLAN ID harus 1–4094.'); return; }

    const fd = new FormData();
    fd.append(CSRF_NAME, CSRF_HASH);
    fd.append('vlan_id', v);
    fetch(`/olts/${OLT_ID}/settings/vlan-add`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            pushLog(d.log && d.log.length ? d.log : [d.message], !!d.success);
            if (d.success) { el.value = ''; loadStatus(); }
        })
        .catch(e => pushLog('Error: ' + e.message, false));
}

function delVlan(v) {
    if (!confirm(`Hapus VLAN ${v} dari VLAN database OLT?\n\nKalau VLAN ini masih dipakai uplink/service-port, trafiknya berhenti.`)) return;
    const fd = new FormData();
    fd.append(CSRF_NAME, CSRF_HASH);
    fd.append('vlan_id', v);
    fetch(`/olts/${OLT_ID}/settings/vlan-delete`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            pushLog(d.log && d.log.length ? d.log : [d.message], !!d.success);
            if (d.success) loadStatus();
        })
        .catch(e => pushLog('Error: ' + e.message, false));
}
</script>
<?= $this->endSection() ?>
