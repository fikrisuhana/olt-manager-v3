<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<!-- Page Header -->
<div class="flex items-center gap-3 flex-wrap">
    <a href="/olts/<?= $onu['olt_id'] ?>" class="btn btn-secondary btn-sm btn-icon-only" title="Kembali ke OLT">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
    </a>
    <div class="flex items-center gap-2 flex-wrap min-w-0">
        <span class="font-mono text-sm font-bold text-slate-900"><?= esc($onu['sn']) ?></span>
        <span class="chip chip-neutral"><?= esc($onu['onu_type'] ?? '-') ?></span>
        <span class="text-[11px] text-slate-400 font-mono"><?= esc("{$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']}") ?></span>
    </div>
    <div class="ml-auto flex gap-2">
        <button class="btn btn-sm bg-amber-500 hover:bg-amber-600 text-white border-0" onclick="setAcsMng()" id="btnSetAcs">
            <i data-lucide="plug" class="w-3.5 h-3.5"></i> Set ACS
        </button>
        <button class="btn btn-sm bg-cyan-600 hover:bg-cyan-700 text-white border-0" onclick="loadAcsInfo()" id="btnLoadAcs">
            <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i> Refresh ACS
        </button>
        <button class="btn btn-danger btn-sm" id="btnReboot" onclick="reboot()">
            <i data-lucide="power" class="w-3.5 h-3.5"></i> Reboot
        </button>
    </div>
</div>

