<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center space-x-3 mb-4">
        <a href="/templates" class="btn btn-secondary btn-sm btn-icon-only" title="Kembali">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
        </a>
        <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight"><?= $template ? 'Edit Template' : 'Tambah Template' ?></h1>
            <p class="text-xs text-slate-500">Template script provisioning ONU</p>
        </div>
    </div>

    <div class="card !mb-0">
        <form action="<?= $template ? "/templates/{$template['id']}/update" : '/templates/store' ?>" method="POST">
            <?= csrf_field() ?>

            <div class="form-grid mb-3" style="grid-template-columns:2fr 1fr 1fr">
                <div class="form-group">
                    <label class="form-label">Nama Template <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" class="form-input"
                           value="<?= esc($template['name'] ?? '') ?>"
                           placeholder="misal: PPPoE-1G-ZTE" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Brand OLT</label>
                    <select name="brand" class="form-select">
                        <?php foreach (['ZTE','Fiberhome','Huawei','All'] as $b): ?>
                            <option value="<?= $b ?>" <?= ($template['brand'] ?? 'ZTE') === $b ? 'selected' : '' ?>><?= $b ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">WAN Type</label>
                    <select name="wan_type" class="form-select">
                        <?php foreach (['pppoe'=>'PPPoE','dhcp'=>'DHCP/IPoE','static'=>'Static IP'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($template['wan_type'] ?? 'pppoe') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- OLT referensi untuk ambil data -->
            <?php if (!empty($olts)): ?>
            <div class="mb-3 p-3 rounded-xl bg-sky-50 border border-sky-200">
                <div class="text-xs font-bold text-slate-600 mb-2">
                    <i data-lucide="network" class="w-3.5 h-3.5 inline"></i> Ambil Data dari OLT
                </div>
                <div class="flex gap-2 items-end flex-wrap">
                    <div class="flex-grow min-w-[220px]">
                        <label class="form-label">Pilih OLT Referensi</label>
                        <select id="refOlt" class="form-select">
                            <option value="">-- Pilih OLT --</option>
                            <?php foreach ($olts as $olt): ?>
                            <option value="<?= $olt['id'] ?>"
                                    data-ip="<?= esc($olt['ip']) ?>"
                                    data-port="<?= esc($olt['telnet_port']) ?>"
                                    data-user="<?= esc($olt['telnet_user']) ?>"
                                    data-brand="<?= esc($olt['brand']) ?>"
                                    data-model="<?= esc($olt['model']) ?>"
                                    data-tcont="<?= esc(str_replace("\n", '|', $olt['tcont_profiles'] ?? '')) ?>"
                                    data-traffic="<?= esc(str_replace("\n", '|', $olt['traffic_profiles'] ?? '')) ?>">
                                <?= esc($olt['name']) ?> (<?= esc($olt['ip']) ?>)
                                <?php if (!empty($olt['tcont_profiles'])): ?>
                                    — <?= count(array_filter(explode("\n", $olt['tcont_profiles']))) ?> profile
                                <?php else: ?>
                                    — belum sync
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="fetchTcontFromOlt()" id="btnFetchOlt">
                        <i data-lucide="cloud-download" class="w-3.5 h-3.5"></i> Ambil TCONT
                    </button>
                </div>
                <div id="fetchOltResult" class="mt-1.5 text-xs hidden"></div>
            </div>
            <?php endif; ?>

            <div class="form-grid mb-3" style="grid-template-columns:1fr 1fr 1fr 1fr">
                <div class="form-group">
                    <label class="form-label">VLAN Internet</label>
                    <input type="number" name="vlan_internet" id="f_vlan_internet" class="form-input"
                           value="<?= esc($template['vlan_internet'] ?? '') ?>"
                           placeholder="misal: 301" oninput="updateScriptPreview()">
                </div>
                <div class="form-group">
                    <label class="form-label">VLAN Management</label>
                    <input type="number" name="vlan_management" id="f_vlan_mgmt" class="form-input"
                           value="<?= esc($template['vlan_management'] ?? 100) ?>"
                           oninput="updateScriptPreview()">
                </div>
                <div class="form-group">
                    <label class="form-label">TCONT Profile</label>
                    <input type="text" name="tcont_profile" id="f_tcont" class="form-input font-mono"
                           value="<?= esc($template['tcont_profile'] ?? '') ?>"
                           placeholder="250M" oninput="updateScriptPreview()">
                </div>
                <div class="form-group">
                    <label class="form-label">Traffic Limit</label>
                    <input type="text" name="traffic_profile" id="f_traffic" class="form-input font-mono"
                           value="<?= esc($template['traffic_profile'] ?? '') ?>"
                           placeholder="200M" oninput="updateScriptPreview()">
                </div>
            </div>

            <div class="mb-3">
                <div class="flex justify-between items-center mb-1 flex-wrap gap-2">
                    <label class="form-label !mb-0">
                        Script <code class="font-mono bg-slate-100 px-1 rounded">interface gpon-onu_x/x/x:x</code>
                        <span class="font-normal text-slate-400">(tcont, gemport, service-port)</span>
                    </label>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="generateScript()">
                        <i data-lucide="wand-2" class="w-3.5 h-3.5"></i> Generate Otomatis
                    </button>
                </div>
                <textarea name="gpon_onu_script" id="f_script" class="form-textarea font-mono text-xs" rows="8"><?= esc($template['gpon_onu_script'] ?? '') ?></textarea>
                <div class="text-[11px] text-slate-400 mt-1">Satu perintah per baris. Baris dimulai # akan diabaikan.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">Keterangan</label>
                <input type="text" name="description" class="form-input"
                       value="<?= esc($template['description'] ?? '') ?>"
                       placeholder="Deskripsi template ini (opsional)">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> <?= $template ? 'Simpan Perubahan' : 'Simpan Template' ?>
                </button>
                <a href="/templates" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
// Auto-populate profiles dari cache DB saat OLT dipilih
document.getElementById('refOlt').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;

    const tcont   = (opt.dataset.tcont   || '').split('|').map(p => p.trim()).filter(p => p);
    const traffic = (opt.dataset.traffic || '').split('|').map(p => p.trim()).filter(p => p);
    const res     = document.getElementById('fetchOltResult');

    if (tcont.length || traffic.length) {
        showProfilePicker(tcont,   'f_tcont',   'tcontPicker',   updateScriptPreview);
        showProfilePicker(traffic, 'f_traffic', 'trafficPicker', updateScriptPreview);
        res.className  = 'mt-1.5 text-xs text-emerald-600';
        res.textContent = `Cache: TCONT ${tcont.length} | Traffic ${traffic.length} profile — klik "Ambil TCONT" untuk refresh dari OLT`;
        res.classList.remove('hidden');

        // Isi field dengan nilai pertama jika belum terisi
        if (!document.getElementById('f_tcont').value && tcont.length)
            document.getElementById('f_tcont').value = tcont[0];
        if (!document.getElementById('f_traffic').value && traffic.length)
            document.getElementById('f_traffic').value = traffic[0];
        updateScriptPreview();
    } else {
        res.className  = 'mt-1.5 text-xs text-amber-600';
        res.textContent = 'Belum ada cache profile — klik "Ambil TCONT" untuk fetch dari OLT';
        res.classList.remove('hidden');
    }
});

