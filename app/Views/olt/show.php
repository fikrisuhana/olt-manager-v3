<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/olts" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
    <div>
        <span class="badge bg-primary"><?= esc($olt['brand']) ?></span>
        <span class="badge bg-secondary"><?= esc($olt['model']) ?></span>
        <span class="text-muted small ms-1"><?= esc($olt['ip']) ?>:<?= esc($olt['telnet_port']) ?></span>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="text-muted small" id="cacheTime">
            <i class="bi bi-database me-1"></i>
            <?php if ($cache_updated_at): ?>
                Cache: <?= date('d/m H:i', strtotime($cache_updated_at)) ?>
            <?php else: ?>
                <span class="text-warning">Cache kosong</span>
            <?php endif; ?>
        </span>
        <button class="btn btn-sm btn-outline-warning" id="btnRefreshCache" onclick="refreshCache()"
                title="Sync ulang data ONU terdaftar dari OLT (berat, lakukan sekali / jika ada perubahan)">
            <i class="bi bi-arrow-clockwise me-1"></i>Sync Cache
        </button>
        <?php if ($cache_updated_at): ?>
        <button class="btn btn-sm btn-outline-info" id="btnImportCache" onclick="importFromCache()"
                title="Import semua ONU dari cache ke database (ONU yang sudah ada di-skip)">
            <i class="bi bi-download me-1"></i>Import ke DB
        </button>
        <?php endif; ?>
        <a href="/olts/<?= $olt['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <button class="btn btn-sm btn-primary" id="btnScan" onclick="scanOnu()">
            <i class="bi bi-search me-1"></i>Scan ONU Baru
        </button>
    </div>
</div>
<?php if (!$cache_updated_at): ?>
<div class="alert alert-warning border-0 shadow-sm mb-3 py-2">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Cache belum ada.</strong> Klik <strong>Sync Cache</strong> sekali untuk sinkronisasi data ONU dari OLT.
    Setelah itu, "Scan ONU Baru" hanya kirim 1 perintah ke OLT (ringan).
</div>
<?php endif; ?>

<!-- ONU Belum Dikonfigurasi -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-exclamation-circle me-1 text-warning"></i>ONU Belum Dikonfigurasi</h6>
        <span class="badge bg-warning text-dark" id="uncfgCount">-</span>
    </div>
    <div id="scanWarning" class="d-none"></div>
    <div class="card-body p-0" id="uncfgContainer">
        <div class="text-center py-4 text-muted" id="uncfgEmpty">
            <i class="bi bi-search me-1"></i>Klik "Scan ONU Baru" untuk mulai.
        </div>
    </div>
</div>

<!-- ONU Sudah Terdaftar -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center gap-2">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-check-circle me-1 text-success"></i>ONU Terdaftar di OLT Ini</h6>
        <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-end">
            <input type="search" id="onuSearch" class="form-control form-control-sm" style="max-width:200px"
                   placeholder="Cari SN / Nama..." oninput="filterOnu(this.value)">
            <span class="badge bg-success"><?= count($onus) ?></span>
            <button class="btn btn-sm btn-outline-info py-0" onclick="loadAcsStatus()" id="btnAcs" title="Cek status ACS/TR-069">
                <i class="bi bi-cloud-check me-1"></i>Cek ACS
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($onus)): ?>
            <div class="text-center py-4 text-muted small">Belum ada ONU terdaftar.</div>
        <?php else: ?>
        <?php
            $onusByPort = [];
            foreach ($onus as $onu) {
                $portKey = "{$onu['board']}/{$onu['slot']}/{$onu['port']}";
                $onusByPort[$portKey][] = $onu;
            }
            ksort($onusByPort);
        ?>
        <div class="accordion accordion-flush" id="accordionPon">
            <?php foreach ($onusByPort as $portKey => $portOnus): ?>
            <?php $portId = 'pon-' . str_replace('/', '-', $portKey); ?>
            <div class="accordion-item border-0 border-bottom">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2 px-3" type="button"
                            data-bs-toggle="collapse" data-bs-target="#<?= $portId ?>">
                        <span class="font-monospace fw-semibold me-2">PON <?= esc($portKey) ?></span>
                        <span class="badge bg-secondary ms-1 pon-badge" data-port="<?= esc($portKey) ?>"><?= count($portOnus) ?></span>
                    </button>
                </h2>
                <div id="<?= $portId ?>" class="accordion-collapse collapse">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">SN</th>
                                        <th>Nama</th>
                                        <th style="width:3rem">Idx</th>
                                        <th>Tipe</th>
                                        <th>State OLT</th>
                                        <th>ACS</th>
                                        <th>Sinyal</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($portOnus as $onu): ?>
                                    <tr id="onu-row-<?= $onu['id'] ?>"
                                        data-sn="<?= esc($onu['sn']) ?>"
                                        data-name="<?= esc(strtolower($onu['name'] ?? '')) ?>"
                                        data-pppoe="<?= esc($onu['pppoe_user'] ?? '') ?>"
                                        data-port="<?= esc($portKey) ?>">
                                        <td class="font-monospace small ps-3">
                                            <a href="/onus/<?= $onu['id'] ?>" class="text-decoration-none"><?= esc($onu['sn']) ?></a>
                                        </td>
                                        <td><?= esc($onu['name'] ?? '-') ?></td>
                                        <td class="small text-muted text-center"><?= $onu['onu_index'] ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= esc($onu['onu_type'] ?? '-') ?></span></td>
                                        <td class="olt-state-cell"><span class="text-muted small">—</span></td>
                                        <td class="acs-cell"><span class="text-muted small">—</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary py-0"
                                                    onclick="getSignal(<?= $onu['id'] ?>, this)">
                                                <i class="bi bi-reception-4"></i>
                                            </button>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php $pk = explode('/', $portKey); ?>
                                            <button class="btn btn-sm btn-outline-warning py-0 me-1"
                                                    title="Konfigurasi ulang interface OLT (tcont/gemport/vlan)"
                                                    onclick="openRegister('<?= esc($onu['sn'], 'js') ?>','<?= $pk[0] ?>','<?= $pk[1] ?>','<?= $pk[2] ?>',<?= $onu['onu_index'] ?>,true)">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success py-0 me-1"
                                                    title="Push PPPoE ke ACS"
                                                    onclick="openAcsPush(<?= $onu['id'] ?>, '<?= esc($onu['sn'], 'js') ?>', '<?= esc($onu['pppoe_user'] ?? '', 'js') ?>')">
                                                <i class="bi bi-cloud-arrow-up"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger py-0"
                                                    onclick="deleteOnu(<?= $onu['id'] ?>, '<?= esc($onu['sn'], 'js') ?>', this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Push ACS -->
