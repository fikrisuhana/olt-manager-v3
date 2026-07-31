<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/olts" class="btn btn-google-secondary py-1 px-3"><i class="bi bi-arrow-left"></i> Kembali</a>
    <div class="d-flex align-items-center gap-2">
        <span class="chip chip-info font-monospace"><?= esc($olt['brand']) ?></span>
        <span class="chip chip-neutral"><?= esc($olt['model']) ?></span>
        <?php if (($olt['use_acs'] ?? 1) == 1): ?>
            <span class="chip chip-success" title="OLT terhubung dengan ACS/TR-069"><i class="bi bi-wifi me-1"></i>ACS Aktif</span>
        <?php else: ?>
            <span class="chip chip-neutral" title="OLT Standalone / Pure OMCI tanpa ACS"><i class="bi bi-hdd me-1"></i>ACS Off (Pure OMCI)</span>
        <?php endif; ?>
        <span class="text-secondary small font-monospace"><i class="bi bi-diagram-2 me-1"></i><?= esc($olt['ip']) ?>:<?= esc($olt['telnet_port']) ?></span>
    </div>

    <div class="ms-auto d-flex align-items-center gap-2">
        <span class="text-secondary small me-2" id="cacheTime">
            <i class="bi bi-database me-1"></i>
            <?php if ($cache_updated_at): ?>
                Cache: <?= date('d/m H:i', strtotime($cache_updated_at)) ?>
            <?php else: ?>
                <span class="chip chip-warning">Cache kosong</span>
            <?php endif; ?>
        </span>

        <!-- Tombol Kelola Profil OLT -->
        <button class="btn btn-google-secondary" onclick="openOltProfilesModal()" title="Kelola TCONT, Traffic Limit & VLAN Profile">
            <i class="bi bi-sliders me-1 text-primary"></i> Profil OLT
        </button>

        <button class="btn btn-google-secondary" id="btnRefreshCache" onclick="refreshCache()"
                title="Sync ulang data ONU terdaftar dari OLT">
            <i class="bi bi-arrow-clockwise me-1 text-warning"></i> Sync Cache
        </button>

        <?php if ($cache_updated_at): ?>
        <button class="btn btn-google-secondary" id="btnImportCache" onclick="importFromCache()"
                title="Import semua ONU dari cache ke database">
            <i class="bi bi-download me-1 text-info"></i> Import ke DB
        </button>
        <?php endif; ?>

        <a href="/olts/<?= $olt['id'] ?>/edit" class="btn btn-google-secondary">
            <i class="bi bi-pencil me-1 text-secondary"></i> Edit OLT
        </a>
        <button class="btn btn-google-primary" id="btnScan" onclick="scanOnu()">
            <i class="bi bi-search me-1"></i> Scan ONU Baru
        </button>
    </div>
</div>

<?php if (!$cache_updated_at): ?>
<div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 py-2 px-3 d-flex align-items-center gap-2">
    <i class="bi bi-info-circle-fill text-primary"></i>
    <div>
        <strong>Cache OLT belum di-sync.</strong> Klik <strong>Sync Cache</strong> sekali untuk menarik daftar ONU dari OLT, atau klik <strong>Scan ONU Baru</strong> untuk langsung mendaftarkan ONU baru.
    </div>
</div>
<?php endif; ?>

<!-- ONU Belum Dikonfigurasi -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div class="fw-bold d-flex align-items-center gap-2" style="font-family: 'Google Sans', sans-serif;">
            <i class="bi bi-exclamation-circle text-warning fs-5"></i>
            ONU Belum Dikonfigurasi
        </div>
        <span class="chip chip-warning fw-bold" id="uncfgCount">-</span>
    </div>
    <div id="scanWarning" class="d-none"></div>
    <div class="card-body p-0" id="uncfgContainer">
        <div class="text-center py-4 text-secondary" id="uncfgEmpty">
            <i class="bi bi-search me-1"></i>Klik "Scan ONU Baru" untuk deteksi ONU yang baru dicolok.
        </div>
    </div>
</div>