<div class="grid lg:grid-cols-12 gap-3 items-start">
    <!-- Kolom kiri: info, sinyal, ACS -->
    <div class="lg:col-span-5 space-y-3">
        <!-- Info ONU -->
        <div class="card !mb-0">
            <div class="card-header">
                <div class="card-title"><i data-lucide="cpu" class="w-4 h-4 text-slate-400"></i> Info ONU</div>
                <button class="btn btn-secondary btn-sm" onclick="toggleEdit()" id="btnToggleEdit">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                </button>
            </div>
            <div>
                <!-- Info display -->
                <table class="data-table !min-w-0" id="infoTable">
                    <tr><th class="!bg-transparent">SN</th><td class="font-mono text-xs"><?= esc($onu['sn']) ?></td></tr>
                    <tr>
                        <th class="!bg-transparent">Nama</th>
                        <td id="disp_name">
                            <span class="font-medium"><?= esc($onu['name'] ?? '-') ?></span>
                            <button class="btn btn-secondary btn-sm !min-h-[24px] !min-w-[24px] !p-1 ml-1 align-middle" title="Ubah nama saja" onclick="renameOnly()">
                                <i data-lucide="pencil" class="w-3 h-3"></i>
                            </button>
                        </td>
                    </tr>
                    <tr><th class="!bg-transparent">OLT</th><td class="text-xs"><?= esc($onu['olt_name'] ?? '-') ?> <span class="text-slate-400 font-mono text-[10px]">(<?= esc($onu['olt_ip'] ?? '') ?>)</span></td></tr>
                    <tr><th class="!bg-transparent">Port</th><td class="font-mono text-xs"><?= esc("{$onu['board']}/{$onu['slot']}/{$onu['port']}:{$onu['onu_index']}") ?></td></tr>
                    <tr><th class="!bg-transparent">Tipe</th><td class="text-xs"><?= esc($onu['onu_type'] ?? '-') ?></td></tr>
                    <tr>
                        <th class="!bg-transparent">VLAN</th>
                        <td id="disp_vlan">
                            <?php if ($onu['vlan_internet']): ?>
                                <span class="chip chip-info mr-1">Internet: <?= $onu['vlan_internet'] ?></span>
                            <?php endif; ?>
                            <?php if ($onu['vlan_acs']): ?>
                                <span class="chip chip-neutral">ACS: <?= $onu['vlan_acs'] ?></span>
                            <?php endif; ?>
                            <?php if (!$onu['vlan_internet'] && !$onu['vlan_acs']): ?>
                                <span class="text-slate-300">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr><th class="!bg-transparent">TCONT</th><td id="disp_tcont"><?= $onu['tcont_profile'] ? '<span class="font-mono text-xs">'.esc($onu['tcont_profile']).'</span>' : '<span class="text-slate-300">—</span>' ?></td></tr>
                    <tr><th class="!bg-transparent">PPPoE User</th><td id="disp_pppoe" class="font-mono text-xs"><?= esc($onu['pppoe_user'] ?? '—') ?></td></tr>
                    <tr><th class="!bg-transparent">Terdaftar</th><td class="text-xs text-slate-400"><?= date('d/m/Y H:i', strtotime($onu['registered_at'])) ?></td></tr>
                </table>

                <!-- Edit form (hidden by default) -->
                <div id="editForm" class="hidden border-t border-slate-100 mt-3 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-slate-500">Edit Konfigurasi</span>
                        <button type="button" class="btn btn-sm bg-cyan-600 hover:bg-cyan-700 text-white border-0" id="btnFetchConfig" onclick="fetchConfigFromOlt()">
                            <i data-lucide="cloud-download" class="w-3.5 h-3.5"></i> Ambil dari OLT
                        </button>
                    </div>
                    <div id="fetchConfigResult" class="hidden mb-2"></div>
                    <div class="form-grid">
                        <div class="form-group sm:col-span-2" style="grid-column: span 2">
                            <label class="form-label">Nama Pelanggan</label>
                            <input type="text" id="edit_name" class="form-input" value="<?= esc($onu['name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">VLAN Internet</label>
                            <input type="number" id="edit_vlan_internet" class="form-input" value="<?= esc($onu['vlan_internet'] ?? '') ?>" placeholder="misal: 100">
                        </div>
                        <div class="form-group">
                            <label class="form-label">VLAN ACS</label>
                            <input type="number" id="edit_vlan_acs" class="form-input" value="<?= esc($onu['vlan_acs'] ?? '') ?>" placeholder="misal: 155">
                        </div>
                        <div class="form-group">
                            <label class="form-label">TCONT Profile</label>
                            <input type="text" id="edit_tcont" class="form-input" value="<?= esc($onu['tcont_profile'] ?? '') ?>" placeholder="misal: 250M">
                        </div>
                        <div class="form-group">
                            <label class="form-label">PPPoE Username</label>
                            <input type="text" id="edit_pppoe_user" class="form-input" value="<?= esc($onu['pppoe_user'] ?? '') ?>" placeholder="user@isp">
                        </div>
                    </div>
                    <div id="editResult" class="hidden"></div>
                    <div class="mt-3 flex gap-2">
                        <button class="btn btn-primary btn-sm" onclick="saveInfo()">
                            <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Simpan
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="toggleEdit()">Batal</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sinyal ONU -->
        <div class="card !mb-0">
            <div class="card-header">
                <div class="card-title"><i data-lucide="activity" class="w-4 h-4 text-slate-400"></i> Sinyal Optik</div>
                <button class="btn btn-secondary btn-sm" id="btnSignal" onclick="loadSignal()">
                    <i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i> Refresh
                </button>
            </div>
            <div id="signalBox">
                <div class="flex items-center justify-center gap-2 py-3 text-xs text-slate-400">
                    <span class="spin spin-dark"></span> Mengambil sinyal dari OLT...
                </div>
            </div>
        </div>

        <!-- Status ACS -->
        <div class="card !mb-0">
            <div class="card-header">
                <div class="card-title"><i data-lucide="cloud" class="w-4 h-4 text-slate-400"></i> Status ACS</div>
                <?php if ($acsUpdatedAt): ?>
                    <span class="text-[10px] text-slate-400">Cache: <?= date('d/m H:i', strtotime($acsUpdatedAt)) ?></span>
                <?php endif; ?>
            </div>
            <div id="acsStatusBox">
                <?php if ($acsInfo): ?>
                <?php
                    $online  = $acsInfo['online'];
                    $lastInf = $acsInfo['last_inform'] ? date('d/m/Y H:i', strtotime($acsInfo['last_inform'])) : '-';
                ?>
                <div class="mb-2 flex items-center gap-2 flex-wrap">
                    <?php if ($online): ?>
                        <span class="chip chip-success"><span class="pulsing-dot"></span> Online</span>
                    <?php else: ?>
                        <span class="chip chip-neutral">Offline</span>
                    <?php endif; ?>
                    <span class="text-[10px] text-slate-400">Last inform: <?= $lastInf ?></span>
                </div>
                <?php if (!empty($acsInfo['model'])): ?>
                <div class="text-xs text-slate-500"><?= esc($acsInfo['model']) ?> <span class="text-slate-400">(<?= esc($acsInfo['manufacturer'] ?? '-') ?>)</span></div>
                <?php endif; ?>
                <div class="mt-2 text-[11px] text-slate-400"><i data-lucide="info" class="w-3 h-3 inline"></i> Data dari cache. Klik "Refresh ACS" untuk info lengkap (WAN IP, PPPoE, WiFi).</div>
                <?php else: ?>
                <div class="text-xs text-slate-400">
                    <?php if ($acsUpdatedAt): ?>
                        ONU tidak ditemukan di ACS cache. Klik "Refresh ACS" untuk cek live.
                    <?php else: ?>
                        Belum ada ACS cache. Klik "Sync Cache" di halaman OLT, atau klik "Refresh ACS" untuk cek live.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom kanan: PPPoE, WiFi, Clients -->
    <div class="lg:col-span-7 space-y-3">
        <!-- Edit PPPoE -->
        <div class="card !mb-0">
            <div class="card-header">
                <div class="card-title"><i data-lucide="ethernet" class="w-4 h-4 text-slate-400"></i> Edit PPPoE (WAN)</div>
            </div>
            <div>
                <?php if ($onu['vlan_internet'] || $onu['vlan_acs'] || $onu['tcont_profile']): ?>
                <div class="flex flex-wrap gap-2 mb-3 p-2.5 rounded-lg bg-slate-50 border border-slate-200">
                    <?php if ($onu['vlan_internet']): ?>
                    <span class="chip chip-info">VLAN Internet: <?= $onu['vlan_internet'] ?></span>
                    <?php endif; ?>
                    <?php if ($onu['vlan_acs']): ?>
                    <span class="chip chip-neutral">VLAN ACS: <?= $onu['vlan_acs'] ?></span>
                    <?php endif; ?>
                    <?php if ($onu['tcont_profile']): ?>
                    <span class="chip chip-neutral">TCONT: <?= esc($onu['tcont_profile']) ?></span>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="mb-3 p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5 inline"></i> VLAN belum diset.
                    <a href="#" class="font-bold underline" onclick="toggleEdit();return false">Edit Info ONU</a> untuk mengisi VLAN/TCONT.
                </div>
                <?php endif; ?>
                <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                    <div class="form-group">
                        <label class="form-label">PPPoE Username</label>
                        <input type="text" id="pppoe_user" class="form-input font-mono" value="<?= esc($onu['pppoe_user'] ?? '') ?>" placeholder="username@isp">
                    </div>
                    <div class="form-group">
                        <label class="form-label">PPPoE Password</label>
                        <div class="flex gap-1.5">
                            <input type="password" id="pppoe_pass" class="form-input font-mono" value="<?= esc($onu['pppoe_pass'] ?? '') ?>" placeholder="password">
                            <button class="btn btn-secondary btn-icon-only shrink-0" type="button" onclick="togglePass('pppoe_pass',this)" tabindex="-1">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="text-[11px] text-slate-400 mt-1">
                    <?php
                        $oltB     = strtoupper($onu['brand'] ?? '');
                        $isFhOnu  = strncasecmp($onu['sn'] ?? '', 'FHTT', 4) === 0 || strncasecmp($onu['sn'] ?? '', 'FHSC', 4) === 0;
                        $isZteOnu = strncasecmp($onu['sn'] ?? '', 'ZTEG', 4) === 0;
                    ?>
                    <?php if ($oltB === 'ZTE' && $isZteOnu): ?>
                        ONU ZTE di OLT ZTE: PPPoE + VLAN dipush via <strong>OLT pon-onu-mng</strong> (OMCI).
                    <?php elseif (($oltB === 'FIBERHOME' || $oltB === 'FH') && $isFhOnu): ?>
                        ONU FiberHome di OLT FiberHome: PPPoE + VLAN dipush via <strong>OLT (onu wan-cfg)</strong>.
                    <?php else: ?>
                        PPPoE dipush via <strong>TR-069/GenieACS</strong>.
                    <?php endif; ?>
                </div>
                <div id="pppoeResult" class="hidden"></div>
                <button class="btn btn-primary btn-sm mt-3" onclick="setPppoe()">
                    <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Push ke ONU
                </button>
            </div>
        </div>

        <!-- Edit WiFi -->
        <div class="card !mb-0" id="wifiCard">
            <div class="card-header">
                <div class="card-title"><i data-lucide="wifi" class="w-4 h-4 text-slate-400"></i> Edit WiFi</div>
            </div>
            <div>
                <!-- Info WiFi saat ini (diisi oleh Refresh ACS) -->
                <div id="wifiInfoRow" class="hidden mb-3 grid grid-cols-2 gap-2">
                    <div class="rounded-lg p-2.5 bg-sky-50 border border-sky-200">
                        <div class="flex items-center gap-1.5 mb-1 text-sky-600">
                            <i data-lucide="wifi" class="w-3.5 h-3.5"></i>
                            <span class="text-xs font-bold">2.4GHz</span>
                        </div>
                        <div class="font-mono text-xs text-slate-600 truncate" id="wifiInfo24" title="">—</div>
                    </div>
                    <div class="rounded-lg p-2.5 bg-emerald-50 border border-emerald-200">
                        <div class="flex items-center gap-1.5 mb-1 text-emerald-600">
                            <i data-lucide="wifi" class="w-3.5 h-3.5"></i>
                            <span class="text-xs font-bold">5GHz</span>
                        </div>
                        <div class="font-mono text-xs text-slate-600 truncate" id="wifiInfo5" title="">—</div>
                    </div>
                </div>

                <!-- Toggle mode -->
                <label class="flex items-center gap-2 mb-3 cursor-pointer select-none">
                    <input type="checkbox" id="wifi_dual_band" checked class="accent-blue-600 w-4 h-4" onchange="toggleWifiMode()">
                    <span class="text-xs text-slate-600">Terapkan ke <strong>2.4GHz &amp; 5GHz</strong> sekaligus (SSID &amp; password sama)</span>
                </label>

                <!-- Mode single -->
                <div id="wifiSingleForm">
                    <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
                        <div class="form-group">
                            <label class="form-label">SSID (Nama WiFi)</label>
                            <input type="text" id="wifi_ssid" class="form-input" placeholder="Nama-WiFi">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password WiFi</label>
                            <div class="flex gap-1.5">
                                <input type="password" id="wifi_key" class="form-input font-mono" placeholder="min 8 karakter">
                                <button class="btn btn-secondary btn-icon-only shrink-0" type="button" onclick="togglePass('wifi_key',this)" tabindex="-1">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="text-[11px] text-slate-400">SSID &amp; password sama untuk 2.4GHz dan 5GHz.</div>
                    <div id="wifiResult" class="hidden"></div>
                    <button class="btn btn-primary btn-sm mt-3" onclick="setWifi('both')">
                        <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Push ke ONU (2.4GHz &amp; 5GHz)
                    </button>
                </div>

                <!-- Mode terpisah -->
                <div id="wifiSeparateForm" class="hidden">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs font-bold text-sky-600 mb-2 flex items-center gap-1.5"><i data-lucide="wifi" class="w-3.5 h-3.5"></i> 2.4GHz</div>
                            <div class="form-group">
                                <label class="form-label">SSID</label>
                                <input type="text" id="wifi_ssid_24" class="form-input" placeholder="Nama-WiFi-2.4">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <div class="flex gap-1.5">
                                    <input type="password" id="wifi_key_24" class="form-input font-mono" placeholder="min 8 karakter">
                                    <button class="btn btn-secondary btn-icon-only shrink-0" type="button" onclick="togglePass('wifi_key_24',this)" tabindex="-1">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="wifiResult24" class="hidden"></div>
                            <button class="btn btn-sm bg-sky-600 hover:bg-sky-700 text-white border-0 w-full" onclick="setWifi('24')">
                                <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Push 2.4GHz
                            </button>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-emerald-600 mb-2 flex items-center gap-1.5"><i data-lucide="wifi" class="w-3.5 h-3.5"></i> 5GHz</div>
                            <div class="form-group">
                                <label class="form-label">SSID</label>
                                <input type="text" id="wifi_ssid_5" class="form-input" placeholder="Nama-WiFi-5G">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <div class="flex gap-1.5">
                                    <input type="password" id="wifi_key_5" class="form-input font-mono" placeholder="min 8 karakter">
                                    <button class="btn btn-secondary btn-icon-only shrink-0" type="button" onclick="togglePass('wifi_key_5',this)" tabindex="-1">
                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="wifiResult5" class="hidden"></div>
                            <button class="btn btn-sm bg-emerald-600 hover:bg-emerald-700 text-white border-0 w-full" onclick="setWifi('5')">
                                <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Push 5GHz
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Connected Clients -->
        <div class="card !mb-0" id="clientCard">
            <div class="card-header">
                <div class="card-title"><i data-lucide="users" class="w-4 h-4 text-slate-400"></i> Client Terhubung</div>
                <span class="text-[10px] text-slate-400">via Refresh ACS</span>
            </div>
            <div id="clientsBox">
                <div class="text-xs text-slate-400 p-1">Klik <strong>Refresh ACS</strong> untuk muat data client.</div>
            </div>
        </div>
    </div>
