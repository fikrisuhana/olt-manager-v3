<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $isZte = strtoupper($selectedOlt['brand'] ?? '') === 'ZTE'; ?>

<div class="flex items-center justify-between flex-wrap gap-3">
    <div class="flex items-center space-x-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
            <i data-lucide="wrench" class="w-5 h-5"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">Setting OLT</h1>
            <p class="text-xs text-slate-500">Perawatan perangkat: status &amp; enable/disable port PON, VLAN database, info sistem.</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <select id="oltSelect" class="form-select !min-h-[34px] text-xs font-semibold" style="width:auto"
                onchange="location.href='/olts/'+this.value+'/settings'">
            <?php foreach ($olts as $o): ?>
                <option value="<?= $o['id'] ?>" <?= $selectedOlt['id'] == $o['id'] ? 'selected' : '' ?>>
                    <?= esc($o['name']) ?> (<?= esc($o['brand']) ?> - <?= esc($o['ip']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" id="btnLoad" onclick="loadStatus()">
            <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i> Muat Status
        </button>
    </div>
</div>

<?php if (!$isZte): ?>
    <div class="p-3.5 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs flex items-start gap-2 shadow-xs">
        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
        <div>
            OLT <b><?= esc($selectedOlt['brand']) ?></b> belum didukung menu ini. Perintah setup Fiberhome
            berbeda dan belum diverifikasi di perangkat — daripada menebak perintah di OLT produksi,
            fiturnya dinonaktifkan dulu.
        </div>
    </div>
<?php else: ?>

<div id="loadState" class="text-xs text-slate-400">
    <i data-lucide="info" class="w-3.5 h-3.5 inline"></i> Klik <b>Muat Status</b> — semua data diambil dalam satu sesi Telnet.
</div>

<!-- Info sistem -->
<div class="card hidden" id="cardSystem">
    <div class="card-header">
        <div class="card-title"><i data-lucide="cpu" class="w-4 h-4 text-slate-400"></i> Info Sistem</div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3" id="systemBox"></div>
    <div class="mt-3">
        <div class="text-xs font-bold text-slate-500 mb-2">Interface Manajemen</div>
        <div class="table-container"><table class="data-table" id="mgmtTable"></table></div>
        <div class="text-[11px] text-slate-400 mt-1.5">
            Perubahan IP manajemen belum dibuka dari aplikasi — jalur akses aplikasi ke OLT lewat
            interface ini, salah isi berarti OLT tidak bisa dihubungi lagi sampai didatangi langsung.
        </div>
    </div>
    <div class="mt-3">
        <div class="text-xs font-bold text-slate-500 mb-2">Kartu / Slot</div>
        <div class="table-container"><table class="data-table" id="cardTable"></table></div>
    </div>
</div>

<!-- Port PON -->
<div class="card hidden" id="cardPon">
    <div class="card-header !mb-1">
        <div class="card-title"><i data-lucide="ethernet" class="w-4 h-4 text-slate-400"></i> Port PON</div>
    </div>
    <p class="text-[11px] text-slate-400 mb-2">Mematikan port memutus seluruh pelanggan di port tersebut.</p>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Port</th>
                    <th>Nama / Deskripsi</th>
                    <th class="text-center">ONU Terdaftar</th>
                    <th class="text-center">ONU Working</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody id="ponBody"></tbody>
        </table>
    </div>
</div>

<!-- VLAN database -->
<div class="card hidden" id="cardVlan">
    <div class="card-header">
        <div class="card-title"><i data-lucide="git-branch" class="w-4 h-4 text-slate-400"></i> VLAN Database</div>
        <div class="flex gap-2">
            <input type="number" id="newVlanId" class="form-input !min-h-[32px]" placeholder="VLAN ID" min="1" max="4094" style="width:110px">
            <button class="btn btn-primary btn-sm" onclick="addVlan()">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Tambah
            </button>
        </div>
    </div>
    <div id="vlanBox" class="flex flex-wrap gap-2"></div>
</div>

<!-- Modal edit nama/deskripsi port -->
<div id="ponEditModal" class="modal-overlay">
    <div class="modal-card" style="max-width:440px">
        <div class="card-header !mb-3">
            <div class="card-title">
                <i data-lucide="pencil" class="w-4 h-4 text-blue-600"></i>
                Edit Port <span class="font-mono text-xs" id="pe_title"></span>
            </div>
            <button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="App.closeModal('ponEditModal')">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div>
            <input type="hidden" id="pe_board"><input type="hidden" id="pe_slot"><input type="hidden" id="pe_port">
            <div class="form-group">
                <label class="form-label">Nama (name)</label>
                <input type="text" id="pe_name" class="form-input" maxlength="64" placeholder="misal: ODC-TONI CBR">
                <div class="text-[11px] text-slate-400 mt-1">Perintah OLT: <code class="font-mono">name &lt;teks&gt;</code>. Boleh pakai spasi. Kosongkan untuk menghapus.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Deskripsi (description)</label>
                <input type="text" id="pe_desc" class="form-input" maxlength="64" placeholder="misal: CIBUNGUR">
                <div class="text-[11px] text-slate-400 mt-1">Perintah OLT: <code class="font-mono">description &lt;teks&gt;</code>. Kosongkan untuk menghapus.</div>
            </div>
        </div>
        <div class="flex gap-2 justify-end mt-2">
            <button class="btn btn-secondary btn-sm" onclick="App.closeModal('ponEditModal')">Batal</button>
            <button class="btn btn-primary btn-sm" onclick="savePonInfo()">
                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Simpan ke OLT
            </button>
        </div>
    </div>
</div>

<!-- Log aksi -->
<div class="card hidden" id="cardLog">
    <div class="card-header">
        <div class="card-title"><i data-lucide="terminal" class="w-4 h-4 text-slate-400"></i> Log Perintah</div>
    </div>
    <div class="terminal-window !mb-0">
        <div class="terminal-header">
            <span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span>
            <span class="text-slate-500 text-[10px] ml-2">cli-log</span>
        </div>
        <div class="terminal-body" style="max-height:260px" id="logBox"></div>
    </div>
</div>

<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const OLT_ID = <?= (int)$selectedOlt['id'] ?>;
let vlansInUse = [];

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function pushLog(lines, ok = true) {
    const box = document.getElementById('logBox');
    document.getElementById('cardLog').classList.remove('hidden');
    (Array.isArray(lines) ? lines : [lines]).forEach(l => {
        box.innerHTML += `<div style="color:${ok ? '#81c995' : '#f87171'}">${esc(l)}</div>`;
    });
    box.scrollTop = box.scrollHeight;
}

async function loadStatus() {
    const btn = document.getElementById('btnLoad');
    const st  = document.getElementById('loadState');
    App.setLoading(btn, true, 'Membaca OLT...');
    st.innerHTML = '<span class="spin spin-dark align-middle"></span> Membaca info sistem, port PON, dan VLAN database (satu sesi Telnet, bisa ½–1 menit untuk 16 port)...';

    try {
        const d = await App.readJson(await App.fetch(`/olts/${OLT_ID}/settings/status`));
        App.setLoading(btn, false);
        if (!d.success) {
            st.innerHTML = `<span class="text-rose-600">✖ ${esc(d.message)}</span>`;
            return;
        }
        st.innerHTML = '<span class="text-emerald-600 font-medium">✅ Data terbaca dari OLT.</span>';
        renderSystem(d.system);
        renderPorts(d.ports || []);
        renderVlans(d.vlans || []);
    } catch (e) {
        App.setLoading(btn, false);
        st.innerHTML = `<span class="text-rose-600">✖ ${esc(e.message)}</span>`;
    }
}

function renderSystem(sys) {
    if (!sys) return;
    document.getElementById('cardSystem').classList.remove('hidden');
    const item = (label, val) => `
        <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-lg">
            <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">${esc(label)}</div>
            <div class="text-sm font-bold text-slate-800 mt-0.5 truncate" title="${esc(val || '')}">${esc(val || '—')}</div>
        </div>`;
    document.getElementById('systemBox').innerHTML =
        item('Nama Sistem', sys.name) + item('Model', sys.model) +
        item('Versi', sys.version) + item('Uptime', sys.uptime);

    let mgmt = `<thead><tr><th>Interface</th><th>IP</th><th>Netmask</th><th>Admin</th><th>Protokol</th></tr></thead><tbody>`;
    (sys.mgmt || []).forEach(m => {
        mgmt += `<tr><td class="font-mono text-xs">${esc(m.interface)}</td><td class="font-mono text-xs">${esc(m.ip)}</td>`
             +  `<td class="font-mono text-xs">${esc(m.mask)}</td><td class="text-xs">${esc(m.admin)}</td><td class="text-xs">${esc(m.proto)}</td></tr>`;
    });
    document.getElementById('mgmtTable').innerHTML = mgmt + '</tbody>';

    let cards = `<thead><tr><th>Rack/Shelf/Slot</th><th>Tipe</th><th>Terpasang</th><th>Port</th><th>Status</th></tr></thead><tbody>`;
    (sys.cards || []).forEach(c => {
        const badge = c.status === 'INSERVICE' ? 'chip-success' : 'chip-warning';
        cards += `<tr><td class="font-mono text-xs">${esc(c.rack)}/${esc(c.shelf)}/${esc(c.slot)}</td><td class="text-xs">${esc(c.type)}</td>`
              +  `<td class="text-xs">${esc(c.real_type || '—')}</td><td class="text-xs">${esc(c.ports || '—')}</td>`
              +  `<td><span class="chip ${badge}">${esc(c.status || '—')}</span></td></tr>`;
    });
    document.getElementById('cardTable').innerHTML = cards + '</tbody>';
}

function renderPorts(ports) {
    document.getElementById('cardPon').classList.remove('hidden');
    const body = document.getElementById('ponBody');
    if (!ports.length) {
        body.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-slate-400 text-xs">Tidak ada port PON terbaca.</td></tr>';
        return;
    }
    let html = '';
    ports.forEach(p => {
        const id = `${p.board}/${p.slot}/${p.port}`;
        html += `
        <tr>
            <td class="font-mono text-xs font-bold">gpon-olt_${esc(id)}</td>
            <td>
                <div class="text-xs font-medium">${esc(p.name || '—')}</div>
                <div class="text-[10px] text-slate-400">${esc(p.description || '')}</div>
            </td>
            <td class="text-center text-xs">${p.onu_configured}</td>
            <td class="text-center text-xs">${p.onu_working}</td>
            <td class="text-center">
                <span class="chip ${p.enabled ? 'chip-success' : 'chip-neutral'}">${p.enabled ? 'Aktif' : 'Shutdown'}</span>
            </td>
            <td class="text-right whitespace-nowrap">
                <button class="btn btn-secondary btn-sm btn-icon-only" title="Ubah nama & deskripsi"
                        onclick='editPon(${JSON.stringify(p)})'>
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                </button>
                <button class="btn btn-sm ml-1 ${p.enabled ? 'btn-danger' : 'bg-emerald-600 hover:bg-emerald-700 text-white border-0'}"
                        onclick="setPon('${p.board}','${p.slot}','${p.port}',${p.enabled ? 0 : 1},${p.onu_configured})">
                    <i data-lucide="power" class="w-3.5 h-3.5"></i>${p.enabled ? 'Disable' : 'Enable'}
                </button>
            </td>
        </tr>`;
    });
    body.innerHTML = html;
    App.refreshIcons();
}

function renderVlans(vlans) {
    document.getElementById('cardVlan').classList.remove('hidden');
    const box = document.getElementById('vlanBox');
    if (!vlans.length) {
        box.innerHTML = '<span class="text-xs text-slate-400">VLAN database kosong / tidak terbaca.</span>';
        return;
    }
    box.innerHTML = vlans.map(v => `
        <span class="chip chip-neutral font-mono">
            ${v}
            <button class="text-slate-400 hover:text-rose-600 font-bold ml-1" title="Hapus VLAN ${v}"
                    onclick="delVlan(${v})">✕</button>
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
    fd.append('board', board); fd.append('slot', slot); fd.append('port', port);
    fd.append('enable', enable ? '1' : '0');

    App.fetch(`/olts/${OLT_ID}/settings/pon-state`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            pushLog(d.log && d.log.length ? d.log : [d.message], !!d.success);
            if (d.success) loadStatus();
        })
        .catch(e => pushLog('Error: ' + e.message, false));
}

function editPon(p) {
    document.getElementById('pe_title').textContent = `gpon-olt_${p.board}/${p.slot}/${p.port}`;
    document.getElementById('pe_board').value = p.board;
    document.getElementById('pe_slot').value  = p.slot;
    document.getElementById('pe_port').value  = p.port;
    document.getElementById('pe_name').value  = p.name || '';
    document.getElementById('pe_desc').value  = p.description || '';
    App.openModal('ponEditModal');
}

function savePonInfo() {
    const fd = new FormData();
    fd.append('board', document.getElementById('pe_board').value);
    fd.append('slot',  document.getElementById('pe_slot').value);
    fd.append('port',  document.getElementById('pe_port').value);
    fd.append('name',        document.getElementById('pe_name').value.trim());
    fd.append('description', document.getElementById('pe_desc').value.trim());

    App.fetch(`/olts/${OLT_ID}/settings/pon-info`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            App.closeModal('ponEditModal');
            pushLog(d.log && d.log.length ? d.log : [d.message], !!d.success);
            if (d.success) loadStatus();
        })
        .catch(e => pushLog('Error: ' + e.message, false));
}

function addVlan() {
    const el = document.getElementById('newVlanId');
    const v  = parseInt(el.value);
    if (!v || v < 1 || v > 4094) { App.showToast('VLAN ID harus 1–4094.', 'warning'); return; }

    const fd = new FormData();
    fd.append('vlan_id', v);
    App.fetch(`/olts/${OLT_ID}/settings/vlan-add`, { method: 'POST', body: fd })
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
    fd.append('vlan_id', v);
    App.fetch(`/olts/${OLT_ID}/settings/vlan-delete`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            pushLog(d.log && d.log.length ? d.log : [d.message], !!d.success);
            if (d.success) loadStatus();
        })
        .catch(e => pushLog('Error: ' + e.message, false));
}
</script>
<?= $this->endSection() ?>