<!-- ONU Sudah Terdaftar -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center gap-2">
        <div class="fw-bold d-flex align-items-center gap-2" style="font-family: 'Google Sans', sans-serif;">
            <i class="bi bi-check-circle text-success fs-5"></i>
            ONU Terdaftar di OLT Ini
        </div>
        <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-end">
            <input type="search" id="onuSearch" class="form-control form-control-sm" style="max-width:240px"
                   placeholder="Cari SN / Nama..." oninput="filterOnu(this.value)">
            <span class="chip chip-success font-monospace fw-bold"><?= count($onus) ?></span>
            <button class="btn btn-google-secondary py-1 px-3" onclick="loadAcsStatus()" id="btnAcs" title="Cek status ACS/TR-069">
                <i class="bi bi-cloud-check me-1 text-info"></i> Cek ACS
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($onus)): ?>
            <div class="text-center py-5 text-secondary">
                <i class="bi bi-inbox fs-2 text-muted mb-2 d-block"></i>
                Belum ada ONU terdaftar di database.
                <?php if ($cache_updated_at): ?>
                    <br>Cache sudah ada — klik <strong>Import ke DB</strong> di atas untuk memuat ONU hasil Sync Cache ke daftar.
                <?php endif; ?>
            </div>
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
                    <button class="accordion-button collapsed py-3 px-4 bg-white" type="button"
                            data-bs-toggle="collapse" data-bs-target="#<?= $portId ?>">
                        <span class="font-monospace fw-bold text-dark me-2">PON <?= esc($portKey) ?></span>
                        <span class="chip chip-neutral font-monospace pon-badge" data-port="<?= esc($portKey) ?>"><?= count($portOnus) ?></span>
                    </button>
                </h2>
                <div id="<?= $portId ?>" class="accordion-collapse collapse">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Serial Number</th>
                                        <th>Nama Pelanggan</th>
                                        <th class="text-center" style="width:3rem">Idx</th>
                                        <th>Tipe ONU</th>
                                        <th>State OLT</th>
                                        <th>Status ACS</th>
                                        <th>Sinyal</th>
                                        <th class="pe-4 text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($portOnus as $onu): ?>
                                    <tr id="onu-row-<?= $onu['id'] ?>"
                                        data-sn="<?= esc($onu['sn']) ?>"
                                        data-name="<?= esc(strtolower($onu['name'] ?? '')) ?>"
                                        data-pppoe="<?= esc($onu['pppoe_user'] ?? '') ?>"
                                        data-port="<?= esc($portKey) ?>">
                                        <td class="font-monospace small ps-4 fw-bold">
                                            <a href="/onus/<?= $onu['id'] ?>" class="text-decoration-none text-primary"><?= esc($onu['sn']) ?></a>
                                        </td>
                                        <td class="fw-medium"><?= esc($onu['name'] ?? '-') ?></td>
                                        <td class="small text-secondary text-center font-monospace"><?= $onu['onu_index'] ?></td>
                                        <td><span class="chip chip-neutral"><?= esc($onu['onu_type'] ?? '-') ?></span></td>
                                        <td class="olt-state-cell"><span class="text-muted small">—</span></td>
                                        <td class="acs-cell"><span class="text-muted small">—</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-google-secondary py-0 px-2"
                                                    onclick="getSignal(<?= $onu['id'] ?>, this)">
                                                <i class="bi bi-reception-4"></i>
                                            </button>
                                        </td>
                                        <td class="text-nowrap pe-4 text-end">
                                            <?php $pk = explode('/', $portKey); ?>
                                            <button class="btn btn-sm btn-google-secondary py-0 px-2 me-1"
                                                    title="Konfigurasi ulang ONU (VLAN / TCONT / PPPoE)"
                                                    onclick="openRegister('<?= esc($onu['sn'], 'js') ?>','<?= $pk[0] ?>','<?= $pk[1] ?>','<?= $pk[2] ?>',<?= $onu['onu_index'] ?>,true,'<?= esc($onu['onu_type'] ?? '', 'js') ?>')">
                                                <i class="bi bi-arrow-repeat text-warning"></i>
                                            </button>
                                            <button class="btn btn-sm btn-google-secondary py-0 px-2 me-1"
                                                    title="Push PPPoE ke ONU"
                                                    onclick="openAcsPush(<?= $onu['id'] ?>, '<?= esc($onu['sn'], 'js') ?>', '<?= esc($onu['pppoe_user'] ?? '', 'js') ?>')">
                                                <i class="bi bi-cloud-arrow-up text-success"></i>
                                            </button>
                                            <button class="btn btn-sm btn-google-secondary py-0 px-2"
                                                    title="Hapus ONU dari OLT"
                                                    onclick="deleteOnu(<?= $onu['id'] ?>, '<?= esc($onu['sn'], 'js') ?>', this)">
                                                <i class="bi bi-trash text-danger"></i>
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

