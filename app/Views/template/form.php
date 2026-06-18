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

                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label class="form-label">VLAN Internet</label>
                            <input type="number" name="vlan_internet" class="form-control"
                                   value="<?= esc($template['vlan_internet'] ?? '') ?>"
                                   placeholder="misal: 301">
                        </div>
                        <div class="col-4">
                            <label class="form-label">VLAN Management</label>
                            <input type="number" name="vlan_management" class="form-control"
                                   value="<?= esc($template['vlan_management'] ?? 100) ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label">TCONT Profile</label>
                            <input type="text" name="tcont_profile" class="form-control"
                                   value="<?= esc($template['tcont_profile'] ?? '') ?>"
                                   placeholder="misal: UP-1G">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Script <code>interface gpon-onu_x/x/x:x</code>
                            <small class="text-muted fw-normal">(tcont, gemport, service-port)</small>
                        </label>
                        <textarea name="gpon_onu_script" class="form-control font-monospace"
                                  rows="8" placeholder="Contoh:
tcont 1 name tcont_1 profile UP-1G
gemport 1 tcont 1
gemport 1 traffic-limit upstream 1G downstream 1G
service-port 1 vport 1 user-vlan 100 vlan 100
service-port 2 vport 2 user-vlan 301 vlan 301"><?= esc($template['gpon_onu_script'] ?? '') ?></textarea>
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