<div class="modal fade" id="acsPushModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-semibold"><i class="bi bi-cloud-arrow-up me-1"></i>Push PPPoE ke ACS</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2" id="acsPushSn"></p>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Username PPPoE</label>
                    <input type="text" id="acsPushUser" class="form-control form-control-sm" placeholder="user@isp">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-medium">Password PPPoE</label>
                    <input type="text" id="acsPushPass" class="form-control form-control-sm" placeholder="password">
                </div>
                <div id="acsPushResult" class="d-none small mt-2"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success btn-sm" id="btnAcsPush" onclick="doAcsPush()">
                    <i class="bi bi-cloud-arrow-up me-1"></i>Push
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Register ONU -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold"><i class="bi bi-plus-circle me-1"></i>Register ONU</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="registerForm">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <input type="hidden" id="r_board" name="board">
                    <input type="hidden" id="r_slot" name="slot">
                    <input type="hidden" id="r_port" name="port">
                    <input type="hidden" id="r_onu_index" name="onu_index">
                    <input type="hidden" id="r_force" name="force" value="0">

                    <!-- SN + Info -->
                    <div class="row g-3 mb-3">
                        <div class="col-5">
                            <label class="form-label small fw-medium">Serial Number</label>
                            <input type="text" id="r_sn" name="sn" class="form-control font-monospace" readonly>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-medium">Nama Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="PELANGGAN-001" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label small fw-medium">Tipe ONU <span class="text-danger">*</span></label>
                            <input type="text" name="onu_type" id="r_onu_type" class="form-control"
                                   placeholder="ALL-ONT" list="onuTypeList" required>
                            <datalist id="onuTypeList">
                                <?php foreach ($onu_types as $t): ?>
                                <option value="<?= esc($t) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <!-- VLAN + TCONT -->
                    <div class="border rounded p-3 mb-3" style="background:#f8fafc">
                        <div class="small fw-semibold text-muted mb-2">
                            <i class="bi bi-diagram-3 me-1"></i>Konfigurasi Service Port (gpon-onu interface)
                        </div>
                        <div class="row g-3">
                            <div class="col-4">
                                <label class="form-label small fw-medium">VLAN Internet (PPPoE)</label>
                                <?php if (strtoupper($olt['brand'] ?? '') === 'ZTE'): ?>
                                <select name="vlan_internet" id="vlanInternetSelect" class="form-select form-select-sm">
                                    <option value="">-- Memuat profile... --</option>
                                </select>
                                <input type="hidden" name="pppoe_vlan_profile" id="pppoeVlanProfile">
                                <?php else: ?>
                                <input type="number" name="vlan_internet" class="form-control form-control-sm"
                                       placeholder="100" min="1" max="4094">
                                <?php endif; ?>
                                <div class="form-text">service-port 1 vport 1</div>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-medium">VLAN ACS/Mgmt</label>
                                <input type="number" name="vlan_acs" class="form-control form-control-sm"
                                       placeholder="155" min="1" max="4094">
                                <div class="form-text">service-port 2 vport 1</div>
                            </div>
                            <?php
                                $tcontOptions   = array_filter(array_map('trim', explode("\n", $olt['tcont_profiles'] ?? '')));
                                $trafficOptions = array_filter(array_map('trim', explode("\n", $olt['traffic_profiles'] ?? '')));
                            ?>
                            <div class="col-3">
                                <label class="form-label small fw-medium">TCONT Profile</label>
                                <?php if (!empty($tcontOptions)): ?>
                                <select name="tcont_profile" class="form-select form-select-sm">
                                    <option value="">-- Pilih --</option>
                                    <?php foreach ($tcontOptions as $opt): ?>
                                        <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php else: ?>
                                <input type="text" name="tcont_profile" class="form-control form-control-sm" placeholder="250M">
                                <div class="form-text text-warning small"><i class="bi bi-exclamation-triangle me-1"></i><a href="/olts/<?= $olt['id'] ?>/edit">Sync Cache</a> dulu</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-3">
                                <label class="form-label small fw-medium">Traffic Limit</label>
                                <?php if (!empty($trafficOptions)): ?>
                                <select name="traffic_profile" class="form-select form-select-sm">
                                    <option value="">-- Tidak ada --</option>
                                    <?php foreach ($trafficOptions as $opt): ?>
                                        <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php else: ?>
                                <input type="text" name="traffic_profile" class="form-control form-control-sm" placeholder="200M (opsional)">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- PPPoE -->
                    <div class="border rounded p-3 mb-3" style="background:#f0fdf4">
                        <div class="small fw-semibold text-muted mb-2">
                            <i class="bi bi-key me-1"></i>PPPoE Credentials
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-medium">Username PPPoE</label>
                                <input type="text" name="pppoe_user" class="form-control form-control-sm"
                                       placeholder="user@isp">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-medium">Password PPPoE</label>
                                <input type="text" name="pppoe_pass" class="form-control form-control-sm"
                                       placeholder="password">
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>Jika diisi, ONU akan dikonfigurasi otomatis via <strong>GenieACS/TR-069</strong> setelah muncul di ACS (~1–5 menit).
                            </div>
                        </div>
                    </div>

                    <!-- Template tambahan -->
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Template Script Tambahan <span class="text-muted fw-normal">(opsional)</span></label>
                        <select name="template_id" class="form-select form-select-sm">
                            <option value="">-- Tidak ada --</option>
                            <?php
                            $templateModel = new \App\Models\TemplateModel();
                            $templates = $templateModel->getByUser(session()->get('user_id'));
                            foreach ($templates as $t):
                            ?>
                                <option value="<?= $t['id'] ?>"><?= esc($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Script dari template dieksekusi setelah konfigurasi VLAN/TCONT di atas.</div>
                    </div>

                    <div id="registerLog" class="d-none">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted" id="registerLogLabel">Preview CLI</small>
                            <button type="button" class="btn-close btn-sm" onclick="document.getElementById('registerLog').classList.add('d-none')"></button>
                        </div>
                        <pre class="cli-output" id="registerLogContent"></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary me-auto" onclick="previewCli()"
                            title="Lihat perintah CLI yang akan dikirim ke OLT">
                        <i class="bi bi-terminal me-1"></i>Preview CLI
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnRegister">
                        <i class="bi bi-check-circle me-1"></i>Register
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Floating ACS Watcher -->
<div id="acsWatcher" class="d-none position-fixed" style="bottom:1.5rem;right:1.5rem;z-index:1055;min-width:290px;max-width:350px">
    <div class="card shadow-lg border-0">
        <div class="card-header py-2 px-3 d-flex align-items-center gap-2" style="background:#1e293b;color:#e2e8f0">
            <span class="spinner-border spinner-border-sm text-primary flex-shrink-0" id="acsWatchSpinner"></span>
            <span class="small fw-semibold flex-grow-1">Konfigurasi ACS</span>
            <button type="button" class="btn-close btn-close-white" style="font-size:.65rem" onclick="stopAcsWatch()"></button>
        </div>
        <div class="card-body py-2 px-3">
            <div class="font-monospace small fw-semibold text-dark" id="acsWatchSn"></div>
            <div class="small text-muted mt-1" id="acsWatchMsg">Memulai pemantauan...</div>
            <div class="mt-2 d-none" id="acsWatchActions">
                <button class="btn btn-sm btn-outline-primary" onclick="retryAcsPush()">
                    <i class="bi bi-arrow-repeat me-1"></i>Push Ulang
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const OLT_ID = <?= $olt['id'] ?>;

// Auto-load OLT state dari cache saat halaman dibuka
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($cache_updated_at): ?>
    loadOltState();
    <?php endif; ?>
});