<!-- Modal Kelola Profil OLT (TCONT, Traffic Limit & VLAN Profile) -->
<div class="modal fade" id="oltProfilesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" style="font-family:'Google Sans',sans-serif;">
                    <i class="bi bi-sliders text-primary me-2"></i>Manajemen Profil OLT (<?= esc($olt['name']) ?>)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center justify-content-between mb-3 bg-light p-3 rounded-3">
                    <div class="small text-secondary">
                        <i class="bi bi-info-circle me-1"></i>Profil ini digunakan saat registrasi ONU & setting service-port di OLT.
                    </div>
                    <button class="btn btn-google-primary py-1 px-3" id="btnSyncProfiles" onclick="syncProfilesFromOlt()">
                        <i class="bi bi-cloud-download me-1"></i> Tarik dari OLT
                    </button>
                </div>

                <ul class="nav nav-tabs nav-fill mb-3" id="profileTabs">
                    <li class="nav-item">
                        <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#tabTcont">
                            <i class="bi bi-speedometer2 me-1"></i> TCONT Profiles
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tabTraffic">
                            <i class="bi bi-lightning-charge me-1"></i> Traffic Limit Profiles
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#tabVlan">
                            <i class="bi bi-diagram-3 me-1"></i> VLAN Profiles (PPPoE)
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="profileTabContents">
                    <!-- Tab TCONT -->
                    <div class="tab-pane fade show active" id="tabTcont">
                        <!-- Form Tambah ke OLT -->
                        <div class="border rounded-3 p-3 mb-3 bg-light">
                            <div class="small fw-bold text-dark mb-2"><i class="bi bi-plus-circle text-primary me-1"></i> Tambah TCONT Profile Baru ke OLT (CLI)</div>
                            <div class="row g-2 align-items-end">
                                <div class="col-5">
                                    <label class="form-label small text-secondary mb-1">Nama Profile</label>
                                    <input type="text" id="newTcontName" class="form-control form-control-sm" placeholder="500M">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small text-secondary mb-1">Max BW (Kbps)</label>
                                    <input type="number" id="newTcontBw" class="form-control form-control-sm" placeholder="512000" value="512000">
                                </div>
                                <div class="col-3">
                                    <button class="btn btn-google-primary btn-sm w-100" onclick="pushAddProfile('tcont')">
                                        <i class="bi bi-cloud-arrow-up me-1"></i> Push OLT
                                    </button>
                                </div>
                            </div>
                        </div>

                        <label class="form-label small fw-bold">Daftar TCONT Profiles di Database/OLT (Satu per baris)</label>
                        <textarea id="txtTcontProfiles" class="form-control font-monospace" rows="5"
                                  placeholder="250M&#10;100M&#10;50M"><?= esc($olt['tcont_profiles'] ?? '') ?></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="form-text">Profil DBA di OLT. Hapus profile dari OLT via CLI:</div>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" id="delTcontName" class="form-control form-control-sm" style="width:140px" placeholder="Nama Profile">
                                <button class="btn btn-google-secondary btn-sm text-danger" onclick="pushDeleteProfile('tcont')">
                                    <i class="bi bi-trash me-1"></i> Hapus dari OLT
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Traffic Limit -->
                    <div class="tab-pane fade" id="tabTraffic">
                        <!-- Form Tambah ke OLT -->
                        <div class="border rounded-3 p-3 mb-3 bg-light">
                            <div class="small fw-bold text-dark mb-2"><i class="bi bi-plus-circle text-primary me-1"></i> Tambah Traffic Limit Profile Baru ke OLT (CLI)</div>
                            <div class="row g-2 align-items-end">
                                <div class="col-5">
                                    <label class="form-label small text-secondary mb-1">Nama Profile</label>
                                    <input type="text" id="newTrafficName" class="form-control form-control-sm" placeholder="500M">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small text-secondary mb-1">Speed SIR/PIR (Kbps)</label>
                                    <input type="number" id="newTrafficSpeed" class="form-control form-control-sm" placeholder="512000" value="512000">
                                </div>
                                <div class="col-3">
                                    <button class="btn btn-google-primary btn-sm w-100" onclick="pushAddProfile('traffic')">
                                        <i class="bi bi-cloud-arrow-up me-1"></i> Push OLT
                                    </button>
                                </div>
                            </div>
                        </div>

                        <label class="form-label small fw-bold">Daftar Traffic Limit Profiles di Database/OLT (Satu per baris)</label>
                        <textarea id="txtTrafficProfiles" class="form-control font-monospace" rows="5"
                                  placeholder="50M&#10;100M&#10;250M"><?= esc($olt['traffic_profiles'] ?? '') ?></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="form-text">Batas kecepatan Upstream/Downstream gemport.</div>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" id="delTrafficName" class="form-control form-control-sm" style="width:140px" placeholder="Nama Profile">
                                <button class="btn btn-google-secondary btn-sm text-danger" onclick="pushDeleteProfile('traffic')">
                                    <i class="bi bi-trash me-1"></i> Hapus dari OLT
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tab VLAN Profile -->
                    <div class="tab-pane fade" id="tabVlan">
                        <!-- Form Tambah ke OLT -->
                        <div class="border rounded-3 p-3 mb-3 bg-light">
                            <div class="small fw-bold text-dark mb-2"><i class="bi bi-plus-circle text-primary me-1"></i> Tambah ONU VLAN Profile Baru ke OLT (CLI)</div>
                            <div class="row g-2 align-items-end">
                                <div class="col-5">
                                    <label class="form-label small text-secondary mb-1">Nama Profile</label>
                                    <input type="text" id="newVlanName" class="form-control form-control-sm" placeholder="ppp-vlan150">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small text-secondary mb-1">VLAN ID</label>
                                    <input type="number" id="newVlanId" class="form-control form-control-sm" placeholder="150" value="150">
                                </div>
                                <div class="col-3">
                                    <button class="btn btn-google-primary btn-sm w-100" onclick="pushAddProfile('vlan')">
                                        <i class="bi bi-cloud-arrow-up me-1"></i> Push OLT
                                    </button>
                                </div>
                            </div>
                        </div>

                        <label class="form-label small fw-bold">Daftar ONU VLAN Profiles (Format: <code>nama_profile — VLAN ID</code>)</label>
                        <textarea id="txtVlanProfiles" class="form-control font-monospace" rows="5"
                                  placeholder="ppp-155 — VLAN 155&#10;ppp-160 — VLAN 160&#10;PPPOE — VLAN 100"><?= esc($olt['vlan_profiles'] ?? '') ?></textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="form-text">Profil VLAN yang dipakai saat registrasi PPPoE (mis. <code>ppp-155 — VLAN 155</code>).</div>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" id="delVlanName" class="form-control form-control-sm" style="width:140px" placeholder="Nama Profile">
                                <button class="btn btn-google-secondary btn-sm text-danger" onclick="pushDeleteProfile('vlan')">
                                    <i class="bi bi-trash me-1"></i> Hapus dari OLT
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="profileLog" class="d-none mt-3">
                    <div class="alert alert-info py-2 px-3 small mb-0" id="profileLogMsg"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-google-secondary" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-google-primary" id="btnSaveProfiles" onclick="saveOltProfiles()">
                    <i class="bi bi-check-circle me-1"></i> Simpan Teks Manual
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Push ACS -->
<div class="modal fade" id="acsPushModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-3">
                <h6 class="modal-title fw-bold" style="font-family:'Google Sans',sans-serif;">
                    <i class="bi bi-cloud-arrow-up text-success me-1"></i> Push PPPoE ke ACS
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary mb-3" id="acsPushSn"></p>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Username PPPoE</label>
                    <input type="text" id="acsPushUser" class="form-control" placeholder="user@isp">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password PPPoE</label>
                    <input type="text" id="acsPushPass" class="form-control" placeholder="password">
                </div>
                <div id="acsPushResult" class="d-none small mt-2"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-google-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-google-primary btn-sm" id="btnAcsPush" onclick="doAcsPush()">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Push
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
                <h5 class="modal-title fw-bold" style="font-family:'Google Sans',sans-serif;">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Register ONU
                </h5>
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
                            <label class="form-label small fw-bold">Serial Number</label>
                            <input type="text" id="r_sn" name="sn" class="form-control font-monospace bg-light" readonly>
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-bold">Nama Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="PELANGGAN-001" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label small fw-bold">Tipe ONU <span class="text-danger">*</span></label>
                            <input type="text" name="onu_type" id="r_onu_type" class="form-control"
                                   placeholder="ALL-ONT" list="onuTypeList" required>
                            <datalist id="onuTypeList">
                                <?php foreach ($onu_types as $t): ?>
                                <option value="<?= esc($t) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <!-- VLAN + TCONT + Traffic Limit -->
                    <div class="border rounded-3 p-3 mb-3" style="background:#f8fafc">
                        <div class="small fw-bold text-secondary mb-2">
                            <i class="bi bi-diagram-3 me-1"></i> Konfigurasi Service Port (gpon-onu interface)
                        </div>
                        <div class="row g-3">
                            <div class="col-4">
                                <label class="form-label small fw-bold">VLAN Internet (PPPoE)</label>
                                <?php if (strtoupper($olt['brand'] ?? '') === 'ZTE'): ?>
                                <select name="vlan_internet" id="vlanInternetSelect" class="form-select form-select-sm">
                                    <option value="">-- Pilih VLAN PPPoE --</option>
                                </select>
                                <input type="hidden" name="pppoe_vlan_profile" id="pppoeVlanProfile">
                                <?php else: ?>
                                <input type="number" name="vlan_internet" class="form-control form-control-sm"
                                       placeholder="155" min="1" max="4094">
                                <?php endif; ?>
                                <div class="form-text">service-port 2 vport 1</div>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-bold">VLAN ACS/Mgmt</label>
                                <input type="number" name="vlan_acs" class="form-control form-control-sm"
                                       placeholder="155" min="1" max="4094">
                                <div class="form-text">service-port 1 vport 1</div>
                            </div>
                            <div class="col-4">
                                <label class="form-label small fw-bold">TCONT Profile</label>
                                <select name="tcont_profile" id="tcontSelect" class="form-select form-select-sm">
                                    <option value="">-- Pilih TCONT --</option>
                                    <?php
                                    $tconts = array_values(array_filter(array_map('trim', explode("\n", $olt['tcont_profiles'] ?? ''))));
                                    foreach ($tconts as $tc):
                                    ?>
                                        <option value="<?= esc($tc) ?>"><?= esc($tc) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- PPPoE Credentials -->
                    <div class="border rounded-3 p-3 mb-3" style="background:#f0fdf4; border-color:#bbf7d0 !important">
                        <div class="small fw-bold text-success mb-2">
                            <i class="bi bi-shield-lock me-1"></i> PPPoE Credentials
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Username PPPoE</label>
                                <input type="text" name="pppoe_user" class="form-control form-control-sm"
                                       placeholder="user@isp">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Password PPPoE</label>
                                <input type="text" name="pppoe_pass" class="form-control form-control-sm"
                                       placeholder="password">
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="form-text text-success">
                                <?php if (strtoupper($olt['brand'] ?? '') === 'ZTE'): ?>
                                <i class="bi bi-info-circle me-1"></i>ZTE: PPPoE dikonfigurasi langsung via <strong>OLT pon-onu-mng (OMCI)</strong> saat registrasi.
                                <?php else: ?>
                                <i class="bi bi-info-circle me-1"></i>Jika diisi, PPPoE dikonfigurasi via <strong>GenieACS/TR-069</strong>.
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Template tambahan -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Template Script Tambahan <span class="text-muted fw-normal">(opsional)</span></label>
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
                    </div>

                    <div id="registerLog" class="d-none mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-secondary fw-bold" id="registerLogLabel">Preview CLI</small>
                            <button type="button" class="btn-close btn-sm" onclick="document.getElementById('registerLog').classList.add('d-none')"></button>
                        </div>
                        <pre class="cli-output" id="registerLogContent"></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-google-secondary me-auto" onclick="previewCli()"
                            title="Lihat perintah CLI yang akan dikirim ke OLT">
                        <i class="bi bi-terminal me-1"></i> Preview CLI
                    </button>
                    <button type="button" class="btn btn-google-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-google-primary" id="btnRegister">
                        <i class="bi bi-check-circle me-1"></i> Register
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Floating ACS Watcher -->
<div id="acsWatcher" class="d-none position-fixed" style="bottom:1.5rem;right:1.5rem;z-index:1055;min-width:300px;max-width:360px">
    <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
        <div class="card-header py-2 px-3 d-flex align-items-center gap-2" style="background:#202124;color:#fff">
            <span class="spinner-border spinner-border-sm text-primary flex-shrink-0" id="acsWatchSpinner"></span>
            <span class="small fw-bold flex-grow-1">Konfigurasi ACS</span>
            <button type="button" class="btn-close btn-close-white" style="font-size:.65rem" onclick="stopAcsWatch()"></button>
        </div>
        <div class="card-body py-3 px-3">
            <div class="font-monospace small fw-bold text-dark" id="acsWatchSn"></div>
            <div class="small text-secondary mt-1" id="acsWatchMsg">Memulai pemantauan...</div>
            <div class="mt-2 d-none" id="acsWatchActions">
                <button class="btn btn-sm btn-google-primary" onclick="retryAcsPush()">
                    <i class="bi bi-arrow-repeat me-1"></i> Push Ulang
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const OLT_ID  = <?= $olt['id'] ?>;
const IS_FH   = <?= strtoupper($olt['brand'] ?? '') === 'FIBERHOME' ? 'true' : 'false' ?>;
const USE_ACS = <?= ($olt['use_acs'] ?? 1) == 1 ? 'true' : 'false' ?>;

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
                        oltCell.innerHTML = '<span class="chip chip-warning">Tidak di cache</span>';
                    } else {
                        const st  = (info.status || '').toLowerCase();
                        const cls = st === 'working' ? 'chip-success'
                                  : st === 'los'     ? 'chip-danger'
                                  : st === 'lofi'    ? 'chip-warning'
                                  : 'chip-neutral';
                        oltCell.innerHTML = `<span class="chip ${cls}">${info.status || st}</span>`;
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
                            ? `<span class="chip chip-success"><i class="bi bi-wifi me-1"></i>Online</span>`
                            : `<span class="chip chip-neutral"><i class="bi bi-wifi-off me-1"></i>Offline ${lastInf}</span>`;
                        const model = acsInfo.model ? `<div class="small text-muted">${acsInfo.model}</div>` : '';
                        acsCell.innerHTML = badge + model;
                    } else if (hasAcsCache) {
                        acsCell.innerHTML = '<span class="chip chip-neutral">Tidak di ACS</span>';
                    }
                }
            });

            if (hasAcsCache && data.acs_updated_at) {
                const ct = document.getElementById('cacheTime');
                if (ct) ct.title = `OLT: ${data.updated_at} | ACS: ${data.acs_updated_at}`;
            }
        })
        .catch(() => {});
}