async function fetchTcontFromOlt() {
    const sel = document.getElementById('refOlt');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) { App.showToast('Pilih OLT terlebih dahulu.', 'warning'); return; }

    const btn = document.getElementById('btnFetchOlt');
    const res = document.getElementById('fetchOltResult');
    App.setLoading(btn, true, 'Mengambil...');
    res.className = 'mt-1.5 text-xs text-slate-400';
    res.textContent = 'Menghubungi OLT...';
    res.classList.remove('hidden');

    const fd = new FormData();
    fd.append('olt_id',      opt.value);
    fd.append('ip',          opt.dataset.ip);
    fd.append('telnet_port', opt.dataset.port);
    fd.append('telnet_user', opt.dataset.user);
    fd.append('brand',       opt.dataset.brand);
    fd.append('model',       opt.dataset.model);

    try {
        const data = await App.readJson(await App.fetch('/olts/fetch-tcont', { method: 'POST', body: fd }));
        App.setLoading(btn, false);
        if (!data.success) {
            res.className = 'mt-1.5 text-xs text-rose-600';
            res.textContent = 'Gagal: ' + data.message;
            return;
        }
        const bwCount = (data.traffic_profiles || []).length;
        res.className = 'mt-1.5 text-xs text-emerald-600';
        res.textContent = `✅ TCONT: ${data.count} | Traffic: ${bwCount} profile`;

        const tcontEl = document.getElementById('f_tcont');
        if (!tcontEl.value && data.profiles.length > 0) {
            tcontEl.value = data.profiles[0];
            updateScriptPreview();
        }

        showProfilePicker(data.profiles, 'f_tcont', 'tcontPicker', updateScriptPreview);
        showProfilePicker(data.traffic_profiles || [], 'f_traffic', 'trafficPicker', updateScriptPreview);
    } catch (e) {
        App.setLoading(btn, false);
        res.className = 'mt-1.5 text-xs text-rose-600';
        res.textContent = 'Error: ' + e.message;
    }
}