</div>

<?php if (strtoupper($onu['brand'] ?? '') === 'ZTE' && strncasecmp($onu['sn'], 'ZTEG', 4) === 0): ?>
<!-- Konfigurasi OLT (ZTE) — dari Database, tidak perlu Telnet. Hanya ONU ZTE di OLT ZTE. -->
<div class="card !mb-0">
    <div class="card-header">
        <div class="card-title"><i data-lucide="terminal" class="w-4 h-4 text-slate-400"></i> Konfigurasi OLT</div>
        <div class="flex gap-2 items-center">
            <span class="chip chip-neutral">dari database</span>
            <button class="btn btn-secondary btn-sm" onclick="toggleOltConfig()" id="btnToggleOltConfig">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
            </button>
        </div>
    </div>
    <div class="terminal-window !mb-0">
        <div class="terminal-header">
            <span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span>
            <span class="text-slate-500 text-[10px] ml-2">olt-config</span>
        </div>
        <div id="oltConfigPre" class="terminal-body" style="min-height:80px"></div>
    </div>
    <div id="oltConfigEdit" class="hidden border-t border-slate-100">
        <div class="p-3">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">VLAN Internet</label>
                    <input type="number" id="cfg_vlan_internet" class="form-input" value="<?= esc($onu['vlan_internet'] ?? '') ?>" oninput="renderOltConfig()">
                </div>
                <div class="form-group">
                    <label class="form-label">VLAN ACS</label>
                    <input type="number" id="cfg_vlan_acs" class="form-input" value="<?= esc($onu['vlan_acs'] ?? '') ?>" oninput="renderOltConfig()">
                </div>
                <div class="form-group">
                    <label class="form-label">TCONT Profile</label>
                    <input type="text" id="cfg_tcont" class="form-input" value="<?= esc($onu['tcont_profile'] ?? '') ?>" oninput="renderOltConfig()">
                </div>
                <div class="form-group">
                    <label class="form-label">PPPoE Username</label>
                    <input type="text" id="cfg_pppoe_user" class="form-input" value="<?= esc($onu['pppoe_user'] ?? '') ?>" oninput="renderOltConfig()">
                </div>
                <div class="form-group">
                    <label class="form-label">PPPoE Password</label>
                    <input type="text" id="cfg_pppoe_pass" class="form-input" value="<?= esc($onu['pppoe_pass'] ?? '') ?>" oninput="renderOltConfig()">
                </div>
            </div>
            <div id="oltCfgResult" class="hidden"></div>
            <div class="mt-3 flex gap-2 flex-wrap">
                <button class="btn btn-sm bg-amber-500 hover:bg-amber-600 text-white border-0" onclick="saveAndPushOlt()">
                    <i data-lucide="cloud-upload" class="w-3.5 h-3.5"></i> Simpan &amp; Push ke OLT
                </button>
                <button class="btn btn-primary btn-sm" onclick="saveOltConfigOnly()">
                    <i data-lucide="save" class="w-3.5 h-3.5"></i> Simpan ke DB saja
                </button>
                <button class="btn btn-secondary btn-sm" onclick="toggleOltConfig()">Batal</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Set ACS -->