function loadOltState() {
    fetch(`/olts/${OLT_ID}/cache-data`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const hasAcsCache = data.acs && Object.keys(data.acs).length > 0;

            document.querySelectorAll('tr[data-sn]').forEach(row => {
                const sn      = row.dataset.sn;
                const oltCell = row.querySelector('.olt-state-cell');
                const acsCell = row.querySelector('.acs-cell');
                const info    = data.data[sn];
                const acsInfo = data.acs?.[sn];

                // OLT state
                if (oltCell) {
                    if (!info) {
                        oltCell.innerHTML = '<span class="badge bg-warning text-dark small">Tidak di cache</span>';
                    } else {
                        const st  = (info.status || '').toLowerCase();
                        const cls = st === 'working' ? 'bg-success'
                                  : st === 'los'     ? 'bg-danger'
                                  : st === 'lofi'    ? 'bg-warning text-dark'
                                  : 'bg-secondary';
                        oltCell.innerHTML = `<span class="badge ${cls} small">${info.status || st}</span>`;
                    }
                }

                // ACS status dari cache
                if (acsCell) {
                    if (acsInfo) {
                        const online  = acsInfo.online;
                        const lastInf = acsInfo.last_inform
                            ? new Date(acsInfo.last_inform).toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'})
                            : '?';
                        const badge = online
                            ? `<span class="badge bg-success"><i class="bi bi-wifi me-1"></i>Online</span>`
                            : `<span class="badge bg-secondary"><i class="bi bi-wifi-off me-1"></i>Offline ${lastInf}</span>`;
                        const model = acsInfo.model ? `<div class="small text-muted">${acsInfo.model}</div>` : '';
                        acsCell.innerHTML = badge + model;
                    } else if (hasAcsCache) {
                        acsCell.innerHTML = '<span class="badge bg-light text-dark border small">Tidak di ACS</span>';
                    }
                }
            });

            // Update info ACS di header jika ada
            if (hasAcsCache && data.acs_updated_at) {
                const ts = new Date(data.acs_updated_at.replace(' ', 'T'));
                const ct = document.getElementById('cacheTime');
                if (ct) ct.title = `OLT: ${data.updated_at} | ACS: ${data.acs_updated_at}`;
            }
        })
        .catch(() => {});
}

