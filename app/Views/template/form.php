<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="<?= $template ? "/templates/{$template['id']}/update" : '/templates/store' ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Nama Template <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= esc($template['name'] ?? '') ?>"
                                   placeholder="misal: PPPoE-1G-ZTE" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Brand OLT</label>
                            <select name="brand" class="form-select">
                                <?php foreach (['ZTE','Fiberhome','Huawei','All'] as $b): ?>
                                    <option value="<?= $b ?>" <?= ($template['brand'] ?? 'ZTE') === $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-3">
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
                    <div class="mb-3 p-3 rounded border" style="background:#f0f9ff">
                        <div class="small fw-semibold text-muted mb-2">
                            <i class="bi bi-router me-1"></i>Ambil Data dari OLT
                        </div>
                        <div class="d-flex gap-2 align-items-end">
                            <div class="flex-grow-1">
                                <label class="form-label small">Pilih OLT Referensi</label>
                                <select id="refOlt" class="form-select form-select-sm">
                                    <option value="">-- Pilih OLT --</option>
                                    <?php foreach ($olts as $olt): ?>
                                    <option value="<?= $olt['id'] ?>"
                                            data-ip="<?= esc($olt['ip']) ?>"
                                            data-port="<?= $olt['telnet_port'] ?>"
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
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="fetchTcontFromOlt()" id="btnFetchOlt">
                                <i class="bi bi-cloud-download me-1"></i>Ambil TCONT
                            </button>
                        </div>
                        <div id="fetchOltResult" class="mt-1 small d-none"></div>
                    </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label class="form-label">VLAN Internet</label>
                            <input type="number" name="vlan_internet" id="f_vlan_internet" class="form-control"
                                   value="<?= esc($template['vlan_internet'] ?? '') ?>"
                                   placeholder="misal: 301" oninput="updateScriptPreview()">
                        </div>
                        <div class="col-4">
                            <label class="form-label">VLAN Management</label>
                            <input type="number" name="vlan_management" id="f_vlan_mgmt" class="form-control"
                                   value="<?= esc($template['vlan_management'] ?? 100) ?>"
                                   oninput="updateScriptPreview()">
                        </div>
                        <div class="col-2">
                            <label class="form-label">TCONT Profile</label>
                            <input type="text" name="tcont_profile" id="f_tcont" class="form-control"
                                   value="<?= esc($template['tcont_profile'] ?? '') ?>"
                                   placeholder="250M" oninput="updateScriptPreview()">
                        </div>
                        <div class="col-2">
                            <label class="form-label">Traffic Limit</label>
                            <input type="text" name="traffic_profile" id="f_traffic" class="form-control"
                                   value="<?= esc($template['traffic_profile'] ?? '') ?>"
                                   placeholder="200M" oninput="updateScriptPreview()">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0 fw-semibold">
                                Script <code>interface gpon-onu_x/x/x:x</code>
                                <small class="text-muted fw-normal">(tcont, gemport, service-port)</small>
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="generateScript()">
                                <i class="bi bi-magic me-1"></i>Generate Otomatis
                            </button>
                        </div>
                        <textarea name="gpon_onu_script" id="f_script" class="form-control font-monospace"
                                  rows="8"><?= esc($template['gpon_onu_script'] ?? '') ?></textarea>
                        <div class="form-text">Satu perintah per baris. Baris dimulai # akan diabaikan.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Keterangan</label>
                        <input type="text" name="description" class="form-control"
                               value="<?= esc($template['description'] ?? '') ?>"
                               placeholder="Deskripsi template ini (opsional)">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i><?= $template ? 'Simpan Perubahan' : 'Simpan Template' ?>
                        </button>
                        <a href="/templates" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const _csrf = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };

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
        res.className  = 'mt-1 small text-success';
        res.textContent = `Cache: TCONT ${tcont.length} | Traffic ${traffic.length} profile — klik "Ambil TCONT" untuk refresh dari OLT`;
        res.classList.remove('d-none');

        // Isi field dengan nilai pertama jika belum terisi
        if (!document.getElementById('f_tcont').value && tcont.length)
            document.getElementById('f_tcont').value = tcont[0];
        if (!document.getElementById('f_traffic').value && traffic.length)
            document.getElementById('f_traffic').value = traffic[0];
        updateScriptPreview();
    } else {
        res.className  = 'mt-1 small text-warning';
        res.textContent = 'Belum ada cache profile — klik "Ambil TCONT" untuk fetch dari OLT';
        res.classList.remove('d-none');
    }
});

function fetchTcontFromOlt() {
    const sel = document.getElementById('refOlt');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) { alert('Pilih OLT terlebih dahulu.'); return; }

    const btn = document.getElementById('btnFetchOlt');
    const res = document.getElementById('fetchOltResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mengambil...';
    res.className = 'mt-1 small text-muted';
    res.textContent = 'Menghubungi OLT...';
    res.classList.remove('d-none');

    const fd = new FormData();
    fd.append('olt_id',      opt.value);
    fd.append('ip',          opt.dataset.ip);
    fd.append('telnet_port', opt.dataset.port);
    fd.append('telnet_user', opt.dataset.user);
    fd.append('brand',       opt.dataset.brand);
    fd.append('model',       opt.dataset.model);
    fd.append(_csrf.name,    _csrf.hash);

    fetch('/olts/fetch-tcont', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Ambil TCONT';
            if (!data.success) {
                res.className = 'mt-1 small text-danger';
                res.textContent = 'Gagal: ' + data.message;
                return;
            }
            const bwCount = (data.traffic_profiles || []).length;
            res.className = 'mt-1 small text-success';
            res.textContent = `TCONT: ${data.count} | Traffic: ${bwCount} profile`;

            const tcontEl = document.getElementById('f_tcont');
            if (!tcontEl.value && data.profiles.length > 0) {
                tcontEl.value = data.profiles[0];
                updateScriptPreview();
            }

            showProfilePicker(data.profiles, 'f_tcont', 'tcontPicker', updateScriptPreview);
            showProfilePicker(data.traffic_profiles || [], 'f_traffic', 'trafficPicker', updateScriptPreview);
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i>Ambil TCONT';
            res.className = 'mt-1 small text-danger';
            res.textContent = 'Error: ' + e.message;
        });
}

function showProfilePicker(profiles, fieldId, pickerId, onChange) {
    const existing = document.getElementById(pickerId);
    if (existing) existing.remove();
    if (!profiles.length) return;

    const targetEl = document.getElementById(fieldId);
    const picker = document.createElement('div');
    picker.id = pickerId;
    picker.className = 'mt-1 d-flex flex-wrap gap-1';
    profiles.forEach(p => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-primary py-0 px-2';
        btn.textContent = p;
        btn.onclick = () => {
            targetEl.value = p;
            if (onChange) onChange();
            picker.querySelectorAll('button').forEach(b => { b.classList.remove('active','btn-primary'); b.classList.add('btn-outline-primary'); });
            btn.classList.add('active','btn-primary');
            btn.classList.remove('btn-outline-primary');
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
        alert('Isi minimal TCONT Profile atau VLAN untuk generate script.');
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