function refreshCache() {
    const btn = document.getElementById('btnRefreshCache');
    if (!confirm('Sync cache dari OLT?\n\nProses ini akan kirim beberapa perintah ke OLT (1 per port aktif).')) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sync...';

    fetch(`/olts/${OLT_ID}/refresh-cache`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1 text-warning"></i> Sync Cache';

            if (!data.success) {
                alert('Gagal: ' + data.message);
                return;
            }

            const ct = document.getElementById('cacheTime');
            const now = new Date().toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'});
            ct.innerHTML = `<i class="bi bi-database me-1"></i>Cache: ${now} (${data.count} ONU)`;

            document.querySelector('.alert-warning')?.remove();
            loadOltState();

            alert(data.message || `Cache berhasil diperbarui. ${data.count} ONU.`);
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1 text-warning"></i> Sync Cache';
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
            btn.innerHTML = '<i class="bi bi-download me-1 text-info"></i> Import ke DB';
            if (data.success) {
                alert(data.message);
                if (data.imported > 0) location.reload();
            } else {
                alert('Gagal: ' + data.message);
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-download me-1 text-info"></i> Import ke DB';
            alert('Error: ' + e.message);
        });
}

// ── Kelola Profil OLT ──────────────────────────────────────────
function openOltProfilesModal() {
    document.getElementById('profileLog').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('oltProfilesModal')).show();
}