<div id="setAcsModal" class="modal-overlay">
    <div class="modal-card" style="max-width:460px">
        <div class="card-header !mb-2">
            <div class="card-title"><i data-lucide="plug" class="w-4 h-4 text-amber-500"></i> Push pon-onu-mng ke ONU</div>
            <button type="button" class="btn btn-secondary btn-sm btn-icon-only" onclick="closeSetAcsModal()">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div>
            <p class="text-xs text-slate-500 mb-2">
                Set service mapping VLAN via OMCI langsung dari OLT:
            </p>
            <ul class="text-xs space-y-1.5 mb-3 list-disc pl-4">
                <?php if (in_array(strtoupper($onu['brand'] ?? ''), ['FIBERHOME', 'FH'])): ?>
                    <?php $isFhOnu = (bool)preg_match('/^FH(TT|SC)/i', $onu['sn'] ?? ''); ?>
                    <?php if ($isFhOnu): // ONU FH: WAN penuh di OLT ?>
                        <?php if ($onu['vlan_acs']): ?>
                        <li class="font-mono text-[11px]">onu wan-cfg <?= $onu['onu_index'] ?> index 1 mode tr069 ... <?= $onu['vlan_acs'] ?> ... dsp dhcp</li>
                        <?php endif; ?>
                        <?php if ($onu['vlan_internet']): ?>
                        <li class="font-mono text-[11px]">onu wan-cfg <?= $onu['onu_index'] ?> index 2 mode internet ... <?= $onu['vlan_internet'] ?> ...</li>
                        <?php endif; ?>
                    <?php else: // ONU non-FH: bridge VLAN ke veip ?>
                        <?php if ($onu['vlan_acs']): ?>
                        <li class="font-mono text-[11px]">onu veip <?= $onu['onu_index'] ?> cvlan-id <?= $onu['vlan_acs'] ?> ... svlan-vid <?= $onu['vlan_acs'] ?> ...</li>
                        <?php endif; ?>
                        <?php if ($onu['vlan_internet']): ?>
                        <li class="font-mono text-[11px]">onu veip <?= $onu['onu_index'] ?> cvlan-id <?= $onu['vlan_internet'] ?> ... svlan-vid <?= $onu['vlan_internet'] ?> ...</li>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($onu['vlan_internet']): ?>
                    <li class="font-mono text-[11px]">service hsi gemport 1 vlan <?= $onu['vlan_internet'] ?></li>
                    <?php endif; ?>
                    <?php if ($onu['vlan_acs']): ?>
                    <li class="font-mono text-[11px]">service acs gemport 1 vlan <?= $onu['vlan_acs'] ?></li>
                    <?php endif; ?>
                    <li class="font-mono text-[11px]">vlan port veip_1 mode hybrid</li>
                <?php endif; ?>
            </ul>
            <p class="text-xs text-slate-500">Setelah ONU konek ACS, PPPoE bisa di-push (FH ONU: via OLT; brand lain: via GenieACS).</p>
            <div id="setAcsResult" class="hidden"></div>
        </div>
        <div class="flex gap-2 mt-4">
            <button id="btnDoSetAcs" class="btn bg-amber-500 hover:bg-amber-600 text-white border-0 btn-sm" onclick="doSetAcs()">
                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Push pon-onu-mng
            </button>
            <button class="btn btn-secondary btn-sm" onclick="closeSetAcsModal()">Batal</button>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
const ONU_ID = <?= $onu['id'] ?>;

document.addEventListener('DOMContentLoaded', () => {
    loadSignal();
    loadAcsInfo();
    renderOltConfig();
});

// === Konfigurasi OLT — dari Database (tidak perlu Telnet) ===
const OLT_CFG = {
    b: '<?= $onu['board'] ?>', s: '<?= $onu['slot'] ?>',
    p: '<?= $onu['port'] ?>', i: '<?= $onu['onu_index'] ?>',
    vlanInt:  <?= (int)($onu['vlan_internet'] ?? 0) ?>,
    vlanAcs:  <?= (int)($onu['vlan_acs'] ?? 0) ?>,
    tcont:    '<?= esc(addslashes($onu['tcont_profile'] ?? '')) ?>',
    pppoeUser:'<?= esc(addslashes($onu['pppoe_user'] ?? '')) ?>',
    pppoePass:'<?= esc(addslashes($onu['pppoe_pass'] ?? '')) ?>',
};