function refreshCache() {
    const btn = document.getElementById('btnRefreshCache');
    if (!confirm('Sync cache dari OLT?\n\nProses ini akan kirim beberapa perintah ke OLT (1 per port aktif).\nLakukan hanya jika perlu — jangan terlalu sering!')) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sync...';

    fetch(`/olts/${OLT_ID}/refresh-cache`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Sync Cache';

            if (!data.success) {
                alert('Gagal: ' + data.message);
                return;
            }

            const ct = document.getElementById('cacheTime');
            const now = new Date().toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'});
            ct.innerHTML = `<i class="bi bi-database me-1"></i>Cache: ${now} (${data.count} ONU)`;

            // Hapus banner peringatan jika ada
            document.querySelector('.alert-warning')?.remove();

            // Refresh tampilan state OLT
            loadOltState();

            alert(data.message || `Cache berhasil diperbarui. ${data.count} ONU.`);
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Sync Cache';
            alert('Error: ' + e.message);
        });
}

function importFromCache() {
    if (!confirm('Import semua ONU dari cache ke database?\nONU yang sudah ada di DB akan di-skip.')) return;

    const btn = document.getElementById('btnImportCache');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Import...';

    const fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch(`/olts/${OLT_ID}/import-cache`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-download me-1"></i>Import ke DB';
            if (data.success) {
                alert(data.message);
                if (data.imported > 0) location.reload();
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-download me-1"></i>Import ke DB';
            alert('Error: ' + e.message);
        });
}