function showProfilePicker(profiles, fieldId, pickerId, onChange) {
    const existing = document.getElementById(pickerId);
    if (existing) existing.remove();
    if (!profiles.length) return;

    const targetEl = document.getElementById(fieldId);
    const picker = document.createElement('div');
    picker.id = pickerId;
    picker.className = 'mt-1 flex flex-wrap gap-1';
    profiles.forEach(p => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pp-btn px-2.5 py-0.5 rounded-full text-[11px] font-semibold border border-blue-200 text-blue-600 bg-white hover:bg-blue-50 transition';
        btn.textContent = p;
        btn.onclick = () => {
            targetEl.value = p;
            if (onChange) onChange();
            picker.querySelectorAll('button').forEach(b => {
                b.classList.remove('!bg-blue-600', '!text-white', '!border-blue-600');
            });
            btn.classList.add('!bg-blue-600', '!text-white', '!border-blue-600');
        };
        picker.appendChild(btn);
    });
    targetEl.parentElement.appendChild(picker);
}

function generateScript() {
    const vlanI   = parseInt(document.getElementById('f_vlan_internet').value) || 0;
    const vlanM   = parseInt(document.getElementById('f_vlan_mgmt').value) || 0;
    const tcont   = document.getElementById('f_tcont').value.trim();
    const traffic = document.getElementById('f_traffic').value.trim();
    const brand   = document.querySelector('[name="brand"]').value;
    const wan     = document.querySelector('[name="wan_type"]').value;

    let lines = [];

    if (tcont) {
        lines.push(`tcont 1 name tcont profile ${tcont}`);
        lines.push(`gemport 1 name gemport tcont 1`);
        if (traffic) {
            lines.push(`gemport 1 traffic-limit upstream ${traffic} downstream ${traffic}`);
        }
    }

    let spIdx = 1;
    if (vlanM) {
        lines.push(`service-port ${spIdx} vport 1 user-vlan ${vlanM} vlan ${vlanM}`);
        spIdx++;
    }
    if (vlanI) {
        lines.push(`service-port ${spIdx} vport 1 user-vlan ${vlanI} vlan ${vlanI}`);
    }

    if (lines.length === 0) {
        App.showToast('Isi minimal TCONT Profile atau VLAN untuk generate script.', 'warning');
        return;
    }

    document.getElementById('f_script').value = lines.join('\n');
}

function updateScriptPreview() {
    // Auto-update script jika field script kosong (belum diisi manual)
    const scriptEl = document.getElementById('f_script');
    if (scriptEl.value.trim() === '') generateScript();
}
</script>
<?= $this->endSection() ?>
