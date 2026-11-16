<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Page Header -->
<div class="flex items-center gap-3 flex-wrap">
    <a href="/olts" class="btn btn-secondary btn-sm btn-icon-only" title="Kembali">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
    </a>
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-lg font-bold text-slate-900"><?= esc($olt['name']) ?></span>
        <span class="chip chip-info font-mono"><?= esc($olt['brand']) ?></span>
        <span class="chip chip-neutral font-mono"><?= esc($olt['model']) ?></span>
        <?php if (($olt['use_acs'] ?? 1) == 1): ?>
            <span class="chip chip-success" title="OLT terhubung dengan ACS/TR-069"><i data-lucide="wifi" class="w-3 h-3"></i> ACS Aktif</span>
        <?php else: ?>
            <span class="chip chip-neutral" title="OLT Standalone / Pure OMCI tanpa ACS"><i data-lucide="hard-drive" class="w-3 h-3"></i> ACS Off (Pure OMCI)</span>
        <?php endif; ?>
        <span class="text-[11px] text-slate-400 font-mono flex items-center gap-1"><i data-lucide="git-merge" class="w-3 h-3"></i><?= esc($olt['ip']) ?>:<?= esc($olt['telnet_port']) ?></span>
    </div>

    <div class="ml-auto flex items-center gap-2 flex-wrap">
        <span class="text-[11px] text-slate-400 flex items-center gap-1" id="cacheTime">
            <i data-lucide="database" class="w-3.5 h-3.5"></i>
            <?php if ($cache_updated_at): ?>
                Cache: <?= date('d/m H:i', strtotime($cache_updated_at)) ?>
            <?php else: ?>
                <span class="chip chip-warning">Cache kosong</span>
            <?php endif; ?>
        </span>

        <button class="btn btn-danger btn-sm" onclick="checkOltAlarms()" title="Cek Alarm Aktif, Kipas (FAN), Power, & Card OLT">
            <i data-lucide="bell-ring" class="w-3.5 h-3.5"></i> Cek Alarm
        </button>

        <button class="btn btn-secondary btn-sm" onclick="openOltProfilesModal()" title="Kelola TCONT, Traffic Limit & VLAN Profile">
            <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 text-blue-600"></i> Profil OLT
        </button>

        <button class="btn btn-secondary btn-sm" id="btnRefreshCache" onclick="refreshCache()" title="Sync ulang data ONU terdaftar dari OLT">
            <i data-lucide="rotate-cw" class="w-3.5 h-3.5 text-amber-500"></i> Sync Cache
        </button>

        <?php if ($cache_updated_at): ?>
        <button class="btn btn-secondary btn-sm" id="btnImportCache" onclick="importFromCache()" title="Import semua ONU dari cache ke database">
            <i data-lucide="download" class="w-3.5 h-3.5 text-cyan-600"></i> Import ke DB
        </button>
        <?php endif; ?>

        <a href="/olts/<?= $olt['id'] ?>/edit" class="btn btn-secondary btn-sm">
            <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
        </a>
        <button class="btn btn-primary btn-sm" id="btnScan" onclick="scanOnu()">
            <i data-lucide="search" class="w-3.5 h-3.5"></i> Scan ONU Baru
        </button>
    </div>
</div>

<?php if (!$cache_updated_at): ?>
<div id="cacheBanner" class="p-3.5 bg-blue-50 border border-blue-100 text-blue-800 rounded-xl text-xs flex items-center gap-2 shadow-xs">
    <i data-lucide="info" class="w-4 h-4 text-blue-500 shrink-0"></i>
    <div>
        <strong>Cache OLT belum di-sync.</strong> Klik <strong>Sync Cache</strong> sekali untuk menarik daftar ONU dari OLT, atau klik <strong>Scan ONU Baru</strong> untuk langsung mendaftarkan ONU baru.
    </div>
</div>
<?php endif; ?>

<!-- ONU Belum Dikonfigurasi -->
<div class="card !mb-0">
    <div class="card-header">
        <div class="card-title">
            <i data-lucide="alert-circle" class="w-4 h-4 text-amber-500"></i>
            ONU Belum Dikonfigurasi
        </div>
        <span class="chip chip-warning font-mono" id="uncfgCount">-</span>
    </div>
    <div id="scanWarning" class="hidden"></div>
    <div id="uncfgContainer">
        <div class="empty-state !py-4 text-xs" id="uncfgEmpty">
            Klik "Scan ONU Baru" untuk deteksi ONU yang baru dicolok.
        </div>
    </div>
</div>