function buildOltConfigText(vlanInt, vlanAcs, tcont, pppoeUser, pppoePass) {
    const ifName = `gpon-onu_${OLT_CFG.b}/${OLT_CFG.s}/${OLT_CFG.p}:${OLT_CFG.i}`;
    let L = [];
    L.push(`interface ${ifName}`);
    if (tcont)   L.push(` tcont 1 profile ${tcont}`);
    L.push(` gemport 1 tcont 1`);
    if (vlanInt) L.push(` service hsi gemport 1 vlan ${vlanInt}`);
    if (vlanAcs) L.push(` service acs gemport 1 vlan ${vlanAcs}`);
    L.push(`!`);
    L.push(``);
    L.push(`pon-onu-mng ${ifName}`);
    if (vlanInt) {
        L.push(` service hsi gemport 1 vlan ${vlanInt}`);
        L.push(` vlan port veip_1 mode hybrid vlan ${vlanInt}`);
    }
    if (vlanAcs) L.push(` service acs gemport 1 vlan ${vlanAcs}`);
    if (pppoeUser) {
        const pw = pppoePass || '(belum diisi)';
        L.push(` wan-ip 1 mode pppoe username ${pppoeUser} password ${pw}`);
        L.push(` wan-ip 1 dns mode static primary-dns 8.8.8.8`);
    }
    if (vlanAcs) L.push(` wan-ip 2 mode dhcp`);
    L.push(`!`);
    return L.join('\n');
}

function cfgHL(txt) {
    txt = txt.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    txt = txt.replace(/^(interface|pon-onu-mng)(\s)/gm,
        '<span style="color:#7dcfff;font-weight:600">$1</span>$2');
    txt = txt.replace(/\b(tcont|gemport|service|vlan|wan-ip|mode|username|password|dns|primary-dns|port|hybrid)\b/g,
        '<span style="color:#bb9af7">$1</span>');
    txt = txt.replace(/\b(pppoe|dhcp|static|hsi|acs|veip_1)\b/g,
        '<span style="color:#e0af68">$1</span>');
    txt = txt.replace(/\b(\d+)\b/g,
        '<span style="color:#9ece6a">$1</span>');
    txt = txt.replace(/^(!)/gm,
        '<span style="color:#565f89">!</span>');
    return txt;
}

function renderOltConfig() {
    const pre = document.getElementById('oltConfigPre');
    if (!pre) return;
    const editOpen = !document.getElementById('oltConfigEdit')?.classList.contains('hidden');
    const vlanInt   = editOpen ? (document.getElementById('cfg_vlan_internet')?.value || '') : (OLT_CFG.vlanInt || '');
    const vlanAcs   = editOpen ? (document.getElementById('cfg_vlan_acs')?.value      || '') : (OLT_CFG.vlanAcs || '');
    const tcont     = editOpen ? (document.getElementById('cfg_tcont')?.value          || '') : OLT_CFG.tcont;
    const pppoeUser = editOpen ? (document.getElementById('cfg_pppoe_user')?.value    || '') : OLT_CFG.pppoeUser;
    const pppoePass = editOpen ? (document.getElementById('cfg_pppoe_pass')?.value    || '') : OLT_CFG.pppoePass;
    pre.innerHTML = cfgHL(buildOltConfigText(vlanInt, vlanAcs, tcont, pppoeUser, pppoePass));
}

function toggleOltConfig() {
    const edit = document.getElementById('oltConfigEdit');
    const btn  = document.getElementById('btnToggleOltConfig');
    if (!edit) return;
    const show = edit.classList.contains('hidden');
    edit.classList.toggle('hidden', !show);
    btn.innerHTML = show
        ? '<i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Tutup'
        : '<i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit';
    App.refreshIcons();
    if (show) document.getElementById('oltCfgResult')?.classList.add('hidden');
    renderOltConfig();
}

async function doSaveOltConfig() {
    const fd = new FormData();
    fd.append('vlan_internet', document.getElementById('cfg_vlan_internet').value.trim());
    fd.append('vlan_acs',      document.getElementById('cfg_vlan_acs').value.trim());
    fd.append('tcont_profile', document.getElementById('cfg_tcont').value.trim());
    fd.append('pppoe_user',    document.getElementById('cfg_pppoe_user').value.trim());
    fd.append('pppoe_pass',    document.getElementById('cfg_pppoe_pass').value.trim());
    fd.append('name',          '');
    try {
        const d = await App.readJson(await App.fetch(`/onus/${ONU_ID}/update-info`, { method: 'POST', body: fd }));
        return d.success;
    } catch { return false; }
}

async function saveOltConfigOnly() {
    const res = document.getElementById('oltCfgResult');
    App.setMsg(res, 'loading', 'Menyimpan...');
    if (await doSaveOltConfig()) {
        App.setMsg(res, 'success', 'Tersimpan ke database.');
    } else {
        App.setMsg(res, 'error', 'Gagal menyimpan.');
    }
}

async function saveAndPushOlt() {
    const res = document.getElementById('oltCfgResult');
    App.setMsg(res, 'loading', 'Menyimpan ke DB...');

    if (!(await doSaveOltConfig())) {
        App.setMsg(res, 'error', 'Gagal menyimpan ke DB.');
        return;
    }

    App.setMsg(res, 'loading', 'Push ke OLT...');

    const pppoeUser = document.getElementById('cfg_pppoe_user').value.trim();
    const pppoePass = document.getElementById('cfg_pppoe_pass').value.trim();

    const fd = new FormData();
    let url;
    if (pppoeUser) {
        fd.append('action', 'pppoe');
        fd.append('pppoe_user', pppoeUser);
        fd.append('pppoe_pass', pppoePass);
        url = `/onus/${ONU_ID}/acs-set`;
    } else {
        url = `/onus/${ONU_ID}/set-acs`;
    }

    try {
        const data = await App.readJson(await App.fetch(url, { method: 'POST', body: fd }));
        App.setMsg(res, data.success ? 'success' : 'error',
            App.escapeHtml(data.message || (data.success ? 'Berhasil dipush ke OLT.' : 'Gagal push ke OLT.')));
    } catch (e) {
        App.setMsg(res, 'error', 'Error: ' + App.escapeHtml(e.message));
    }
}
// === End Konfigurasi OLT ===