function syncProfilesFromOlt() {
    const btn = document.getElementById('btnSyncProfiles');
    const log = document.getElementById('profileLog');
    const msg = document.getElementById('profileLogMsg');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menarik dari OLT...';

    const fd = new FormData();
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch(`/olts/${OLT_ID}/sync-profiles`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i> Tarik dari OLT';

            log.classList.remove('d-none');
            if (data.success) {
                msg.className = 'alert alert-success py-2 px-3 small';
                msg.textContent = data.message;
                if (data.tcont_profiles)   document.getElementById('txtTcontProfiles').value   = data.tcont_profiles.join('\n');
                if (data.traffic_profiles) document.getElementById('txtTrafficProfiles').value = data.traffic_profiles.join('\n');
                if (data.vlan_profiles)    document.getElementById('txtVlanProfiles').value    = data.vlan_profiles.join('\n');
                _vlanProfiles = null; // reset cache vlan
            } else {
                msg.className = 'alert alert-danger py-2 px-3 small';
                msg.textContent = 'Gagal: ' + data.message;
            }
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-download me-1"></i> Tarik dari OLT';
            log.classList.remove('d-none');
            msg.className = 'alert alert-danger py-2 px-3 small';
            msg.textContent = 'Error: ' + e.message;
        });
}

function saveOltProfiles() {
    const btn = document.getElementById('btnSaveProfiles');
    const log = document.getElementById('profileLog');
    const msg = document.getElementById('profileLogMsg');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

    const fd = new FormData();
    fd.append('tcont_profiles',   document.getElementById('txtTcontProfiles').value);
    fd.append('traffic_profiles', document.getElementById('txtTrafficProfiles').value);
    fd.append('vlan_profiles',    document.getElementById('txtVlanProfiles').value);
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch(`/olts/${OLT_ID}/save-profiles`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Simpan Profil';

            log.classList.remove('d-none');
            msg.className = 'alert alert-success py-2 px-3 small';
            msg.textContent = data.message;
            _vlanProfiles = null;
            setTimeout(() => bootstrap.Modal.getInstance(document.getElementById('oltProfilesModal'))?.hide(), 1200);
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Simpan Teks Manual';
            log.classList.remove('d-none');
            msg.className = 'alert alert-danger py-2 px-3 small';
            msg.textContent = 'Error: ' + e.message;
        });
}