<!-- ONU Sudah Terdaftar -->
<div class="card !mb-0">
    <div class="card-header gap-2">
        <div class="card-title">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
            ONU Terdaftar di OLT Ini
        </div>
        <div class="flex items-center gap-2 flex-1 justify-end min-w-0">
            <div class="relative">
                <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="search" id="onuSearch" class="form-input !min-h-[34px] pl-8 text-xs" style="max-width:220px"
                       placeholder="Cari SN / Nama..." oninput="filterOnu(this.value)">
            </div>
            <span class="chip chip-success font-mono"><?= count($onus) ?></span>
            <button class="btn btn-secondary btn-sm" onclick="loadAcsStatus()" id="btnAcs" title="Cek status ACS/TR-069">
                <i data-lucide="cloud" class="w-3.5 h-3.5 text-cyan-600"></i> Cek ACS
            </button>
        </div>
    </div>
    <?php if (empty($onus)): ?>
        <div class="empty-state">
            <i data-lucide="inbox"></i>
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
    <!-- PON port overview (heatmap) -->
    <div class="px-4 pb-3 border-b border-slate-100">
        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">PON Port (klik untuk buka port)</div>
        <div class="pon-grid">
            <?php foreach ($onusByPort as $portKey => $portOnus): ?>
            <?php
                $cnt = count($portOnus);
                $load = $cnt >= 64 ? 'var(--signal-danger)' : ($cnt >= 48 ? 'var(--signal-warn)' : 'var(--primary)');
            ?>
            <div class="pon-port-box" title="PON <?= esc($portKey) ?> — <?= $cnt ?> ONU" onclick="openPonSection('pon-<?= esc(str_replace('/', '-', $portKey)) ?>')">
                <div class="pon-port-title"><?= esc($portKey) ?></div>
                <div class="pon-port-count" style="color:<?= $load ?>"><?= $cnt ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Per-port collapsible sections -->
    <div class="p-2" id="ponSections">
        <?php foreach ($onusByPort as $portKey => $portOnus): ?>
        <?php $portId = 'pon-' . str_replace('/', '-', $portKey); ?>
        <div class="border border-slate-200 rounded-xl mb-2 overflow-hidden pon-section" id="<?= $portId ?>" data-port="<?= esc($portKey) ?>">
            <button type="button" class="pon-toggle w-full flex items-center gap-2.5 px-4 py-3 bg-slate-50 hover:bg-slate-100 transition text-left" onclick="togglePon(this)">
                <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 transition-transform chevron"></i>
                <span class="font-mono font-bold text-sm text-slate-800">PON <?= esc($portKey) ?></span>
                <span class="chip chip-neutral font-mono pon-badge"><?= count($portOnus) ?></span>
            </button>
            <div class="pon-body hidden">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Serial Number</th>
                                <th>Nama Pelanggan</th>
                                <th class="text-center">Idx</th>
                                <th>Tipe ONU</th>
                                <th>State OLT</th>
                                <th>Status ACS</th>
                                <th>Sinyal</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($portOnus as $onu): ?>
                            <tr id="onu-row-<?= $onu['id'] ?>"
                                data-sn="<?= esc($onu['sn']) ?>"
                                data-name="<?= esc(strtolower($onu['name'] ?? '')) ?>"
                                data-pppoe="<?= esc($onu['pppoe_user'] ?? '') ?>"
                                data-port="<?= esc($portKey) ?>">
                                <td class="font-mono text-xs font-bold">
                                    <a href="/onus/<?= $onu['id'] ?>" class="text-blue-600 hover:underline"><?= esc($onu['sn']) ?></a>
                                </td>
                                <td class="text-xs font-medium"><?= esc($onu['name'] ?? '-') ?></td>
                                <td class="text-xs text-slate-400 text-center font-mono"><?= $onu['onu_index'] ?></td>
                                <td><span class="chip chip-neutral"><?= esc($onu['onu_type'] ?? '-') ?></span></td>
                                <td class="olt-state-cell"><span class="text-slate-300">—</span></td>
                                <td class="acs-cell"><span class="text-slate-300">—</span></td>
                                <td>
                                    <button class="btn btn-secondary btn-sm btn-icon-only !min-h-[28px]" title="Cek sinyal"
                                            onclick="getSignal(<?= $onu['id'] ?>, this)">
                                        <i data-lucide="activity" class="w-3.5 h-3.5"></i>
                                    </button>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <?php $pk = explode('/', $portKey); ?>
                                    <button class="btn btn-secondary btn-sm btn-icon-only !min-h-[28px]" title="Konfigurasi ulang ONU (VLAN / TCONT / PPPoE)"
                                            onclick="openRegister('<?= esc($onu['sn'], 'js') ?>','<?= $pk[0] ?>','<?= $pk[1] ?>','<?= $pk[2] ?>',<?= $onu['onu_index'] ?>,true,'<?= esc($onu['onu_type'] ?? '', 'js') ?>')">
                                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5 text-amber-500"></i>
                                    </button>
                                    <button class="btn btn-secondary btn-sm btn-icon-only !min-h-[28px] ml-1" title="Push PPPoE ke ONU"
                                            onclick="openAcsPush(<?= $onu['id'] ?>, '<?= esc($onu['sn'], 'js') ?>', '<?= esc($onu['pppoe_user'] ?? '', 'js') ?>')">
                                        <i data-lucide="cloud-upload" class="w-3.5 h-3.5 text-emerald-600"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-icon-only !min-h-[28px] ml-1" title="Hapus ONU dari OLT"
                                            onclick="deleteOnu(<?= $onu['id'] ?>, '<?= esc($onu['sn'], 'js') ?>', this)">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Kelola Profil OLT -->
<div id="oltProfilesModal" class="modal-overlay">
    <div class="modal-card" style="max-width:760px">
        <div class="card-header !mb-3">
            <div class="card-title"><i data-lucide="sliders-horizontal" class="w-4 h-4 text-blue-600"></i> Manajemen Profil OLT (<?= esc($olt['name']) ?>)</div>
            <button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="App.closeModal('oltProfilesModal')">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="flex items-center justify-between mb-3 bg-slate-50 border border-slate-200 p-3 rounded-xl gap-3 flex-wrap">
            <div class="text-xs text-slate-500">
                <i data-lucide="info" class="w-3.5 h-3.5 inline"></i> Profil ini digunakan saat registrasi ONU &amp; setting service-port di OLT.
            </div>
            <button class="btn btn-primary btn-sm" id="btnSyncProfiles" onclick="syncProfilesFromOlt()">
                <i data-lucide="cloud-download" class="w-3.5 h-3.5"></i> Tarik dari OLT
            </button>
        </div>

        <div class="flex gap-2 mb-3 flex-wrap" id="profileTabs">
            <button type="button" class="tab-pill active" data-tab-group="profile" data-tab-target="tabTcont"><i data-lucide="gauge" class="w-3.5 h-3.5"></i> TCONT</button>
            <button type="button" class="tab-pill" data-tab-group="profile" data-tab-target="tabTraffic"><i data-lucide="zap" class="w-3.5 h-3.5"></i> Traffic Limit</button>
            <button type="button" class="tab-pill" data-tab-group="profile" data-tab-target="tabVlan"><i data-lucide="git-branch" class="w-3.5 h-3.5"></i> VLAN (PPPoE)</button>
        </div>

        <div id="profileTabContents">
            <!-- Tab TCONT -->
            <div data-tab-group="profile" data-tab-pane="tabTcont">
                <div class="border border-slate-200 rounded-xl p-3 mb-3 bg-slate-50">
                    <div class="text-xs font-bold text-slate-700 mb-2"><i data-lucide="plus-circle" class="w-3.5 h-3.5 inline text-blue-600"></i> Tambah TCONT Profile Baru ke OLT (CLI)</div>
                    <div class="form-grid" style="grid-template-columns:2fr 2fr 1fr;align-items:end">
                        <div class="form-group">
                            <label class="form-label">Nama Profile</label>
                            <input type="text" id="newTcontName" class="form-input" placeholder="500M">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max BW (Kbps)</label>
                            <input type="number" id="newTcontBw" class="form-input" placeholder="512000" value="512000">
                        </div>
                        <div class="form-group">
                            <button class="btn btn-primary btn-sm w-full" onclick="pushAddProfile('tcont')">
                                <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Push
                            </button>
                        </div>
                    </div>
                </div>

                <label class="form-label">Daftar TCONT Profiles di Database/OLT (satu per baris)</label>
                <textarea id="txtTcontProfiles" class="form-textarea font-mono text-xs" rows="5" placeholder="250M&#10;100M&#10;50M"><?= esc($olt['tcont_profiles'] ?? '') ?></textarea>
                <div class="flex justify-between items-center mt-2 flex-wrap gap-2">
                    <div class="text-[11px] text-slate-400">Profil DBA di OLT. Hapus profile dari OLT via CLI:</div>
                    <div class="flex gap-2 items-center">
                        <input type="text" id="delTcontName" class="form-input !min-h-[32px]" style="width:150px" placeholder="Nama Profile">
                        <button class="btn btn-danger btn-sm" onclick="pushDeleteProfile('tcont')">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus dari OLT
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Traffic -->
            <div data-tab-group="profile" data-tab-pane="tabTraffic" class="hidden">
                <div class="border border-slate-200 rounded-xl p-3 mb-3 bg-slate-50">
                    <div class="text-xs font-bold text-slate-700 mb-2"><i data-lucide="plus-circle" class="w-3.5 h-3.5 inline text-blue-600"></i> Tambah Traffic Limit Profile Baru ke OLT (CLI)</div>
                    <div class="form-grid" style="grid-template-columns:2fr 2fr 1fr;align-items:end">
                        <div class="form-group">
                            <label class="form-label">Nama Profile</label>
                            <input type="text" id="newTrafficName" class="form-input" placeholder="500M">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Speed SIR/PIR (Kbps)</label>
                            <input type="number" id="newTrafficSpeed" class="form-input" placeholder="512000" value="512000">
                        </div>
                        <div class="form-group">
                            <button class="btn btn-primary btn-sm w-full" onclick="pushAddProfile('traffic')">
                                <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Push
                            </button>
                        </div>
                    </div>
                </div>

                <label class="form-label">Daftar Traffic Limit Profiles di Database/OLT (satu per baris)</label>
                <textarea id="txtTrafficProfiles" class="form-textarea font-mono text-xs" rows="5" placeholder="50M&#10;100M&#10;250M"><?= esc($olt['traffic_profiles'] ?? '') ?></textarea>
                <div class="flex justify-between items-center mt-2 flex-wrap gap-2">
                    <div class="text-[11px] text-slate-400">Batas kecepatan Upstream/Downstream gemport.</div>
                    <div class="flex gap-2 items-center">
                        <input type="text" id="delTrafficName" class="form-input !min-h-[32px]" style="width:150px" placeholder="Nama Profile">
                        <button class="btn btn-danger btn-sm" onclick="pushDeleteProfile('traffic')">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus dari OLT
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab VLAN -->
            <div data-tab-group="profile" data-tab-pane="tabVlan" class="hidden">
                <div class="border border-slate-200 rounded-xl p-3 mb-3 bg-slate-50">
                    <div class="text-xs font-bold text-slate-700 mb-2"><i data-lucide="plus-circle" class="w-3.5 h-3.5 inline text-blue-600"></i> Tambah ONU VLAN Profile Baru ke OLT (CLI)</div>
                    <div class="form-grid" style="grid-template-columns:2fr 2fr 1fr;align-items:end">
                        <div class="form-group">
                            <label class="form-label">Nama Profile</label>
                            <input type="text" id="newVlanName" class="form-input" placeholder="ppp-vlan150">
                        </div>
                        <div class="form-group">
                            <label class="form-label">VLAN ID</label>
                            <input type="number" id="newVlanId" class="form-input" placeholder="150" value="150">
                        </div>
                        <div class="form-group">
                            <button class="btn btn-primary btn-sm w-full" onclick="pushAddProfile('vlan')">
                                <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Push
                            </button>
                        </div>
                    </div>
                </div>

                <label class="form-label">Daftar ONU VLAN Profiles (format: <code class="font-mono bg-slate-100 px-1 rounded">nama_profile — VLAN ID</code>)</label>
                <textarea id="txtVlanProfiles" class="form-textarea font-mono text-xs" rows="5" placeholder="ppp-155 — VLAN 155&#10;ppp-160 — VLAN 160&#10;PPPOE — VLAN 100"><?= esc($olt['vlan_profiles'] ?? '') ?></textarea>
                <div class="flex justify-between items-center mt-2 flex-wrap gap-2">
                    <div class="text-[11px] text-slate-400">Profil VLAN yang dipakai saat registrasi PPPoE (mis. <code class="font-mono">ppp-155 — VLAN 155</code>).</div>
                    <div class="flex gap-2 items-center">
                        <input type="text" id="delVlanName" class="form-input !min-h-[32px]" style="width:150px" placeholder="Nama Profile">
                        <button class="btn btn-danger btn-sm" onclick="pushDeleteProfile('vlan')">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus dari OLT
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="profileLog" class="hidden mt-3">
            <div id="profileLogMsg"></div>
        </div>

        <div class="flex gap-2 mt-4 justify-end">
            <button type="button" class="btn btn-secondary" onclick="App.closeModal('oltProfilesModal')">Tutup</button>
            <button type="button" class="btn btn-primary" id="btnSaveProfiles" onclick="saveOltProfiles()">
                <i data-lucide="check-circle" class="w-4 h-4"></i> Simpan Teks Manual
            </button>
        </div>
    </div>