function scanOnu() {
    const btn = document.getElementById('btnScan');
    const container = document.getElementById('uncfgContainer');
    const countBadge = document.getElementById('uncfgCount');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scanning...';
    container.innerHTML = '<div class="text-center py-4 text-muted">Menghubungi OLT, mohon tunggu...</div>';

    fetch(`/olts/${OLT_ID}/scan`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i>Scan ONU Baru';

            if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger m-3">${data.message}</div>`;
                countBadge.textContent = '!';
                return;
            }

            countBadge.textContent = data.count;

            // Update cache timestamp
            if (data.cache_updated_at) {
                const ts = new Date(data.cache_updated_at.replace(' ', 'T'));
                const ct = document.getElementById('cacheTime');
                if (ct) ct.innerHTML = `<i class="bi bi-clock me-1"></i>Cache: ${ts.toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'})}`;
            }

            // Refresh OLT state dari cache yang baru diupdate
            loadOltState();

            // Tampilkan peringatan cache kosong tanpa menimpa container
            const warnEl = document.getElementById('scanWarning');
            if (data.no_cache_warning) {
                warnEl.className = '';
                warnEl.innerHTML = `<div class="alert alert-danger rounded-0 border-0 border-bottom py-2 px-3 mb-0 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Cache belum ada — index ONU tidak bisa ditentukan dengan aman.</strong>
                    Klik <strong>Sync Cache</strong> dulu sebelum register ONU baru agar tidak menimpa ONU yang sudah aktif.
                </div>`;
            } else {
                warnEl.className = 'd-none';
                warnEl.innerHTML = '';
            }

            if (data.count === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted small">Tidak ada ONU baru yang belum dikonfigurasi.</div>';
                return;
            }

            const noCache = data.no_cache_warning;
            let rows = data.onus.map(o => {
                const portLabel = `${o.board}/${o.slot}/${o.port}`;
                const nextIdx   = o.next_index ?? 1;
                const badge = o.already_registered
                    ? `<div class="d-flex gap-1 align-items-center">
                         <span class="badge bg-secondary">Sudah di DB</span>
                         ${o.existing_id ? `<a href="/onus/${o.existing_id}" class="btn btn-sm btn-outline-secondary py-0 px-1" title="Lihat ONU"><i class="bi bi-eye"></i></a>` : ''}
                         <button class="btn btn-sm btn-outline-warning py-0 px-1" title="Konfigurasi ulang ke OLT"
                             onclick="openRegister('${o.sn}','${o.board}','${o.slot}','${o.port}',${nextIdx},true)">
                             <i class="bi bi-arrow-repeat me-1"></i>Konfigurasi Ulang
                         </button>
                       </div>`
                    : noCache
                        ? `<button class="btn btn-sm btn-secondary" disabled title="Sync Cache dulu untuk index yang aman">
                             <i class="bi bi-lock me-1"></i>Sync Cache dulu
                           </button>`
                        : `<button class="btn btn-sm btn-success" onclick="openRegister('${o.sn}','${o.board}','${o.slot}','${o.port}',${nextIdx})">
                             <i class="bi bi-plus me-1"></i>Register (idx ${nextIdx})
                           </button>`;
                return `<tr>
                    <td class="font-monospace small">${o.sn}</td>
                    <td class="small text-muted">${portLabel}</td>
                    <td><span class="badge bg-warning text-dark">${o.state ?? '-'}</span></td>
                    <td>${badge}</td>
                </tr>`;
            }).join('');

            container.innerHTML = `<div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Serial Number</th><th>Port</th><th>State</th><th></th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i>Scan ONU Baru';
            container.innerHTML = `<div class="alert alert-danger m-3">Error: ${e.message}</div>`;
        });
}

function openRegister(sn, board, slot, port, idx, force = false) {
    document.getElementById('r_sn').value    = sn;
    document.getElementById('r_board').value = board;
    document.getElementById('r_slot').value  = slot;
    document.getElementById('r_port').value  = port;
    document.getElementById('r_onu_index').value = idx;
    document.getElementById('r_force').value     = force ? '1' : '0';
    document.getElementById('registerLog').classList.add('d-none');
    document.getElementById('registerLogContent').textContent = '';

    // Ubah judul modal jika re-register
    const title = document.querySelector('#registerModal .modal-title');
    title.innerHTML = force
        ? '<i class="bi bi-arrow-repeat me-1"></i>Konfigurasi Ulang ONU'
        : '<i class="bi bi-plus-circle me-1"></i>Register ONU';

    // Reset form
    document.querySelector('[name="name"]').value          = '';
    document.querySelector('[name="onu_type"]').value      = sn.startsWith('ZTEG') ? 'ZTE-F609' : 'ALL-ONT';
    const vlanEl = document.querySelector('[name="vlan_internet"]');
    if (vlanEl) vlanEl.value = '';
    document.querySelector('[name="vlan_acs"]').value      = '';
    const tcontEl = document.querySelector('[name="tcont_profile"]');
    if (tcontEl) tcontEl.value = '';
    document.querySelector('[name="pppoe_user"]').value    = '';
    document.querySelector('[name="pppoe_pass"]').value    = '';

    new bootstrap.Modal(document.getElementById('registerModal')).show();
    loadVlanProfiles();
}

function previewCli() {
    const board  = document.getElementById('r_board').value;
    const slot   = document.getElementById('r_slot').value;
    const port   = document.getElementById('r_port').value;
    const idx    = document.getElementById('r_onu_index').value;
    const sn     = document.getElementById('r_sn').value;
    const name   = document.querySelector('[name="name"]').value || 'NAMA_PELANGGAN';
    const type   = document.querySelector('[name="onu_type"]').value || 'ALL-ONT';
    const vlanI  = parseInt(document.querySelector('[name="vlan_internet"]').value) || 0;
    const vlanA  = parseInt(document.querySelector('[name="vlan_acs"]').value) || 0;
    const tcont  = document.querySelector('[name="tcont_profile"]').value.trim();
    const pppoeU = document.querySelector('[name="pppoe_user"]').value.trim();

    // Format diverifikasi dari ZTE C320 v1.2 (show running-config interface gpon-onu_*)
    let cli = `! ══ ZTE C320 CLI Preview ══\n`;
    cli += `conf t\n`;
    cli += `interface gpon-olt_${board}/${slot}/${port}\n`;
    cli += `  onu ${idx} type ${type} sn ${sn}\n`;
    cli += `exit\n`;
    cli += `interface gpon-onu_${board}/${slot}/${port}:${idx}\n`;
    cli += `  name ${name}\n`;
    cli += `  sn-bind enable sn\n`;
    if (tcont) {
        cli += `  tcont 1 name tcont profile ${tcont}\n`;
        cli += `  gemport 1 name gemport tcont 1\n`;
    }
    let spIdx = 1;
    if (vlanI) {
        cli += `  service-port ${spIdx} vport 1 user-vlan ${vlanI} vlan ${vlanI}\n`;
        spIdx++;
    }
    if (vlanA) {
        cli += `  service-port ${spIdx} vport 1 user-vlan ${vlanA} vlan ${vlanA}\n`;
    }
    cli += `exit\n`;
    cli += `write\n`;

    if (pppoeU) {
        cli += `\n! PPPoE "${pppoeU}" disimpan ke DB — push via GenieACS/TR-069 setelah ONU online`;
    }

    document.getElementById('registerLogLabel').textContent = 'Preview CLI (belum dikirim)';
    document.getElementById('registerLogContent').style.color = '#93c5fd';
    document.getElementById('registerLogContent').textContent = cli;
    document.getElementById('registerLog').classList.remove('d-none');
}

document.getElementById('registerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnRegister');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Mendaftarkan...';

    const pppoeUser = this.querySelector('[name="pppoe_user"]').value.trim();
    const pppoePass = this.querySelector('[name="pppoe_pass"]').value.trim();

    const fd = new FormData(this);
    fetch(`/olts/${OLT_ID}/onu/register`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Register';

            const logEl      = document.getElementById('registerLog');
            const logContent = document.getElementById('registerLogContent');
            const logLabel   = document.getElementById('registerLogLabel');
            logEl.classList.remove('d-none');
            logLabel.textContent = data.success ? 'Log OLT — Berhasil' : 'Log OLT — Gagal';
            logContent.textContent = data.log ? data.log.join('\n') : data.message;

            if (data.success) {
                logContent.style.color = '#86efac';
                const hasWarn = (data.log || []).some(l => l.includes('WARN') || l.includes('Error'));
                const delay = 15000;
                if (hasWarn) logContent.textContent += '\n\n⚠ Ada peringatan — cek log di atas.';
                if (data.watch_acs && data.onu_id && pppoePass) {
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('registerModal'))?.hide();
                        startAcsWatch(data.onu_id, data.sn, pppoeUser, pppoePass, data.push_via_acs !== false);
                    }, delay);
                } else {
                    setTimeout(() => location.reload(), delay);
                }
            } else {
                logContent.style.color = '#fca5a5';
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Register';
            alert('Error: ' + e.message);
        });
});

// ── ACS Watcher ─────────────────────────────────────────────────
const _csrf = { name: '<?= csrf_token() ?>', hash: '<?= csrf_hash() ?>' };
let _acsWatch = { interval: null, attempt: 0, onuId: 0, sn: '', user: '', pass: '' };
const ACS_MAX_ATTEMPT = 20; // 5 menit × 15 detik

function startAcsWatch(onuId, sn, user, pass, pushViaAcs = true) {
    if (_acsWatch.interval) clearInterval(_acsWatch.interval);
    Object.assign(_acsWatch, { interval: null, attempt: 0, onuId, sn, user, pass, pushViaAcs });

    document.getElementById('acsWatchSn').textContent = sn;
    _setWatchMsg('Menunggu ONU online di ACS...', false);
    document.getElementById('acsWatchSpinner').classList.remove('d-none');
    document.getElementById('acsWatchActions').classList.add('d-none');
    document.getElementById('acsWatcher').classList.remove('d-none');

    _pollAcs(); // cek langsung sekali
    _acsWatch.interval = setInterval(_pollAcs, 15000);
}

function _pollAcs() {
    _acsWatch.attempt++;
    const elapsed = _acsWatch.attempt * 15;
    const m = Math.floor(elapsed / 60), s = String(elapsed % 60).padStart(2, '0');
    _setWatchMsg(`Menunggu di ACS... (${m}:${s})`, false);

    if (_acsWatch.attempt > ACS_MAX_ATTEMPT) {
        clearInterval(_acsWatch.interval);
        _acsWatch.interval = null;
        document.getElementById('acsWatchSpinner').classList.add('d-none');
        _setWatchMsg('Timeout — ONU belum muncul di ACS (5 menit).', false);
        document.getElementById('acsWatchActions').classList.remove('d-none');
        return;
    }

    fetch(`/onus/${_acsWatch.onuId}/acs-info`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                clearInterval(_acsWatch.interval);
                _acsWatch.interval = null;
                document.getElementById('acsWatchSpinner').classList.add('d-none');
                if (_acsWatch.pushViaAcs) {
                    _setWatchMsg('ONU ditemukan! Mendorong konfigurasi PPPoE via ACS...', false);
                    document.getElementById('acsWatchSpinner').classList.remove('d-none');
                    _pushAcs();
                } else {
                    _setWatchMsg('ONU online di ACS! PPPoE sudah diset via OLT.', true);
                    setTimeout(() => location.reload(), 3000);
                }
            }
        })
        .catch(() => {});
}

function _pushAcs() {
    const fd = new FormData();
    fd.append('action',     'pppoe');
    fd.append('pppoe_user', _acsWatch.user);
    fd.append('pppoe_pass', _acsWatch.pass);
    fd.append(_csrf.name,   _csrf.hash);

    fetch(`/onus/${_acsWatch.onuId}/acs-set`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            document.getElementById('acsWatchSpinner').classList.add('d-none');
            if (data.success) {
                _setWatchMsg('PPPoE berhasil dikonfigurasi!', true);
                setTimeout(() => location.reload(), 3000);
            } else {
                _setWatchMsg('Push gagal: ' + (data.message || 'Error'), false);
                document.getElementById('acsWatchActions').classList.remove('d-none');
            }
        })
        .catch(() => {
            _setWatchMsg('Error saat push ke ACS.', false);
            document.getElementById('acsWatchActions').classList.remove('d-none');
        });
}

function retryAcsPush() {
    document.getElementById('acsWatchActions').classList.add('d-none');
    document.getElementById('acsWatchSpinner').classList.remove('d-none');
    _setWatchMsg('Mencoba push ulang...', false);
    _pushAcs();
}

function stopAcsWatch() {
    if (_acsWatch.interval) clearInterval(_acsWatch.interval);
    _acsWatch.interval = null;
    document.getElementById('acsWatcher').classList.add('d-none');
}

function _setWatchMsg(msg, isSuccess) {
    const el = document.getElementById('acsWatchMsg');
    el.textContent = msg;
    el.className   = 'small mt-1 ' + (isSuccess ? 'text-success' : 'text-muted');
}

// ── VLAN Profile Dropdown (ZTE) ────────────────────────────────
let _vlanProfiles = null; // cache per page load

function loadVlanProfiles() {
    const sel = document.getElementById('vlanInternetSelect');
    if (!sel) return; // non-ZTE: pakai input biasa
    if (_vlanProfiles) { _populateVlanSelect(_vlanProfiles); return; } // sudah dicache

    sel.innerHTML = '<option value="">Memuat dari OLT...</option>';
    fetch(`/olts/${OLT_ID}/vlan-profiles`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.profiles.length) {
                _vlanProfiles = data.profiles;
                _populateVlanSelect(data.profiles);
            } else {
                sel.innerHTML = '<option value="">-- Gagal fetch, isi manual --</option>';
                // fallback ke input text
                sel.outerHTML = '<input type="number" name="vlan_internet" id="vlanInternetFallback" class="form-control form-control-sm" placeholder="155" min="1" max="4094">';
            }
        })
        .catch(() => {
            if (document.getElementById('vlanInternetSelect')) {
                document.getElementById('vlanInternetSelect').outerHTML =
                    '<input type="number" name="vlan_internet" id="vlanInternetFallback" class="form-control form-control-sm" placeholder="155" min="1" max="4094">';
            }
        });
}

function _populateVlanSelect(profiles) {
    const sel = document.getElementById('vlanInternetSelect');
    const pfInput = document.getElementById('pppoeVlanProfile');
    if (!sel) return;
    sel.innerHTML = '<option value="">-- Pilih VLAN PPPoE --</option>';
    profiles.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.vlan;
        opt.dataset.profile = p.name;
        opt.textContent = `${p.name} — VLAN ${p.vlan}`;
        sel.appendChild(opt);
    });
    sel.onchange = () => {
        const selected = sel.options[sel.selectedIndex];
        if (pfInput) pfInput.value = selected.dataset.profile || '';
    };
}

function getSignal(onuId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    fetch(`/onus/${onuId}/signal`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.success && data.signal) {
                const onuRx = data.signal.onu_rx ?? '?';
                const oltRx = data.signal.olt_rx ?? '?';
                const qualClass = {'good':'text-success','warn':'text-warning','bad':'text-danger'}[data.quality] ?? '';
                btn.innerHTML = `<small class="${qualClass}">${onuRx} dBm</small>`;
                btn.title = `ONU-RX: ${onuRx} | OLT-RX: ${oltRx} | ONU-TX: ${data.signal.onu_tx} | OLT-TX: ${data.signal.olt_tx}`;
            } else {
                btn.innerHTML = '<i class="bi bi-reception-4"></i>';
                alert(data.message);
            }
        });
}

function loadAcsStatus() {
    const btn = document.getElementById('btnAcs');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch(`/olts/${OLT_ID}/acs-status`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-check me-1"></i>Cek ACS';

            if (!data.success) {
                alert('ACS: ' + data.message);
                return;
            }

            // Update setiap baris ONU dengan status ACS
            document.querySelectorAll('tr[data-sn]').forEach(row => {
                const sn   = row.dataset.sn;
                const cell = row.querySelector('.acs-cell');
                const info = data.data[sn];

                if (!cell) return;

                if (!info) {
                    cell.innerHTML = '<span class="badge bg-light text-dark border">Tidak di ACS</span>';
                    return;
                }

                const online  = info.online;
                const lastInf = info.last_inform ? new Date(info.last_inform).toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'}) : '?';
                const badge   = online
                    ? `<span class="badge bg-success"><i class="bi bi-wifi me-1"></i>Online</span>`
                    : `<span class="badge bg-secondary"><i class="bi bi-wifi-off me-1"></i>Offline ${lastInf}</span>`;
                const model = info.model ? `<div class="small text-muted">${info.model}</div>` : '';
                cell.innerHTML = badge + model;
            });
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-check me-1"></i>Cek ACS';
            alert('Error: ' + e.message);
        });
}

function filterOnu(q) {
    q = q.toLowerCase();
    document.querySelectorAll('tr[data-sn]').forEach(row => {
        const sn   = (row.dataset.sn   || '').toLowerCase();
        const name = (row.dataset.name || '').toLowerCase();
        row.style.display = (!q || sn.includes(q) || name.includes(q)) ? '' : 'none';
    });

    // Expand accordion panels yang ada hasil, collapse yang kosong
    document.querySelectorAll('#accordionPon .accordion-item').forEach(item => {
        const collapse = item.querySelector('.accordion-collapse');
        const btn      = item.querySelector('.accordion-button');
        if (!collapse) return;
        if (!q) {
            collapse.classList.remove('show');
            btn?.classList.add('collapsed');
            return;
        }
        const hasVisible = [...item.querySelectorAll('tr[data-sn]')].some(r => r.style.display !== 'none');
        if (hasVisible) {
            collapse.classList.add('show');
            btn?.classList.remove('collapsed');
        } else {
            collapse.classList.remove('show');
            btn?.classList.add('collapsed');
        }
    });
}

let _acsPushOnuId = 0;
function openAcsPush(onuId, sn, pppoeUser) {
    _acsPushOnuId = onuId;
    document.getElementById('acsPushSn').textContent   = 'SN: ' + sn;
    document.getElementById('acsPushUser').value       = pppoeUser || '';
    document.getElementById('acsPushPass').value       = '';
    document.getElementById('acsPushResult').className = 'd-none';
    new bootstrap.Modal(document.getElementById('acsPushModal')).show();
}

function doAcsPush() {
    const btn  = document.getElementById('btnAcsPush');
    const res  = document.getElementById('acsPushResult');
    const user = document.getElementById('acsPushUser').value.trim();
    const pass = document.getElementById('acsPushPass').value.trim();

    if (!user || !pass) {
        res.className = 'small mt-2 text-danger';
        res.textContent = 'Username dan password wajib diisi.';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Pushing...';

    const fd = new FormData();
    fd.append('action',     'pppoe');
    fd.append('pppoe_user', user);
    fd.append('pppoe_pass', pass);
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch(`/onus/${_acsPushOnuId}/acs-set`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Push';
            res.className = 'small mt-2 ' + (data.success ? 'text-success' : 'text-danger');
            res.textContent = data.success ? 'PPPoE berhasil dipush ke ONU.' : (data.message || 'Gagal.');
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Push';
            res.className = 'small mt-2 text-danger';
            res.textContent = 'Error: ' + e.message;
        });
}

function deleteOnu(onuId, sn, btn) {
    if (!confirm(`Hapus ONU ${sn} dari OLT?\nAksi ini akan menghapus konfigurasi dari OLT.`)) return;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const fd = new FormData();
    fd.append(_csrf.name, _csrf.hash);
    fetch(`/onus/${onuId}/delete`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else { btn.disabled = false; btn.innerHTML = origHtml; alert(data.message); }
    })
    .catch(e => { btn.disabled = false; btn.innerHTML = origHtml; alert('Error: ' + e.message); });
}
</script>
<?= $this->endSection() ?>