function pushAddProfile(type) {
    let name = '', param = 0;
    if (type === 'tcont') {
        name  = document.getElementById('newTcontName').value.trim();
        param = parseInt(document.getElementById('newTcontBw').value) || 512000;
    } else if (type === 'traffic') {
        name  = document.getElementById('newTrafficName').value.trim();
        param = parseInt(document.getElementById('newTrafficSpeed').value) || 512000;
    } else if (type === 'vlan') {
        name  = document.getElementById('newVlanName').value.trim();
        param = parseInt(document.getElementById('newVlanId').value) || 150;
    }

    if (!name) {
        alert('Nama profile wajib diisi!');
        return;
    }

    const log = document.getElementById('profileLog');
    const msg = document.getElementById('profileLogMsg');
    log.classList.remove('d-none');
    msg.className = 'alert alert-info py-2 px-3 small';
    msg.textContent = `Mengirim perintah CLI ke OLT untuk membuat profile '${name}'...`;

    const fd = new FormData();
    fd.append('type', type);
    fd.append('name', name);
    fd.append('param', param);
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch(`/olts/${OLT_ID}/add-profile`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                msg.className = 'alert alert-success py-2 px-3 small';
                msg.textContent = data.message;
                syncProfilesFromOlt();
            } else {
                msg.className = 'alert alert-danger py-2 px-3 small';
                msg.textContent = 'Gagal: ' + data.message;
            }
        })
        .catch(e => {
            msg.className = 'alert alert-danger py-2 px-3 small';
            msg.textContent = 'Error: ' + e.message;
        });
}

function pushDeleteProfile(type) {
    let name = '';
    if (type === 'tcont')   name = document.getElementById('delTcontName').value.trim();
    if (type === 'traffic') name = document.getElementById('delTrafficName').value.trim();
    if (type === 'vlan')    name = document.getElementById('delVlanName').value.trim();

    if (!name) {
        alert('Masukkan nama profile yang ingin dihapus dari OLT!');
        return;
    }

    if (!confirm(`Hapus profile '${name}' dari OLT?\nPerintah 'no ...' akan dikirim langsung ke OLT via Telnet.`)) return;

    const log = document.getElementById('profileLog');
    const msg = document.getElementById('profileLogMsg');
    log.classList.remove('d-none');
    msg.className = 'alert alert-info py-2 px-3 small';
    msg.textContent = `Mengirim perintah CLI ke OLT untuk menghapus profile '${name}'...`;

    const fd = new FormData();
    fd.append('type', type);
    fd.append('name', name);
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch(`/olts/${OLT_ID}/delete-profile`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                msg.className = 'alert alert-success py-2 px-3 small';
                msg.textContent = data.message;
                syncProfilesFromOlt();
            } else {
                msg.className = 'alert alert-danger py-2 px-3 small';
                msg.textContent = 'Gagal: ' + data.message;
            }
        })
        .catch(e => {
            msg.className = 'alert alert-danger py-2 px-3 small';
            msg.textContent = 'Error: ' + e.message;
        });
}