</div>

<!-- Modal Push ACS -->
<div id="acsPushModal" class="modal-overlay">
    <div class="modal-card" style="max-width:380px">
        <div class="card-header !mb-3">
            <div class="card-title"><i data-lucide="cloud-upload" class="w-4 h-4 text-emerald-600"></i> Push PPPoE ke ACS</div>
            <button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="App.closeModal('acsPushModal')">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div>
            <p class="text-xs text-slate-400 font-mono mb-3" id="acsPushSn"></p>
            <div class="form-group">
                <label class="form-label">Username PPPoE</label>
                <input type="text" id="acsPushUser" class="form-input font-mono" placeholder="user@isp">
            </div>
            <div class="form-group">
                <label class="form-label">Password PPPoE</label>
                <input type="text" id="acsPushPass" class="form-input font-mono" placeholder="password">
            </div>
            <div id="acsPushResult" class="hidden"></div>
        </div>
        <div class="flex gap-2 justify-end mt-4">
            <button type="button" class="btn btn-secondary btn-sm" onclick="App.closeModal('acsPushModal')">Batal</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnAcsPush" onclick="doAcsPush()">
                <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Push
            </button>
        </div>
    </div>
</div>

<!-- Modal Register ONU -->
<div id="registerModal" class="modal-overlay">
    <div class="modal-card" style="max-width:720px">
        <div class="card-header !mb-3">
            <div class="card-title modal-title" id="registerModalTitle"><i data-lucide="plus-circle" class="w-4 h-4 text-blue-600"></i> Register ONU</div>
            <button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="App.closeModal('registerModal')">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form id="registerForm">
            <?= csrf_field() ?>
            <input type="hidden" id="r_board" name="board">
            <input type="hidden" id="r_slot" name="slot">
            <input type="hidden" id="r_port" name="port">
            <input type="hidden" id="r_onu_index" name="onu_index">
            <input type="hidden" id="r_force" name="force" value="0">

            <!-- SN + Info -->
            <div class="form-grid mb-3" style="grid-template-columns:5fr 4fr 3fr">
                <div class="form-group">
                    <label class="form-label">Serial Number</label>
                    <input type="text" id="r_sn" name="sn" class="form-input font-mono bg-slate-50" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Pelanggan <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" class="form-input" placeholder="PELANGGAN-001" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipe ONU <span class="text-rose-500">*</span></label>
                    <input type="text" name="onu_type" id="r_onu_type" class="form-input" placeholder="ALL-ONT" list="onuTypeList" required>
                    <datalist id="onuTypeList">
                        <?php foreach ($onu_types as $t): ?>
                        <option value="<?= esc($t) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <!-- VLAN + TCONT -->
            <div class="border border-slate-200 rounded-xl p-3 mb-3 bg-slate-50">
                <div class="text-xs font-bold text-slate-500 mb-2">
                    <i data-lucide="git-branch" class="w-3.5 h-3.5 inline"></i> Konfigurasi Service Port (gpon-onu interface)
                </div>
                <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr">
                    <div class="form-group">
                        <label class="form-label">VLAN Internet (PPPoE)</label>
                        <?php if (strtoupper($olt['brand'] ?? '') === 'ZTE'): ?>
                        <select name="vlan_internet" id="vlanInternetSelect" class="form-select">
                            <option value="">-- Pilih VLAN PPPoE --</option>
                        </select>
                        <input type="hidden" name="pppoe_vlan_profile" id="pppoeVlanProfile">
                        <?php else: ?>
                        <input type="number" name="vlan_internet" class="form-input" placeholder="155" min="1" max="4094">
                        <?php endif; ?>
                        <div class="text-[10px] text-slate-400 mt-0.5">service-port 2 vport 1</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">VLAN ACS/Mgmt</label>
                        <input type="number" name="vlan_acs" class="form-input" placeholder="155" min="1" max="4094">
                        <div class="text-[10px] text-slate-400 mt-0.5">service-port 1 vport 1</div>
                    </div>
                    <div class="form-group">
                        <?php $isZte = strtoupper($olt['brand'] ?? '') === 'ZTE'; ?>
                        <label class="form-label">TCONT Profile
                            <?php if ($isZte): ?><span class="text-rose-500">*</span><?php endif; ?>
                        </label>
                        <select name="tcont_profile" id="tcontSelect" class="form-select" <?= $isZte ? 'required' : '' ?>>
                            <option value="">-- Pilih TCONT --</option>
                            <?php
                            $tconts = array_values(array_filter(array_map('trim', explode("\n", $olt['tcont_profiles'] ?? ''))));
                            foreach ($tconts as $tc):
                            ?>
                                <option value="<?= esc($tc) ?>"><?= esc($tc) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isZte): ?>
                        <div class="text-[10px] text-slate-400 mt-0.5">Pilih profil <b>type 4 maximum</b>; <code>fixed</code>/<code>assured</code> mem-booking bandwidth per ONU.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PPPoE Credentials -->
            <div class="border border-emerald-200 rounded-xl p-3 mb-3 bg-emerald-50">
                <div class="text-xs font-bold text-emerald-700 mb-2">
                    <i data-lucide="shield" class="w-3.5 h-3.5 inline"></i> PPPoE Credentials
                </div>
                <div class="form-grid" style="grid-template-columns:1fr 1fr">
                    <div class="form-group">
                        <label class="form-label">Username PPPoE</label>
                        <input type="text" name="pppoe_user" class="form-input font-mono" placeholder="user@isp">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password PPPoE</label>
                        <input type="text" name="pppoe_pass" class="form-input font-mono" placeholder="password">
                    </div>
                </div>
                <div class="text-[11px] text-emerald-700 mt-1">
                    <?php if (strtoupper($olt['brand'] ?? '') === 'ZTE'): ?>
                    ZTE: PPPoE dikonfigurasi langsung via <strong>OLT pon-onu-mng (OMCI)</strong> saat registrasi.
                    <?php else: ?>
                    Jika diisi, PPPoE dikonfigurasi via <strong>GenieACS/TR-069</strong>.
                    <?php endif; ?>
                </div>
            </div>

            <!-- Template tambahan -->
            <div class="mb-3">
                <label class="form-label">Template Script Tambahan <span class="font-normal text-slate-400">(opsional)</span></label>
                <select name="template_id" class="form-select">
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

            <div id="registerLog" class="hidden mt-3">
                <div class="flex justify-between items-center mb-1">
                    <span class="text-[11px] font-bold text-slate-500" id="registerLogLabel">Preview CLI</span>
                    <button type="button" class="btn btn-secondary btn-sm !min-h-[24px] !min-w-[24px] !p-1" onclick="document.getElementById('registerLog').classList.add('hidden')">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>
                </div>
                <div class="terminal-window">
                    <div class="terminal-header">
                        <span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span>
                        <span class="text-slate-500 text-[10px] ml-2">olt-cli</span>
                    </div>
                    <pre class="terminal-body" id="registerLogContent" style="margin:0"></pre>
                </div>
            </div>

            <div class="flex gap-2 mt-4">
                <button type="button" class="btn btn-secondary" onclick="previewCli()" title="Lihat perintah CLI yang akan dikirim ke OLT">
                    <i data-lucide="terminal" class="w-4 h-4"></i> Preview CLI
                </button>
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('registerModal')">Batal</button>
                <button type="submit" class="btn btn-primary ml-auto" id="btnRegister">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Register
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cek Alarm OLT -->
<div id="alarmModal" class="modal-overlay">
    <div class="modal-card" style="max-width:900px">
        <div class="flex items-center justify-between mb-4 bg-rose-600 text-white -mx-5 -mt-5 px-5 py-3.5 rounded-t-xl">
            <div class="font-bold text-sm flex items-center gap-2">
                <i data-lucide="bell-ring" class="w-4 h-4"></i> Status Alarm Aktif &amp; Hardware OLT
            </div>
            <button type="button" class="text-white/80 hover:text-white" onclick="App.closeModal('alarmModal')">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <div id="alarmLoading" class="text-center py-10">
            <span class="spin spin-dark" style="width:28px;height:28px;border-width:3px"></span>
            <div class="text-xs text-slate-400 mt-3 font-medium">Menghubungi OLT via Telnet &amp; menarik status Alarm, Fan, Power, Card...</div>
        </div>

        <div id="alarmContent" class="hidden">
            <div class="flex gap-2 mb-3 flex-wrap">
                <button type="button" class="tab-pill active" data-tab-group="alarm" data-tab-target="tab-current"><i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i> Alarm Aktif</button>
                <button type="button" class="tab-pill" data-tab-group="alarm" data-tab-target="tab-fan"><i data-lucide="fan" class="w-3.5 h-3.5"></i> Kipas / FAN</button>
                <button type="button" class="tab-pill" data-tab-group="alarm" data-tab-target="tab-power"><i data-lucide="zap" class="w-3.5 h-3.5"></i> Power</button>
                <button type="button" class="tab-pill" data-tab-group="alarm" data-tab-target="tab-card"><i data-lucide="cpu" class="w-3.5 h-3.5"></i> Card / Slot</button>
                <button type="button" class="tab-pill" data-tab-group="alarm" data-tab-target="tab-env"><i data-lucide="thermometer" class="w-3.5 h-3.5"></i> Environment</button>
            </div>

            <div data-tab-group="alarm" data-tab-pane="tab-current">
                <div class="terminal-window"><div class="terminal-header"><span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span><span class="text-slate-500 text-[10px] ml-2">show alarm pool / current</span></div><pre class="terminal-body" id="alarmCurrentPre" style="max-height:380px;margin:0"></pre></div>
            </div>
            <div data-tab-group="alarm" data-tab-pane="tab-fan" class="hidden">
                <div class="terminal-window"><div class="terminal-header"><span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span><span class="text-slate-500 text-[10px] ml-2">show fan</span></div><pre class="terminal-body" id="alarmFanPre" style="max-height:380px;margin:0"></pre></div>
            </div>
            <div data-tab-group="alarm" data-tab-pane="tab-power" class="hidden">
                <div class="terminal-window"><div class="terminal-header"><span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span><span class="text-slate-500 text-[10px] ml-2">power / counter alarm</span></div><pre class="terminal-body" id="alarmPowerPre" style="max-height:380px;margin:0"></pre></div>
            </div>
            <div data-tab-group="alarm" data-tab-pane="tab-card" class="hidden">
                <div class="terminal-window"><div class="terminal-header"><span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span><span class="text-slate-500 text-[10px] ml-2">show card</span></div><pre class="terminal-body" id="alarmCardPre" style="max-height:380px;margin:0"></pre></div>
            </div>
            <div data-tab-group="alarm" data-tab-pane="tab-env" class="hidden">
                <div class="terminal-window"><div class="terminal-header"><span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span><span class="text-slate-500 text-[10px] ml-2">environment / suhu</span></div><pre class="terminal-body" id="alarmEnvPre" style="max-height:380px;margin:0"></pre></div>
            </div>

            <div class="flex justify-end mt-4">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('alarmModal')">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Floating ACS Watcher -->
