<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form action="<?= $olt ? "/olts/{$olt['id']}/update" : '/olts/store' ?>" method="POST">
                    <?= csrf_field() ?>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                        Informasi OLT
                    </h6>

                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label">Nama OLT <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= esc($olt['name'] ?? old('name')) ?>"
                                   placeholder="misal: OLT-PURBALINGGA-1" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Brand <span class="text-danger">*</span></label>
                            <select name="brand" class="form-select">
                                <?php $brands = ['ZTE', 'Fiberhome', 'Huawei', 'Nokia', 'Calix']; ?>
                                <?php foreach ($brands as $b): ?>
                                    <option value="<?= $b ?>" <?= ($olt['brand'] ?? 'ZTE') === $b ? 'selected' : '' ?>><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-8">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" class="form-control"
                                   value="<?= esc($olt['model'] ?? old('model', 'C320')) ?>"
                                   placeholder="C320, C600, AN5516, MA5800 ...">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Deskripsi</label>
                            <input type="text" name="description" class="form-control"
                                   value="<?= esc($olt['description'] ?? '') ?>"
                                   placeholder="Opsional">
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                        Koneksi Telnet
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-8">
                            <label class="form-label">IP Address <span class="text-danger">*</span></label>
                            <input type="text" name="ip" class="form-control"
                                   value="<?= esc($olt['ip'] ?? old('ip')) ?>"
                                   placeholder="192.168.1.1" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Port Telnet</label>
                            <input type="number" name="telnet_port" class="form-control"
                                   value="<?= esc($olt['telnet_port'] ?? 23) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="telnet_user" class="form-control"
                                   value="<?= esc($olt['telnet_user'] ?? old('telnet_user')) ?>"
                                   placeholder="zte" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Password <span class="text-danger"><?= $olt ? '' : '*' ?></span></label>
                            <input type="password" name="telnet_pass" class="form-control"
                                   placeholder="<?= $olt ? 'Kosongkan jika tidak ganti' : 'Password' ?>"
                                   <?= $olt ? '' : 'required' ?>>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                        SNMP (Opsional)
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-8">
                            <label class="form-label">SNMP Community</label>
                            <input type="text" name="snmp_community" class="form-control"
                                   value="<?= esc($olt['snmp_community'] ?? 'public') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label">SNMP Port</label>
                            <input type="number" name="snmp_port" class="form-control"
                                   value="<?= esc($olt['snmp_port'] ?? 161) ?>">
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.05em">
                        Konfigurasi Provisioning
                    </h6>

                    <div class="mb-4">
                        <label class="form-label">TCONT Profiles</label>
                        <textarea name="tcont_profiles" class="form-control font-monospace" rows="4"
                                  placeholder="250M&#10;100M&#10;50M"><?= esc($olt['tcont_profiles'] ?? '') ?></textarea>
                        <div class="form-text">Satu nama profile per baris — sesuai yang dikonfigurasi di OLT. Tampil sebagai dropdown saat register ONU.</div>
                    </div>

                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i><?= $olt ? 'Simpan Perubahan' : 'Tambah OLT' ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnTestTelnet" onclick="testTelnet()">
                            <i class="bi bi-plug me-1"></i>Test Koneksi
                        </button>
                        <a href="/olts" class="btn btn-light">Batal</a>
                        <span id="testResult" class="small ms-1"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
function testTelnet() {
    const btn = document.getElementById('btnTestTelnet');
    const res = document.getElementById('testResult');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
    res.textContent = '';

    const fd = new FormData();
    fd.append('ip',          document.querySelector('[name="ip"]').value.trim());
    fd.append('telnet_port', document.querySelector('[name="telnet_port"]').value || '23');
    fd.append('telnet_user', document.querySelector('[name="telnet_user"]').value.trim());
    fd.append('telnet_pass', document.querySelector('[name="telnet_pass"]').value);
    fd.append('olt_id',      '<?= $olt['id'] ?? 0 ?>');
    fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

    fetch('/olts/test-telnet', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plug me-1"></i>Test Koneksi';
            res.className = 'small ms-1 ' + (data.success ? 'text-success' : 'text-danger');
            res.innerHTML = (data.success ? '<i class="bi bi-check-circle me-1"></i>' : '<i class="bi bi-x-circle me-1"></i>') + data.message;
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-plug me-1"></i>Test Koneksi';
            res.className = 'small ms-1 text-danger';
            res.textContent = 'Error: ' + e.message;
        });
}
</script>
<?= $this->endSection() ?>