function scanOnu() {
    const btn = document.getElementById('btnScan');
    const container = document.getElementById('uncfgContainer');
    const countBadge = document.getElementById('uncfgCount');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scanning...';
    container.innerHTML = '<div class="text-center py-4 text-secondary"><span class="spinner-border spinner-border-sm me-2"></span>Menghubungi OLT, mohon tunggu...</div>';

    fetch(`/olts/${OLT_ID}/scan`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Scan ONU Baru';

            if (!data.success) {
                container.innerHTML = `<div class="alert alert-danger rounded-3 m-3">${data.message}</div>`;
                countBadge.textContent = '!';
                return;
            }

            countBadge.textContent = data.count;

            if (data.cache_updated_at) {
                const ts = new Date(data.cache_updated_at.replace(' ', 'T'));
                const ct = document.getElementById('cacheTime');
                if (ct) ct.innerHTML = `<i class="bi bi-clock me-1"></i>Cache: ${ts.toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'})}`;
            }

            loadOltState();

            const warnEl = document.getElementById('scanWarning');
            if (data.no_cache_warning) {
                warnEl.className = '';
                warnEl.innerHTML = `<div class="alert alert-info border-0 rounded-0 border-bottom py-2 px-3 mb-0 small">
                    <i class="bi bi-info-circle-fill me-1"></i><strong>Cache OLT belum di-sync.</strong>
                    Index ONU otomatis ditentukan dari database. Disarankan klik <strong>Sync Cache</strong> jika OLT ini sudah memiliki ONU aktif sebelum aplikasi ini dipasang.
                </div>`;
            } else {
                warnEl.className = 'd-none';
                warnEl.innerHTML = '';
            }

            if (data.count === 0) {
                container.innerHTML = '<div class="text-center py-4 text-secondary small">Tidak ada ONU baru yang belum dikonfigurasi.</div>';
                return;
            }

            let rows = data.onus.map(o => {
                const portLabel = `${o.board}/${o.slot}/${o.port}`;
                const nextIdx   = o.next_index ?? 1;
                const badge = o.already_registered
                    ? `<div class="d-flex gap-1 align-items-center">
                         <span class="chip chip-neutral">Sudah di DB</span>
                         ${o.existing_id ? `<a href="/onus/${o.existing_id}" class="btn btn-sm btn-google-secondary py-0 px-2" title="Lihat ONU"><i class="bi bi-eye"></i></a>` : ''}
                         <button class="btn btn-sm btn-google-secondary py-0 px-2" title="Konfigurasi ulang ke OLT"
                             onclick="openRegister('${o.sn}','${o.board}','${o.slot}','${o.port}',${nextIdx},true,'${o.onu_type||''}')">
                             <i class="bi bi-arrow-repeat text-warning me-1"></i>Konfigurasi Ulang
                         </button>
                       </div>`
                    : `<button class="btn btn-sm btn-google-primary py-1 px-3" onclick="openRegister('${o.sn}','${o.board}','${o.slot}','${o.port}',${nextIdx},false,'${o.onu_type||''}')">
                         <i class="bi bi-plus me-1"></i>Register (idx ${nextIdx})
                       </button>`;
                const typeCell = o.onu_type
                    ? `<span class="chip chip-neutral">${o.onu_type}</span>`
                    : '<span class="text-muted small">-</span>';
                return `<tr>
                    <td class="font-monospace small fw-bold">${o.sn}</td>
                    <td class="small text-secondary font-monospace">${portLabel}</td>
                    <td>${typeCell}</td>
                    <td><span class="chip chip-warning">${o.state ?? '-'}</span></td>
                    <td>${badge}</td>
                </tr>`;
            }).join('');

            container.innerHTML = `<div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr><th class="ps-4">Serial Number</th><th>Port</th><th>Tipe</th><th>State</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-search me-1"></i> Scan ONU Baru';
            container.innerHTML = `<div class="alert alert-danger m-3">Error: ${e.message}</div>`;
        });
}

function openRegister(sn, board, slot, port, idx, force = false, onuType = '') {
    document.getElementById('r_sn').value    = sn;
    document.getElementById('r_board').value = board;
    document.getElementById('r_slot').value  = slot;
    document.getElementById('r_port').value  = port;
    document.getElementById('r_onu_index').value = idx;
    document.getElementById('r_force').value     = force ? '1' : '0';
    document.getElementById('registerLog').classList.add('d-none');
    document.getElementById('registerLogContent').textContent = '';

    const title = document.querySelector('#registerModal .modal-title');
    title.innerHTML = force
        ? '<i class="bi bi-arrow-repeat me-1 text-warning"></i>Konfigurasi Ulang ONU'
        : '<i class="bi bi-plus-circle me-1 text-primary"></i>Register ONU';

    document.querySelector('[name="name"]').value          = '';
    document.querySelector('[name="onu_type"]').value      = onuType || (IS_FH ? '' : 'ALL-ONT');
    const vlanEl = document.querySelector('[name="vlan_internet"]');
    if (vlanEl) vlanEl.value = '';
    document.querySelector('[name="vlan_acs"]').value      = '155';
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
    const pppoeP = document.querySelector('[name="pppoe_pass"]').value.trim();

    if (IS_FH) {
        const isFhOnu = /^FH(TT|SC)/i.test(sn);
        const dname = (name || '').trim().replace(/\s+/g, '-').replace(/[^A-Za-z0-9._-]/g, '') || '<NAMA>';
        let f = `! ══ Fiberhome AN6000 CLI Preview ══\n`;
        f += `config\n`;
        f += `whitelist add phy-id ${sn} type ${type || '<TIPE_ONU>'} slot ${slot} pon ${port} onuid ${idx}\n`;
        f += `interface pon ${board}/${slot}/${port}\n`;
        f += `  onu description ${idx} ${dname} id 0\n`;
        if (isFhOnu) {
            let ind = 1;
            if (vlanA) { f += `  onu wan-cfg ${idx} index ${ind} mode tr069 type route ${vlanA} 65535 nat disable qos disable dsp dhcp entries 0\n`; ind++; }
            if (vlanI) {
                if (pppoeU && pppoeP) {
                    f += `  onu wan-cfg ${idx} index ${ind} mode internet type route ${vlanI} 65535 nat enable qos disable dsp pppoe pro disable ${pppoeU} ${pppoeP} null auto entries 6 fe1 fe2 fe3 fe4 ssid1 ssid5\n`;
                } else {
                    f += `  onu wan-cfg ${idx} index ${ind} mode internet type route ${vlanI} 65535 nat enable qos disable dsp dhcp entries 6 fe1 fe2 fe3 fe4 ssid1 ssid5\n`;
                }
            }
        } else {
            if (vlanA) f += `  onu veip ${idx} cvlan-id ${vlanA} cvlan-cos 65535 svlan-tpid 33024 svlan-vid ${vlanA} svlan-cos 65535\n`;
            if (vlanI) f += `  onu veip ${idx} cvlan-id ${vlanI} cvlan-cos 65535 svlan-tpid 33024 svlan-vid ${vlanI} svlan-cos 65535\n`;
        }
        f += `exit\n`;
        f += `save\n`;
        document.getElementById('registerLogLabel').textContent = 'Preview CLI (belum dikirim)';
        document.getElementById('registerLogContent').style.color = '#93c5fd';
        document.getElementById('registerLogContent').textContent = f;
        document.getElementById('registerLog').classList.remove('d-none');
        return;
    }

    let cli = `! ══ ZTE C320 CLI Preview (${USE_ACS ? 'ACS TR-069 Mode' : 'Standalone / Pure OMCI Mode'}) ══\n`;
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
    if (vlanA && USE_ACS) {
        cli += `  service-port ${spIdx} vport 1 user-vlan ${vlanA} vlan ${vlanA}\n`;
        spIdx++;
    }
    if (vlanI) {
        cli += `  service-port ${spIdx} vport 1 user-vlan ${vlanI} vlan ${vlanI}\n`;
    }
    cli += `exit\n`;
    cli += `pon-onu-mng gpon-onu_${board}/${slot}/${port}:${idx}\n`;
    if (vlanA && USE_ACS) {
        cli += `  service acs gemport 1 vlan ${vlanA}\n`;
        cli += `  ip-host 2 dhcp-enable enable\n`;
    }
    if (vlanI) {
        cli += `  service ppp gemport 1 vlan ${vlanI}\n`;
    }
    if (pppoeU) {
        cli += `  wan-ip 1 mode pppoe username ${pppoeU} password ${pppoeP || 'xxx'} host 1\n`;
    }
    cli += `exit\n`;
    cli += `write\n`;

    if (!USE_ACS) {
        cli += `\n! ACS / TR-069 Non-Aktif di OLT ini — registrasi murni OMCI PPPoE tanpa TR-069.`;
    }

    document.getElementById('registerLogLabel').textContent = 'Preview CLI (belum dikirim)';
    document.getElementById('registerLogContent').style.color = '#81c995';
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

            if (data.success && data.partial) {
                logEl.classList.remove('d-none');
                logLabel.textContent = 'Log OLT — ⚠ TIDAK LENGKAP';
                logContent.style.color = '#fcd34d';
                logContent.textContent = '⚠ ' + data.message + '\n\n'
                    + (data.warnings || []).map(w => '• ' + w).join('\n')
                    + '\n\n──── log ────\n' + (data.log ? data.log.join('\n') : '');
                setTimeout(() => location.reload(), 30000);
                return;
            }
            if (data.success) {
                logContent.style.color = '#81c995';
                const hasWarn = (data.log || []).some(l => l.includes('WARN') || l.includes('Error'));
                const delay = 3000;
                if (hasWarn) logContent.textContent += '\n\n⚠ Ada peringatan — cek log di atas.';
                setTimeout(() => location.reload(), delay);
            } else {
                logContent.style.color = '#f87171';
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
const ACS_MAX_ATTEMPT = 20;

function startAcsWatch(onuId, sn, user, pass, pushViaAcs = true) {
    if (_acsWatch.interval) clearInterval(_acsWatch.interval);
    Object.assign(_acsWatch, { interval: null, attempt: 0, onuId, sn, user, pass, pushViaAcs });

    document.getElementById('acsWatchSn').textContent = sn;
    _setWatchMsg('Menunggu ONU online di ACS...', false);
    document.getElementById('acsWatchSpinner').classList.remove('d-none');
    document.getElementById('acsWatchActions').classList.add('d-none');
    document.getElementById('acsWatcher').classList.remove('d-none');

    _pollAcs();
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
    el.className   = 'small mt-1 ' + (isSuccess ? 'text-success' : 'text-secondary');
}

// ── VLAN Profile Dropdown (ZTE & Dynamic Profiles) ─────────────
let _vlanProfiles = null;

function loadVlanProfiles() {
    const sel = document.getElementById('vlanInternetSelect');
    if (!sel) return;
    if (_vlanProfiles) { _populateVlanSelect(_vlanProfiles); return; }

    sel.innerHTML = '<option value="">Memuat dari OLT...</option>';
    fetch(`/olts/${OLT_ID}/vlan-profiles`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.profiles.length) {
                _vlanProfiles = data.profiles;
                _populateVlanSelect(data.profiles);
            } else {
                // Fallback ke vlan_profiles yang tersimpan di DB
                const dbVlans = `<?= esc($olt['vlan_profiles'] ?? '') ?>`;
                if (dbVlans) {
                    const parsed = [];
                    dbVlans.split('\n').forEach(line => {
                        line = line.trim();
                        if (line) {
                            const parts = line.split(/—|:|-/);
                            const pName = parts[0]?.trim() || line;
                            const vId   = parseInt((line.match(/\d+/) || [155])[0]);
                            parsed.push({ name: pName, vlan: vId });
                        }
                    });
                    _vlanProfiles = parsed;
                    _populateVlanSelect(parsed);
                } else {
                    sel.innerHTML = '<option value="">-- Isi manual --</option>';
                    sel.outerHTML = '<input type="number" name="vlan_internet" id="vlanInternetFallback" class="form-control form-control-sm" placeholder="155" min="1" max="4094">';
                }
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
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memuat...';

    fetch(`/olts/${OLT_ID}/acs-status`)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-check me-1 text-info"></i> Cek ACS';

            if (!data.success) {
                alert('ACS: ' + data.message);
                return;
            }

            document.querySelectorAll('tr[data-sn]').forEach(row => {
                const sn   = row.dataset.sn;
                const cell = row.querySelector('.acs-cell');
                const info = data.data[sn];

                if (!cell) return;

                if (!info) {
                    cell.innerHTML = '<span class="chip chip-neutral">Tidak di ACS</span>';
                    return;
                }

                const online  = info.online;
                const lastInf = info.last_inform ? new Date(info.last_inform).toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'}) : '?';
                const badge   = online
                    ? `<span class="chip chip-success"><i class="bi bi-wifi me-1"></i>Online</span>`
                    : `<span class="chip chip-neutral"><i class="bi bi-wifi-off me-1"></i>Offline ${lastInf}</span>`;
                const model = info.model ? `<div class="small text-muted">${info.model}</div>` : '';
                cell.innerHTML = badge + model;
            });
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-check me-1 text-info"></i> Cek ACS';
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
            btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Push';
            res.className = 'small mt-2 ' + (data.success ? 'text-success' : 'text-danger');
            res.textContent = data.success ? 'PPPoE berhasil dipush ke ONU.' : (data.message || 'Gagal.');
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i> Push';
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