<div id="acsWatcher" class="hidden fixed" style="bottom:1.5rem;right:1.5rem;z-index:1060;min-width:300px;max-width:360px">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl overflow-hidden">
        <div class="py-2.5 px-3.5 flex items-center gap-2 bg-slate-900 text-white">
            <span class="spin shrink-0" id="acsWatchSpinner"></span>
            <span class="text-xs font-bold flex-1">Konfigurasi ACS</span>
            <button type="button" class="text-white/70 hover:text-white" onclick="stopAcsWatch()">
                <i data-lucide="x" class="w-3.5 h-3.5"></i>
            </button>
        </div>
        <div class="py-3 px-3.5">
            <div class="font-mono text-xs font-bold text-slate-800" id="acsWatchSn"></div>
            <div class="text-xs text-slate-500 mt-1" id="acsWatchMsg">Memulai pemantauan...</div>
            <div class="mt-2 hidden" id="acsWatchActions">
                <button class="btn btn-primary btn-sm" onclick="retryAcsPush()">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Push Ulang
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

// ── Tab pills generik (profil OLT & alarm) ─────────────────────
document.addEventListener('click', (e) => {
    const tab = e.target.closest('[data-tab-target]');
    if (!tab) return;
    const group = tab.dataset.tabGroup;
    document.querySelectorAll(`[data-tab-group="${group}"][data-tab-target]`).forEach(b => {
        b.classList.toggle('active', b === tab);
    });
    document.querySelectorAll(`[data-tab-group="${group}"][data-tab-pane]`).forEach(p => {
        p.classList.toggle('hidden', p.dataset.tabPane !== tab.dataset.tabTarget);
    });
});