async function loadSignal() {
    const box = document.getElementById('signalBox');
    const btn = document.getElementById('btnSignal');
    box.innerHTML = '<div class="flex items-center justify-center gap-2 py-3 text-xs text-slate-400"><span class="spin spin-dark"></span> Mengambil sinyal dari OLT...</div>';
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spin"></span>'; }

    try {
        const data = await App.readJson(await App.fetch(`/onus/${ONU_ID}/signal`));
        if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i> Refresh'; App.refreshIcons(); }
        if (!data.success) {
            box.innerHTML = `<div class="text-xs text-rose-600">✖ ${App.escapeHtml(data.message)}</div>`;
            return;
        }
        const s = data.signal;
        const rx = s.onu_rx !== null && s.onu_rx !== undefined ? parseFloat(s.onu_rx) : null;
        const qColor  = { good: '#188038', warn: '#b06000', bad: '#d93025' }[data.quality] ?? '#5f6368';
        const qLabel  = { good: 'Bagus', warn: 'Waspada', bad: 'Kritis' }[data.quality] ?? '-';
        const badge   = App.getSignalBadgeHtml(rx, data.quality !== 'bad' && rx !== null && rx >= -32);
        const head = `
            <div class="flex items-center gap-3 mb-3">
                <div class="font-mono font-bold text-2xl" style="color:${qColor}">${s.onu_rx ?? '?'} <span class="text-sm font-medium">dBm</span></div>
                ${badge}
            </div>
            <div class="text-[11px] text-slate-400 mb-2">ONU RX (sinyal di pelanggan) · kualitas: <strong style="color:${qColor}">${qLabel}</strong></div>`;
        if (data.source === 'acs') {
            box.innerHTML = head + `
                ${s.temperature ? `<div class="text-xs text-slate-500">Suhu Transceiver: <span class="font-mono">${s.temperature} &deg;C</span></div>` : ''}
                <div class="text-[11px] text-slate-400 mt-2">☁ Dari ACS/TR-069 (OLT Fiberhome).</div>`;
        } else {
            box.innerHTML = head + `
                <table class="data-table !min-w-0">
                    <tr><th class="!bg-transparent">OLT RX (dari pelanggan)</th><td class="font-mono text-xs">${s.olt_rx ?? '?'} dBm</td></tr>
                    <tr><th class="!bg-transparent">ONU TX (kirim ke OLT)</th><td class="font-mono text-xs">${s.onu_tx ?? '?'} dBm</td></tr>
                    <tr><th class="!bg-transparent">OLT TX (kirim ke pelanggan)</th><td class="font-mono text-xs">${s.olt_tx ?? '?'} dBm</td></tr>
                </table>`;
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i> Refresh'; App.refreshIcons(); }
        box.innerHTML = `<div class="text-xs text-rose-600">✖ Error: ${App.escapeHtml(e.message)}</div>`;
    }
}

async function fetchConfigFromOlt() {
    const btn = document.getElementById('btnFetchConfig');
    const res = document.getElementById('fetchConfigResult');
    App.setLoading(btn, true, 'Mengambil...');
    App.setMsg(res, 'loading', 'Menghubungi OLT...');

    try {
        const data = await App.readJson(await App.fetch(`/onus/${ONU_ID}/fetch-config`));
        App.setLoading(btn, false);
        if (!data.success) {
            App.setMsg(res, 'error', 'Gagal: ' + App.escapeHtml(data.message));
            return;
        }
        const c = data.config;
        if (c.tcont_profile)   document.getElementById('edit_tcont').value          = c.tcont_profile;
        if (c.vlan_internet)   document.getElementById('edit_vlan_internet').value  = c.vlan_internet;
        if (c.vlan_acs)        document.getElementById('edit_vlan_acs').value        = c.vlan_acs;
        if (data.pppoe_user) {
            document.getElementById('edit_pppoe_user').value = data.pppoe_user;
            const pppoeField = document.getElementById('pppoe_user');
            if (pppoeField && !pppoeField.value) pppoeField.value = data.pppoe_user;
        }

        App.setMsg(res, 'success',
            `Data dari ${data.source === 'olt' ? 'OLT' : 'DB'}: `
            + `TCONT <strong>${App.escapeHtml(c.tcont_profile || '—')}</strong>`
            + (c.vlan_internet  ? `, VLAN Internet <strong>${c.vlan_internet}</strong>` : '')
            + (c.vlan_acs       ? `, VLAN ACS <strong>${c.vlan_acs}</strong>`           : '')
            + (data.pppoe_user  ? `, PPPoE <strong>${App.escapeHtml(data.pppoe_user)}</strong>` : '')
            + (data.note        ? `<br><span class="opacity-70">${App.escapeHtml(data.note)}</span>` : ''));
    } catch (e) {
        App.setLoading(btn, false);
        App.setMsg(res, 'error', 'Error: ' + App.escapeHtml(e.message));
    }
}

function toggleEdit() {
    const form = document.getElementById('editForm');
    const btn  = document.getElementById('btnToggleEdit');
    const show = form.classList.contains('hidden');
    form.classList.toggle('hidden', !show);
    btn.innerHTML = show
        ? '<i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Batal'
        : '<i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit';
    App.refreshIcons();
    if (show) document.getElementById('editResult').classList.add('hidden');
}

// Ubah nama saja — kirim HANYA field name, sisanya tidak disentuh sama sekali.
async function renameOnly() {
    const current = <?= json_encode($onu['name'] ?? '') ?>;
    const val = prompt('Nama pelanggan baru:', current);
    if (val === null) return;
    const name = val.trim();
    if (name === '') { App.showToast('Nama tidak boleh kosong.', 'warning'); return; }
    if (name === current) return;

    const fd = new FormData();
    fd.append('name', name);

    try {
        const d = await App.readJson(await App.fetch(`/onus/${ONU_ID}/update-info`, { method: 'POST', body: fd }));
        if (!d.success) { App.showToast(d.message || 'Gagal menyimpan.', 'error'); return; }
        // Nama didorong ke OLT juga; kalau OLT menolak, jangan diam-diam kelihatan sukses.
        if (d.olt_warning) App.showToast(d.message, 'warning', 6000);
        location.reload();
    } catch (e) { App.showToast('Error: ' + e.message, 'error'); }
}

const ONU_CURRENT = <?= json_encode([
    'vlan_internet' => (string)($onu['vlan_internet'] ?? ''),
    'vlan_acs'      => (string)($onu['vlan_acs'] ?? ''),
    'tcont_profile' => (string)($onu['tcont_profile'] ?? ''),
]) ?>;

async function saveInfo(autoReload = false) {
    const vi = document.getElementById('edit_vlan_internet').value.trim();
    const va = document.getElementById('edit_vlan_acs').value.trim();
    const tc = document.getElementById('edit_tcont').value.trim();

    // VLAN/TCONT berubah = tulis ulang service-port & tcont di OLT → layanan pelanggan
    // putus beberapa detik. Jangan lakukan diam-diam saat orang cuma mau ganti nama.
    const cfgChanged = vi !== ONU_CURRENT.vlan_internet
                    || va !== ONU_CURRENT.vlan_acs
                    || tc !== ONU_CURRENT.tcont_profile;
    if (cfgChanged && !autoReload) {
        if (!confirm('VLAN/TCONT berubah — konfigurasi ONU akan ditulis ulang ke OLT '
                   + '(service-port lama dihapus, lalu dibuat ulang).\n\n'
                   + 'Koneksi pelanggan putus beberapa detik. Lanjutkan?')) return;
    }

    const fd = new FormData();
    fd.append('name',           document.getElementById('edit_name').value.trim());
    fd.append('vlan_internet',  vi);
    fd.append('vlan_acs',       va);
    fd.append('tcont_profile',  tc);
    fd.append('pppoe_user',     document.getElementById('edit_pppoe_user').value.trim());

    const el = document.getElementById('editResult');
    if (!autoReload) App.setMsg(el, 'loading', 'Menyimpan...');

    try {
        const data = await App.readJson(await App.fetch(`/onus/${ONU_ID}/update-info`, { method: 'POST', body: fd }));
        if (data.success) {
            if (data.olt_warning) App.showToast(data.message, 'warning', 6000);
            location.reload();
        } else {
            App.setMsg(el, 'error', App.escapeHtml(data.message || 'Gagal menyimpan.'));
        }
    } catch (e) {
        App.setMsg(el, 'error', 'Error: ' + App.escapeHtml(e.message));
    }
}

async function loadAcsInfo() {
    const box = document.getElementById('acsStatusBox');
    const btn = document.getElementById('btnLoadAcs');
    // Tambah spinner di bawah konten yang ada (jangan hapus cache dulu)
    let spinner = document.getElementById('acsLoadingRow');
    if (!spinner) {
        spinner = document.createElement('div');
        spinner.id = 'acsLoadingRow';
        spinner.className = 'mt-2 text-xs text-slate-400 flex items-center gap-1.5';
        spinner.innerHTML = '<span class="spin spin-dark"></span> Memuat data live dari ACS...';
        box.appendChild(spinner);
    }
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spin"></span> Loading...'; }

    try {
        const data = await App.readJson(await App.fetch(`/onus/${ONU_ID}/acs-info`));
        if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i> Refresh ACS'; App.refreshIcons(); }
        document.getElementById('acsLoadingRow')?.remove();
        if (!data.success) {
            const err = document.createElement('div');
            err.className = 'mt-2 text-xs text-rose-600';
            err.textContent = '✖ ' + data.message;
            box.appendChild(err);
            return;
        }
        const i = data.info;
        const online  = i.online
            ? '<span class="chip chip-success"><span class="pulsing-dot"></span> Online</span>'
            : '<span class="chip chip-neutral">Offline</span>';
        const lastInf = i.last_inform ? new Date(i.last_inform).toLocaleString('id') : '-';

        box.innerHTML = `
            <div class="mb-2 flex items-center gap-2 flex-wrap">${online}
                <span class="text-[10px] text-slate-400">Last Inform: ${lastInf}</span>
            </div>
            <table class="data-table !min-w-0">
                <tr><th class="!bg-transparent">Model</th><td class="text-xs">${App.escapeHtml(i.model || '-')} (${App.escapeHtml(i.manufacturer || '-')})</td></tr>
                <tr><th class="!bg-transparent">WAN IP (PPPoE)</th><td class="font-mono text-xs">${App.escapeHtml(i.wan?.ip || '-')}</td></tr>
                <tr><th class="!bg-transparent">PPPoE User</th><td class="text-xs font-mono">${App.escapeHtml(i.wan?.pppoe_user || '-')}</td></tr>
                <tr><th class="!bg-transparent">WAN Status</th><td class="text-xs">${App.escapeHtml(i.wan?.status || '-')}</td></tr>
                <tr><th class="!bg-transparent">Uptime</th><td class="text-xs">${i.wan?.uptime ? Math.floor(i.wan.uptime/3600)+'j '+Math.floor((i.wan.uptime%3600)/60)+'m' : '-'}</td></tr>
                <tr><th class="!bg-transparent">WiFi SSID</th><td class="text-xs">${App.escapeHtml(i.wifi?.ssid || '-')}</td></tr>
            </table>`;

        // Isi form WiFi (single + separate)
        if (i.wifi?.ssid) {
            document.getElementById('wifi_ssid').value    = i.wifi.ssid;
            document.getElementById('wifi_ssid_24').value = i.wifi.ssid;
        }
        if (i.wifi?.password) {
            document.getElementById('wifi_key').value    = i.wifi.password;
            document.getElementById('wifi_key_24').value = i.wifi.password;
        }
        if (i.wifi5?.ssid)     document.getElementById('wifi_ssid_5').value = i.wifi5.ssid;
        if (i.wifi5?.password) document.getElementById('wifi_key_5').value  = i.wifi5.password;

        // Tampilkan info WiFi saat ini di atas form
        const infoRow = document.getElementById('wifiInfoRow');
        const has24   = i.wifi?.ssid  != null;
        const has5    = i.wifi5?.ssid != null;
        if (infoRow && (has24 || has5)) {
            const el24 = document.getElementById('wifiInfo24');
            const el5  = document.getElementById('wifiInfo5');
            el24.textContent = has24 ? i.wifi.ssid  : '—';
            el5.textContent  = has5  ? i.wifi5.ssid : '—';
            el24.title = has24 ? i.wifi.ssid  : '';
            el5.title  = has5  ? i.wifi5.ssid : '';
            infoRow.classList.remove('hidden');
        }

        if (i.wan?.pppoe_user) document.getElementById('pppoe_user').value = i.wan.pppoe_user;
        if (i.wan?.pppoe_pass) document.getElementById('pppoe_pass').value = i.wan.pppoe_pass;

        // Sembunyikan WiFi section jika ONU tidak punya WiFi (bridge-only, mis. F609)
        const wifiCard = document.getElementById('wifiCard');
        if (wifiCard) {
            const hasWifi = i.wifi?.ssid !== null && i.wifi?.ssid !== undefined;
            wifiCard.style.display = hasWifi ? '' : 'none';
            if (!hasWifi) {
                const clientCard = document.getElementById('clientCard');
                if (clientCard) clientCard.style.display = 'none';
            }
        }

        // Tampilkan connected clients
        const clientBox = document.getElementById('clientsBox');
        if (clientBox && i.clients) {
            if (!i.clients.length) {
                clientBox.innerHTML = '<span class="text-xs text-slate-400">Tidak ada client terhubung.</span>';
            } else {
                const rows = i.clients.map(c => `
                    <tr>
                        <td class="text-xs">${App.escapeHtml(c.hostname || '—')}</td>
                        <td class="font-mono text-xs">${App.escapeHtml(c.ip)}</td>
                        <td class="font-mono text-[10px]">${App.escapeHtml(c.mac)}</td>
                        <td class="text-xs">${c.band ? `<span class="chip chip-neutral">${App.escapeHtml(c.band)}</span>` : '<span class="text-slate-400">LAN</span>'}</td>
                    </tr>`).join('');
                clientBox.innerHTML = `<div class="table-container"><table class="data-table">
                    <thead><tr><th>Hostname</th><th>IP</th><th>MAC</th><th>Band</th></tr></thead>
                    <tbody>${rows}</tbody></table></div>`;
            }
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i data-lucide="rotate-cw" class="w-3.5 h-3.5"></i> Refresh ACS'; App.refreshIcons(); }
        document.getElementById('acsLoadingRow')?.remove();
        const err = document.createElement('div');
        err.className = 'mt-2 text-xs text-rose-600';
        err.textContent = '✖ Error: ' + e.message;
        box.appendChild(err);
    }
}

function togglePass(id, btn) {
    const inp = document.getElementById(id);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.querySelector('svg')?.remove();
    btn.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}" class="w-4 h-4"></i>`;
    App.refreshIcons();
}

async function setPppoe() {
    const user = document.getElementById('pppoe_user').value.trim();
    const pass = document.getElementById('pppoe_pass').value.trim();
    const el   = document.getElementById('pppoeResult');

    if (!user) { App.setMsg(el, 'warning', 'Username PPPoE wajib diisi sebelum push.'); return; }
    if (!pass) { App.setMsg(el, 'warning', 'Password PPPoE wajib diisi sebelum push.'); return; }

    const fd = new FormData();
    fd.append('action', 'pppoe');
    fd.append('pppoe_user', user);
    fd.append('pppoe_pass', pass);

    App.setMsg(el, 'loading', 'Memproses...');

    try {
        const data = await App.readJson(await App.fetch(`/onus/${ONU_ID}/acs-set`, { method: 'POST', body: fd }));
        App.setMsg(el, data.success ? 'success' : 'error',
            App.escapeHtml(data.message || (data.success ? 'PPPoE berhasil dipush ke ONU.' : 'Gagal.')));
    } catch (err) {
        App.setMsg(el, 'error', 'Error: ' + App.escapeHtml(err.message));
    }
}

function toggleWifiMode() {
    const dual = document.getElementById('wifi_dual_band').checked;
    document.getElementById('wifiSingleForm').classList.toggle('hidden', !dual);
    document.getElementById('wifiSeparateForm').classList.toggle('hidden', dual);
}

async function setWifi(band = 'both') {
    let ssid, key, el;
    if (band === '24') {
        ssid = document.getElementById('wifi_ssid_24').value.trim();
        key  = document.getElementById('wifi_key_24').value.trim();
        el   = document.getElementById('wifiResult24');
    } else if (band === '5') {
        ssid = document.getElementById('wifi_ssid_5').value.trim();
        key  = document.getElementById('wifi_key_5').value.trim();
        el   = document.getElementById('wifiResult5');
    } else {
        ssid = document.getElementById('wifi_ssid').value.trim();
        key  = document.getElementById('wifi_key').value.trim();
        el   = document.getElementById('wifiResult');
    }

    if (!ssid || !key) { App.setMsg(el, 'warning', 'SSID dan password wajib diisi.'); return; }
    if (key.length < 8) { App.setMsg(el, 'warning', 'Password WiFi minimal 8 karakter.'); return; }

    const fd = new FormData();
    fd.append('action',   'wifi');
    fd.append('ssid',     ssid);
    fd.append('wifi_key', key);
    fd.append('band',     band);

    const bandLabel = { both: '2.4GHz & 5GHz', '24': '2.4GHz', '5': '5GHz' }[band] ?? band;
    App.setMsg(el, 'loading', 'Memproses...');

    try {
        const data = await App.readJson(await App.fetch(`/onus/${ONU_ID}/acs-set`, { method: 'POST', body: fd }));
        App.setMsg(el, data.success ? 'success' : 'error',
            App.escapeHtml(data.success ? `WiFi (${bandLabel}) berhasil diubah.` : (data.message || 'Gagal.')));
    } catch (err) {
        App.setMsg(el, 'error', 'Error: ' + App.escapeHtml(err.message));
    }
}

function setAcsMng() {
    App.openModal('setAcsModal');
}

function closeSetAcsModal() {
    App.closeModal('setAcsModal');
}

async function doSetAcs() {
    const btn = document.getElementById('btnDoSetAcs');
    const res = document.getElementById('setAcsResult');
    App.setLoading(btn, true, 'Pushing...');
    App.setMsg(res, 'loading', 'Menghubungi OLT...');

    try {
        const data = await App.readJson(await App.fetch(`/onus/${ONU_ID}/set-acs`, { method: 'POST' }));
        App.setLoading(btn, false);
        App.setMsg(res, data.success ? 'success' : 'error', App.escapeHtml(data.message));
        if (data.success) setTimeout(() => { closeSetAcsModal(); loadAcsInfo(); }, 2000);
    } catch (e) {
        App.setLoading(btn, false);
        App.setMsg(res, 'error', 'Error: ' + App.escapeHtml(e.message));
    }
}

async function reboot() {
    if (!confirm('Reboot ONU ini via ACS? ONU akan mati sebentar.')) return;
    const btn = document.getElementById('btnReboot');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('action', 'reboot');

    try {
        const data = await App.readJson(await App.fetch(`/onus/${ONU_ID}/acs-set`, { method: 'POST', body: fd }));
        btn.disabled = false;
        App.showToast(data.success ? 'Perintah reboot terkirim ke ONU.' : ('Gagal: ' + data.message), data.success ? 'success' : 'error');
    } catch (e) {
        btn.disabled = false;
        App.showToast('Error: ' + e.message, 'error');
    }
}
</script>
<?= $this->endSection() ?>