// ── PON section collapse ────────────────────────────────────────
function togglePon(btn) {
    const section = btn.closest('.pon-section');
    const body = section.querySelector('.pon-body');
    const chevron = btn.querySelector('.chevron');
    const open = body.classList.toggle('hidden');
    chevron.style.transform = open ? '' : 'rotate(90deg)';
}

function openPonSection(portId) {
    const section = document.getElementById(portId);
    if (!section) return;
    const body = section.querySelector('.pon-body');
    body.classList.remove('hidden');
    section.querySelector('.chevron').style.transform = 'rotate(90deg)';
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function loadOltState() {
    App.fetch(`/olts/${OLT_ID}/cache-data`)
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
                            ? `<span class="chip chip-success"><span class="pulsing-dot"></span> Online</span>`
                            : `<span class="chip chip-neutral">Offline ${lastInf}</span>`;
                        const model = acsInfo.model ? `<div class="text-[10px] text-slate-400">${App.escapeHtml(acsInfo.model)}</div>` : '';
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

async function refreshCache() {
    const btn = document.getElementById('btnRefreshCache');
    if (!confirm('Sync cache dari OLT?\n\nProses ini akan kirim beberapa perintah ke OLT (1 per port aktif).')) return;

    App.setLoading(btn, true, 'Sync...');
    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/refresh-cache`));
        App.setLoading(btn, false);
        if (!data.success) {
            App.showToast('Gagal: ' + data.message, 'error');
            return;
        }

        const ct = document.getElementById('cacheTime');
        const now = new Date().toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'});
        ct.innerHTML = `<i data-lucide="database" class="w-3.5 h-3.5"></i>Cache: ${now} (${data.count} ONU)`;
        App.refreshIcons();

        document.getElementById('cacheBanner')?.remove();
        loadOltState();

        App.showToast(data.message || `Cache berhasil diperbarui. ${data.count} ONU.`, 'success');
    } catch (e) {
        App.setLoading(btn, false);
        App.showToast('Error: ' + e.message, 'error');
    }
}

async function importFromCache() {
    if (!confirm('Import semua ONU dari cache ke database?\nONU yang sudah ada di DB akan di-skip.')) return;

    const btn = document.getElementById('btnImportCache');
    App.setLoading(btn, true, 'Import...');
    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/import-cache`, { method: 'POST' }));
        App.setLoading(btn, false);
        if (data.success) {
            App.showToast(data.message, 'success');
            if (data.imported > 0) setTimeout(() => location.reload(), 1500);
        } else {
            App.showToast('Gagal: ' + data.message, 'error');
        }
    } catch (e) {
        App.setLoading(btn, false);
        App.showToast('Error: ' + e.message, 'error');
    }
}

// ── Kelola Profil OLT ──────────────────────────────────────────
function openOltProfilesModal() {
    document.getElementById('profileLog').classList.add('hidden');
    App.openModal('oltProfilesModal');
}

async function syncProfilesFromOlt() {
    const btn = document.getElementById('btnSyncProfiles');
    const log = document.getElementById('profileLog');
    const msg = document.getElementById('profileLogMsg');

    App.setLoading(btn, true, 'Menarik...');
    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/sync-profiles`, { method: 'POST' }));
        App.setLoading(btn, false);
        log.classList.remove('hidden');
        if (data.success) {
            msg.className = 'p-2.5 rounded-lg text-xs bg-emerald-50 border border-emerald-200 text-emerald-700';
            msg.textContent = data.message;
            if (data.tcont_profiles)   document.getElementById('txtTcontProfiles').value   = data.tcont_profiles.join('\n');
            if (data.traffic_profiles) document.getElementById('txtTrafficProfiles').value = data.traffic_profiles.join('\n');
            if (data.vlan_profiles)    document.getElementById('txtVlanProfiles').value    = data.vlan_profiles.join('\n');
            _vlanProfiles = null; // reset cache vlan
        } else {
            msg.className = 'p-2.5 rounded-lg text-xs bg-rose-50 border border-rose-200 text-rose-700';
            msg.textContent = 'Gagal: ' + data.message;
        }
    } catch (e) {
        App.setLoading(btn, false);
        log.classList.remove('hidden');
        msg.className = 'p-2.5 rounded-lg text-xs bg-rose-50 border border-rose-200 text-rose-700';
        msg.textContent = 'Error: ' + e.message;
    }
}

async function saveOltProfiles() {
    const btn = document.getElementById('btnSaveProfiles');
    const log = document.getElementById('profileLog');
    const msg = document.getElementById('profileLogMsg');

    App.setLoading(btn, true, 'Menyimpan...');

    const fd = new FormData();
    fd.append('tcont_profiles',   document.getElementById('txtTcontProfiles').value);
    fd.append('traffic_profiles', document.getElementById('txtTrafficProfiles').value);
    fd.append('vlan_profiles',    document.getElementById('txtVlanProfiles').value);

    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/save-profiles`, { method: 'POST', body: fd }));
        App.setLoading(btn, false);
        log.classList.remove('hidden');
        msg.className = 'p-2.5 rounded-lg text-xs bg-emerald-50 border border-emerald-200 text-emerald-700';
        msg.textContent = data.message;
        _vlanProfiles = null;
        setTimeout(() => App.closeModal('oltProfilesModal'), 1200);
    } catch (e) {
        App.setLoading(btn, false);
        log.classList.remove('hidden');
        msg.className = 'p-2.5 rounded-lg text-xs bg-rose-50 border border-rose-200 text-rose-700';
        msg.textContent = 'Error: ' + e.message;
    }
}

function _profileMsg(type, text) {
    const log = document.getElementById('profileLog');
    const msg = document.getElementById('profileLogMsg');
    log.classList.remove('hidden');
    msg.className = `p-2.5 rounded-lg text-xs border ${type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : type === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-blue-50 border-blue-100 text-blue-700'}`;
    msg.textContent = text;
}

async function pushAddProfile(type) {
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
        App.showToast('Nama profile wajib diisi!', 'warning');
        return;
    }

    _profileMsg('info', `Mengirim perintah CLI ke OLT untuk membuat profile '${name}'...`);

    const fd = new FormData();
    fd.append('type', type);
    fd.append('name', name);
    fd.append('param', param);

    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/add-profile`, { method: 'POST', body: fd }));
        if (data.success) {
            _profileMsg('success', data.message);
            syncProfilesFromOlt();
        } else {
            _profileMsg('error', 'Gagal: ' + data.message);
        }
    } catch (e) {
        _profileMsg('error', 'Error: ' + e.message);
    }
}

async function pushDeleteProfile(type) {
    let name = '';
    if (type === 'tcont')   name = document.getElementById('delTcontName').value.trim();
    if (type === 'traffic') name = document.getElementById('delTrafficName').value.trim();
    if (type === 'vlan')    name = document.getElementById('delVlanName').value.trim();

    if (!name) {
        App.showToast('Masukkan nama profile yang ingin dihapus dari OLT!', 'warning');
        return;
    }

    if (!confirm(`Hapus profile '${name}' dari OLT?\nPerintah 'no ...' akan dikirim langsung ke OLT via Telnet.`)) return;

    _profileMsg('info', `Mengirim perintah CLI ke OLT untuk menghapus profile '${name}'...`);

    const fd = new FormData();
    fd.append('type', type);
    fd.append('name', name);

    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/delete-profile`, { method: 'POST', body: fd }));
        if (data.success) {
            _profileMsg('success', data.message);
            syncProfilesFromOlt();
        } else {
            _profileMsg('error', 'Gagal: ' + data.message);
        }
    } catch (e) {
        _profileMsg('error', 'Error: ' + e.message);
    }
}

async function scanOnu() {
    const btn = document.getElementById('btnScan');
    const container = document.getElementById('uncfgContainer');
    const countBadge = document.getElementById('uncfgCount');

    App.setLoading(btn, true, 'Scanning...');
    container.innerHTML = '<div class="flex items-center justify-center gap-2 py-6 text-xs text-slate-400"><span class="spin spin-dark"></span> Menghubungi OLT, mohon tunggu...</div>';

    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/scan`));
        App.setLoading(btn, false);

        if (!data.success) {
            container.innerHTML = `<div class="m-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs">${App.escapeHtml(data.message)}</div>`;
            countBadge.textContent = '!';
            return;
        }

        countBadge.textContent = data.count;

        if (data.cache_updated_at) {
            const ts = new Date(data.cache_updated_at.replace(' ', 'T'));
            const ct = document.getElementById('cacheTime');
            if (ct) ct.innerHTML = `<i data-lucide="clock" class="w-3.5 h-3.5"></i>Cache: ${ts.toLocaleTimeString('id', {hour:'2-digit',minute:'2-digit'})}`;
            App.refreshIcons();
        }

        loadOltState();

        const warnEl = document.getElementById('scanWarning');
        if (data.no_cache_warning) {
            warnEl.className = '';
            warnEl.innerHTML = `<div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 text-blue-800 text-xs flex items-start gap-2">
                <i data-lucide="info" class="w-3.5 h-3.5 text-blue-500 shrink-0 mt-0.5"></i>
                <span><strong>Cache OLT belum di-sync.</strong>
                Index ONU otomatis ditentukan dari database. Disarankan klik <strong>Sync Cache</strong> jika OLT ini sudah memiliki ONU aktif sebelum aplikasi ini dipasang.</span>
            </div>`;
            App.refreshIcons();
        } else {
            warnEl.className = 'hidden';
            warnEl.innerHTML = '';
        }

        if (data.count === 0) {
            container.innerHTML = '<div class="empty-state !py-5 text-xs">Tidak ada ONU baru yang belum dikonfigurasi.</div>';
            return;
        }

        const rows = data.onus.map(o => {
            const portLabel = `${o.board}/${o.slot}/${o.port}`;
            const nextIdx   = o.next_index ?? 1;
            const badge = o.already_registered
                ? `<div class="flex gap-1 items-center">
                     <span class="chip chip-neutral">Sudah di DB</span>
                     ${o.existing_id ? `<a href="/onus/${o.existing_id}" class="btn btn-secondary btn-sm btn-icon-only !min-h-[26px]" title="Lihat ONU"><i data-lucide="eye" class="w-3 h-3"></i></a>` : ''}
                     <button class="btn btn-secondary btn-sm !min-h-[26px]" title="Konfigurasi ulang ke OLT"
                         onclick="openRegister('${o.sn}','${o.board}','${o.slot}','${o.port}',${nextIdx},true,'${o.onu_type||''}')">
                         <i data-lucide="refresh-cw" class="w-3 h-3 text-amber-500"></i> Ulang
                     </button>
                   </div>`
                : `<button class="btn btn-primary btn-sm" onclick="openRegister('${o.sn}','${o.board}','${o.slot}','${o.port}',${nextIdx},false,'${o.onu_type||''}')">
                     <i data-lucide="plus" class="w-3 h-3"></i> Register (idx ${nextIdx})
                   </button>`;
            const typeCell = o.onu_type
                ? `<span class="chip chip-neutral">${App.escapeHtml(o.onu_type)}</span>`
                : '<span class="text-slate-300 text-xs">-</span>';
            return `<tr>
                <td class="font-mono text-xs font-bold">${App.escapeHtml(o.sn)}</td>
                <td class="text-xs text-slate-400 font-mono">${portLabel}</td>
                <td>${typeCell}</td>
                <td><span class="chip chip-warning">${App.escapeHtml(o.state ?? '-')}</span></td>
                <td>${badge}</td>
            </tr>`;
        }).join('');

        container.innerHTML = `<div class="table-container">
            <table class="data-table">
                <thead>
                    <tr><th>Serial Number</th><th>Port</th><th>Tipe</th><th>State</th><th>Aksi</th></tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
        App.refreshIcons();
    } catch (e) {
        App.setLoading(btn, false);
        container.innerHTML = `<div class="m-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs">Error: ${App.escapeHtml(e.message)}</div>`;
    }
}

function openRegister(sn, board, slot, port, idx, force = false, onuType = '') {
    document.getElementById('r_sn').value    = sn;
    document.getElementById('r_board').value = board;
    document.getElementById('r_slot').value  = slot;
    document.getElementById('r_port').value  = port;
    document.getElementById('r_onu_index').value = idx;
    document.getElementById('r_force').value     = force ? '1' : '0';
    document.getElementById('registerLog').classList.add('hidden');
    document.getElementById('registerLogContent').textContent = '';

    const title = document.getElementById('registerModalTitle');
    title.innerHTML = force
        ? '<i data-lucide="refresh-cw" class="w-4 h-4 text-amber-500"></i> Konfigurasi Ulang ONU'
        : '<i data-lucide="plus-circle" class="w-4 h-4 text-blue-600"></i> Register ONU';

    document.querySelector('#registerForm [name="name"]').value       = '';
    document.querySelector('#registerForm [name="onu_type"]').value   = onuType || (IS_FH ? '' : 'ALL-ONT');
    const vlanEl = document.querySelector('#registerForm [name="vlan_internet"]');
    if (vlanEl) vlanEl.value = '';
    document.querySelector('#registerForm [name="vlan_acs"]').value   = '155';
    const tcontEl = document.querySelector('#registerForm [name="tcont_profile"]');
    if (tcontEl) tcontEl.value = '';
    document.querySelector('#registerForm [name="pppoe_user"]').value = '';
    document.querySelector('#registerForm [name="pppoe_pass"]').value = '';

    App.openModal('registerModal');
    App.refreshIcons();
    loadVlanProfiles();
}

function previewCli() {
    const board  = document.getElementById('r_board').value;
    const slot   = document.getElementById('r_slot').value;
    const port   = document.getElementById('r_port').value;
    const idx    = document.getElementById('r_onu_index').value;
    const sn     = document.getElementById('r_sn').value;
    const name   = document.querySelector('#registerForm [name="name"]').value || 'NAMA_PELANGGAN';
    const type   = document.querySelector('#registerForm [name="onu_type"]').value || 'ALL-ONT';
    const vlanI  = parseInt(document.querySelector('#registerForm [name="vlan_internet"]').value) || 0;
    const vlanA  = parseInt(document.querySelector('#registerForm [name="vlan_acs"]').value) || 0;
    const tcont  = document.querySelector('#registerForm [name="tcont_profile"]').value.trim();
    const pppoeU = document.querySelector('#registerForm [name="pppoe_user"]').value.trim();
    const pppoeP = document.querySelector('#registerForm [name="pppoe_pass"]').value.trim();

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
        document.getElementById('registerLog').classList.remove('hidden');
        return;
    }

    const isZteOnu = /^ZTE/i.test(sn);
    let cli = `! ══ ZTE C320 CLI Preview (${isZteOnu ? 'ONU ZTE Native' : 'ONU Non-ZTE / Huawei Bridge'} | ${USE_ACS ? 'ACS Active' : 'Pure OMCI'}) ══\n`;
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
    if (isZteOnu) {
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
        cli += `  vlan port veip_1 mode hybrid\n`;
    } else {
        if (vlanI) {
            cli += `  service 1 gemport 1 vlan ${vlanI}\n`;
        }
        if (vlanA && USE_ACS) {
            cli += `  service acs gemport 1 vlan ${vlanA}\n`;
        }
        cli += `  vlan port veip_1 mode transparent\n`;
        cli += `  vlan port eth_0/1 mode transparent\n`;
    }
    cli += `exit\n`;
    cli += `write\n`;

    if (!USE_ACS) {
        cli += `\n! ACS / TR-069 Non-Aktif di OLT ini — registrasi murni OMCI PPPoE tanpa TR-069.`;
    }

    document.getElementById('registerLogLabel').textContent = 'Preview CLI (belum dikirim)';
    document.getElementById('registerLogContent').style.color = '#81c995';
    document.getElementById('registerLogContent').textContent = cli;
    document.getElementById('registerLog').classList.remove('hidden');
}

document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnRegister');
    App.setLoading(btn, true, 'Mendaftarkan...');

    const fd = new FormData(this);
    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/onu/register`, { method: 'POST', body: fd }));
        App.setLoading(btn, false);

        const logEl      = document.getElementById('registerLog');
        const logContent = document.getElementById('registerLogContent');
        const logLabel   = document.getElementById('registerLogLabel');
        logEl.classList.remove('hidden');
        logLabel.textContent = data.success ? 'Log OLT — Berhasil' : 'Log OLT — Gagal';
        logContent.textContent = data.log ? data.log.join('\n') : data.message;

        if (data.success && data.partial) {
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
            if (hasWarn) logContent.textContent += '\n\n⚠ Ada peringatan — cek log di atas.';
            setTimeout(() => location.reload(), 3000);
        } else {
            logContent.style.color = '#f87171';
        }
    } catch (e) {
        App.setLoading(btn, false);
        App.showToast('Error: ' + e.message, 'error');
    }
});

// ── ACS Watcher ─────────────────────────────────────────────────
let _acsWatch = { interval: null, attempt: 0, onuId: 0, sn: '', user: '', pass: '' };
const ACS_MAX_ATTEMPT = 20;

function startAcsWatch(onuId, sn, user, pass, pushViaAcs = true) {
    if (_acsWatch.interval) clearInterval(_acsWatch.interval);
    Object.assign(_acsWatch, { interval: null, attempt: 0, onuId, sn, user, pass, pushViaAcs });

    document.getElementById('acsWatchSn').textContent = sn;
    _setWatchMsg('Menunggu ONU online di ACS...', false);
    document.getElementById('acsWatchSpinner').classList.remove('hidden');
    document.getElementById('acsWatchActions').classList.add('hidden');
    document.getElementById('acsWatcher').classList.remove('hidden');

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
        document.getElementById('acsWatchSpinner').classList.add('hidden');
        _setWatchMsg('Timeout — ONU belum muncul di ACS (5 menit).', false);
        document.getElementById('acsWatchActions').classList.remove('hidden');
        return;
    }

    fetch(`/onus/${_acsWatch.onuId}/acs-info`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                clearInterval(_acsWatch.interval);
                _acsWatch.interval = null;
                document.getElementById('acsWatchSpinner').classList.add('hidden');
                if (_acsWatch.pushViaAcs) {
                    _setWatchMsg('ONU ditemukan! Mendorong konfigurasi PPPoE via ACS...', false);
                    document.getElementById('acsWatchSpinner').classList.remove('hidden');
                    _pushAcs();
                } else {
                    _setWatchMsg('ONU online di ACS! PPPoE sudah diset via OLT.', true);
                    setTimeout(() => location.reload(), 3000);
                }
            }
        })
        .catch(() => {});
}

async function _pushAcs() {
    const fd = new FormData();
    fd.append('action',     'pppoe');
    fd.append('pppoe_user', _acsWatch.user);
    fd.append('pppoe_pass', _acsWatch.pass);

    try {
        const data = await App.readJson(await App.fetch(`/onus/${_acsWatch.onuId}/acs-set`, { method: 'POST', body: fd }));
        document.getElementById('acsWatchSpinner').classList.add('hidden');
        if (data.success) {
            _setWatchMsg('PPPoE berhasil dikonfigurasi!', true);
            setTimeout(() => location.reload(), 3000);
        } else {
            _setWatchMsg('Push gagal: ' + (data.message || 'Error'), false);
            document.getElementById('acsWatchActions').classList.remove('hidden');
        }
    } catch (e) {
        _setWatchMsg('Error saat push ke ACS.', false);
        document.getElementById('acsWatchActions').classList.remove('hidden');
    }
}

function retryAcsPush() {
    document.getElementById('acsWatchActions').classList.add('hidden');
    document.getElementById('acsWatchSpinner').classList.remove('hidden');
    _setWatchMsg('Mencoba push ulang...', false);
    _pushAcs();
}

function stopAcsWatch() {
    if (_acsWatch.interval) clearInterval(_acsWatch.interval);
    _acsWatch.interval = null;
    document.getElementById('acsWatcher').classList.add('hidden');
}

function _setWatchMsg(msg, isSuccess) {
    const el = document.getElementById('acsWatchMsg');
    el.textContent = msg;
    el.className   = 'text-xs mt-1 ' + (isSuccess ? 'text-emerald-600 font-semibold' : 'text-slate-500');
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
                    sel.outerHTML = '<input type="number" name="vlan_internet" id="vlanInternetFallback" class="form-input" placeholder="155" min="1" max="4094">';
                }
            }
        })
        .catch(() => {
            if (document.getElementById('vlanInternetSelect')) {
                document.getElementById('vlanInternetSelect').outerHTML =
                    '<input type="number" name="vlan_internet" id="vlanInternetFallback" class="form-input" placeholder="155" min="1" max="4094">';
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

async function getSignal(onuId, btn) {
    App.setLoading(btn, true, '');
    try {
        const data = await App.readJson(await App.fetch(`/onus/${onuId}/signal`));
        btn.disabled = false;
        if (data.success && data.signal) {
            const onuRx = data.signal.onu_rx ?? '?';
            const oltRx = data.signal.olt_rx ?? '?';
            const color = {'good':'#188038','warn':'#b06000','bad':'#d93025'}[data.quality] ?? '#5f6368';
            btn.innerHTML = `<span class="font-mono text-[11px] font-bold" style="color:${color}">${onuRx} dBm</span>`;
            btn.title = `ONU-RX: ${onuRx} | OLT-RX: ${oltRx} | ONU-TX: ${data.signal.onu_tx} | OLT-TX: ${data.signal.olt_tx}`;
        } else {
            btn.innerHTML = '<i data-lucide="activity" class="w-3.5 h-3.5"></i>';
            App.refreshIcons();
            App.showToast(data.message, 'error');
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="activity" class="w-3.5 h-3.5"></i>';
        App.refreshIcons();
        App.showToast('Error: ' + e.message, 'error');
    }
}

async function loadAcsStatus() {
    const btn = document.getElementById('btnAcs');
    App.setLoading(btn, true, 'Memuat...');
    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/acs-status`));
        App.setLoading(btn, false);
        if (!data.success) {
            App.showToast('ACS: ' + data.message, 'error');
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
                ? `<span class="chip chip-success"><span class="pulsing-dot"></span> Online</span>`
                : `<span class="chip chip-neutral">Offline ${lastInf}</span>`;
            const model = info.model ? `<div class="text-[10px] text-slate-400">${App.escapeHtml(info.model)}</div>` : '';
            cell.innerHTML = badge + model;
        });
    } catch (e) {
        App.setLoading(btn, false);
        App.showToast('Error: ' + e.message, 'error');
    }
}

function filterOnu(q) {
    q = q.toLowerCase();
    document.querySelectorAll('tr[data-sn]').forEach(row => {
        const sn   = (row.dataset.sn   || '').toLowerCase();
        const name = (row.dataset.name || '').toLowerCase();
        row.style.display = (!q || sn.includes(q) || name.includes(q)) ? '' : 'none';
    });

    document.querySelectorAll('.pon-section').forEach(section => {
        const body = section.querySelector('.pon-body');
        if (!body) return;
        if (!q) {
            body.classList.add('hidden');
            section.querySelector('.chevron').style.transform = '';
            return;
        }
        const hasVisible = [...section.querySelectorAll('tr[data-sn]')].some(r => r.style.display !== 'none');
        body.classList.toggle('hidden', !hasVisible);
        section.querySelector('.chevron').style.transform = hasVisible ? 'rotate(90deg)' : '';
    });
}

let _acsPushOnuId = 0;
function openAcsPush(onuId, sn, pppoeUser) {
    _acsPushOnuId = onuId;
    document.getElementById('acsPushSn').textContent   = 'SN: ' + sn;
    document.getElementById('acsPushUser').value       = pppoeUser || '';
    document.getElementById('acsPushPass').value       = '';
    document.getElementById('acsPushResult').className = 'hidden';
    App.openModal('acsPushModal');
}

async function doAcsPush() {
    const btn  = document.getElementById('btnAcsPush');
    const res  = document.getElementById('acsPushResult');
    const user = document.getElementById('acsPushUser').value.trim();
    const pass = document.getElementById('acsPushPass').value.trim();

    if (!user || !pass) {
        App.setMsg(res, 'warning', 'Username dan password wajib diisi.');
        return;
    }

    App.setLoading(btn, true, 'Pushing...');

    const fd = new FormData();
    fd.append('action',     'pppoe');
    fd.append('pppoe_user', user);
    fd.append('pppoe_pass', pass);

    try {
        const data = await App.readJson(await App.fetch(`/onus/${_acsPushOnuId}/acs-set`, { method: 'POST', body: fd }));
        App.setLoading(btn, false);
        App.setMsg(res, data.success ? 'success' : 'error',
            App.escapeHtml(data.success ? 'PPPoE berhasil dipush ke ONU.' : (data.message || 'Gagal.')));
    } catch (e) {
        App.setLoading(btn, false);
        App.setMsg(res, 'error', 'Error: ' + App.escapeHtml(e.message));
    }
}

async function deleteOnu(onuId, sn, btn) {
    if (!confirm(`Hapus ONU ${sn} dari OLT?\nAksi ini akan menghapus konfigurasi dari OLT.`)) return;
    App.setLoading(btn, true, '');
    try {
        const data = await App.readJson(await App.fetch(`/onus/${onuId}/delete`, { method: 'POST' }));
        if (data.success) location.reload();
        else {
            App.setLoading(btn, false);
            App.showToast(data.message, 'error');
        }
    } catch (e) {
        App.setLoading(btn, false);
        App.showToast('Error: ' + e.message, 'error');
    }
}

async function checkOltAlarms() {
    App.openModal('alarmModal');

    const loading = document.getElementById('alarmLoading');
    const content = document.getElementById('alarmContent');

    loading.classList.remove('hidden');
    content.classList.add('hidden');

    try {
        const data = await App.readJson(await App.fetch(`/olts/${OLT_ID}/alarms`));
        loading.classList.add('hidden');
        content.classList.remove('hidden');

        if (!data.success) {
            document.getElementById('alarmCurrentPre').textContent = 'Error: ' + data.message;
            return;
        }

        const a = data.alarms || {};
        document.getElementById('alarmCurrentPre').textContent = a.current_alarms || 'Tidak ada alarm aktif / Output kosong.';
        document.getElementById('alarmFanPre').textContent     = a.fan_status || 'Output show fan kosong.';
        document.getElementById('alarmPowerPre').textContent   = a.power_status || 'Tidak didukung firmware OLT ini.';
        document.getElementById('alarmCardPre').textContent    = a.card_status || 'Output show card kosong.';
        document.getElementById('alarmEnvPre').textContent     = a.env_status || 'Tidak didukung firmware OLT ini.';
    } catch (e) {
        loading.classList.add('hidden');
        content.classList.remove('hidden');
        document.getElementById('alarmCurrentPre').textContent = 'Error Exception: ' + e.message;
    }
}
</script>
<?= $this->endSection() ?>
