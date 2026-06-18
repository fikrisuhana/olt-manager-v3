# GPON Manager

Aplikasi web multi-user untuk manajemen ONU/ONT pada OLT GPON. Mendukung registrasi ONU via Telnet CLI dan auto-provisioning PPPoE/WiFi via GenieACS (TR-069).

---

## Fitur

- **Multi-user** — setiap user punya OLT dan ACS server sendiri, terisolasi satu sama lain
- **Multi-brand OLT** — driver terpisah per brand (ZTE aktif, Fiberhome stub/siap dikembangkan)
- **Multi-brand ONU** — ONU Fiberhome, ZTE, Huawei, RCMG, dll didaftarkan ke OLT ZTE dengan type `ALL-ONT`
- **Scan ONU** — deteksi ONU belum terkonfigurasi via `show gpon onu uncfg` (1 perintah, ringan)
- **JSON Cache** — data ONU terdaftar disimpan lokal di `writable/onu_cache/` agar tidak perlu terus-menerus query OLT
- **Auto-index** — nomor index ONU berikutnya dihitung otomatis dari cache
- **Register ONU** — form terstruktur: VLAN Internet, VLAN ACS, TCONT Profile (dropdown), PPPoE user/pass
- **Preview CLI** — lihat persis perintah yang akan dikirim ke OLT sebelum eksekusi
- **TCONT Profile per OLT** — daftar profile dikonfigurasi di OLT, tampil sebagai dropdown saat register
- **ACS Auto-Provision** — push PPPoE langsung via GenieACS REST API, bisa dari tabel ONU atau halaman detail
- **Edit WiFi & PPPoE** — ubah SSID, password WiFi, PPPoE user/pass dari halaman detail ONU
- **Cek Status ACS** — batch query GenieACS untuk semua ONU dalam satu OLT (online/offline, model)
- **Signal RX/TX** — tarik level sinyal ONU langsung dari OLT
- **Template Config** — simpan script CLI tambahan (`gpon-onu` interface) yang dieksekusi otomatis saat register
- **Search ONU** — filter realtime by SN atau nama pelanggan di tabel ONU
- **Test Koneksi** — tombol test Telnet langsung dari form OLT sebelum simpan
- **Admin panel** — kelola user, lihat semua OLT dan ACS dari semua user, log aktivitas global

---

## Tech Stack

| Komponen | Detail |
|---|---|
| Backend | PHP 8.3, CodeIgniter 4.7 |
| Database | MySQL 8.0 |
| OLT Koneksi | Telnet via `fsockopen` (IAC negotiation) |
| ACS | GenieACS REST API (port 7557) |
| Frontend | Bootstrap 5.3, Bootstrap Icons, AJAX |
| Deploy | Docker (PHP 8.3-Apache) |

---

## Persyaratan

- Docker + Docker Compose v2
- Akses jaringan ke OLT (port Telnet, default 23)
- GenieACS yang sudah berjalan (port 7557)

---

## Instalasi via Docker

### Development / Lokal

```bash
git clone <repo-url> gpon-manager
cd gpon-manager

# Build + jalankan semua service
docker compose up -d --build

# Cek log
docker compose logs -f app
```

Akses di **http://localhost:8080** — MySQL otomatis membuat database, menjalankan migration, dan seed admin saat pertama kali container dibuat.

### Production / IP Langsung (tanpa domain)

**1. Edit `docker-compose.prod.yml`** — sesuaikan bagian berikut:

```yaml
APP_BASE_URL: "http://IP_SERVER:8080/"
DB_PASS: password_kuat
MYSQL_ROOT_PASSWORD: root_password_kuat
MYSQL_PASSWORD: password_kuat        # harus sama dengan DB_PASS
ENCRYPTION_KEY: "isi_hasil_generate"
```

**2. Generate `ENCRYPTION_KEY`** (sekali saja):

```bash
php -r "echo bin2hex(random_bytes(32));"
```

**3. Build dan jalankan:**

```bash
docker build -t gpon-manager:latest .
docker compose -f docker-compose.prod.yml up -d
```

Akses di `http://IP_SERVER:8080`. Pastikan port 8080 tidak diblok firewall:
```bash
ufw allow 8080
```

### Production dengan Domain + SSL (EasyEngine / reverse proxy)

Jalankan app di port lokal, lalu proxy lewat nginx:

```bash
# Pastikan port di prod compose: "127.0.0.1:8080:80"
docker build -t gpon-manager:latest .
docker compose -f docker-compose.prod.yml up -d

# EasyEngine — buat reverse proxy ke app
ee site create gpon.domain.com --type=proxy --proxy=127.0.0.1:8080
```

> File `.env` **tidak perlu disentuh** — semua konfigurasi diinjek via `docker-compose.prod.yml`.

---

## Login Default

| Username | Password |
|---|---|
| `admin` | `admin123` |

> **Ganti password setelah pertama kali login!**

---

## Environment Variables

Semua di-set di `docker-compose.yml` / `docker-compose.prod.yml`, tidak perlu edit `.env`.

| Variable | Default | Keterangan |
|---|---|---|
| `CI_ENVIRONMENT` | `production` | Set `development` untuk aktifkan debug toolbar |
| `APP_BASE_URL` | `http://localhost/` | URL publik aplikasi (dengan trailing slash) |
| `DB_HOST` | `mysql` | Hostname MySQL |
| `DB_NAME` | `gpon_manager` | Nama database |
| `DB_USER` | `gpon` | Username MySQL |
| `DB_PASS` | `secret` | Password MySQL |
| `DB_PORT` | `3306` | Port MySQL |
| `SESSION_EXPIRATION` | `7200` | Session timeout (detik) |
| `ENCRYPTION_KEY` | _(auto-generate)_ | Key enkripsi CI4 — wajib di-set manual di production |

---

## Struktur Database

| Tabel | Keterangan |
|---|---|
| `users` | Akun pengguna, role: `admin` / `user` |
| `olts` | OLT per user (IP, brand, model, kredensial Telnet, TCONT profiles) |
| `onus` | ONU terdaftar per OLT (SN, nama, port, index, VLAN, TCONT, PPPoE user, ACS device ID) |
| `templates` | Template script CLI tambahan (`gpon-onu` interface) per user |
| `acs_servers` | ACS server (GenieACS) per user |
| `provision_logs` | Log semua aktivitas register/delete/ACS provision |

Migration ada di `database/migrations/001_create_gpon_tables.sql` — dijalankan otomatis oleh Docker saat container MySQL pertama kali dibuat.

---

## Alur Penggunaan

### Setup Awal

1. Login sebagai `admin`
2. **Tambah ACS Server** → menu *ACS Server* → isi URL GenieACS (`http://IP:7557`) → set sebagai Default
3. **Tambah OLT** → menu *OLT* → isi IP, port Telnet, username, password, dan daftar **TCONT Profiles** (satu per baris, misal `250M`)
4. Klik **Test Koneksi** di form OLT untuk verifikasi sebelum simpan
5. **Tambah Template** _(opsional)_ → menu *Template Config* → isi script CLI tambahan untuk interface `gpon-onu`

### Register ONU Baru

1. Buka halaman OLT
2. Klik **Sync Cache** — sinkronisasi data ONU terdaftar dari OLT *(lakukan sekali, atau jika ada perubahan manual di OLT)*
3. Klik **Scan ONU Baru** — deteksi ONU yang baru colok *(hanya 1 perintah ke OLT)*
4. Klik **Register** di baris ONU yang muncul
5. Isi: Nama Pelanggan, Tipe ONU, VLAN Internet, VLAN ACS, TCONT Profile (dropdown), PPPoE user/pass
6. Klik **Preview CLI** untuk melihat persis perintah yang akan dikirim ke OLT
7. Klik **Register** untuk eksekusi

### Push PPPoE ke ACS

- **Dari tabel ONU** — klik tombol <kbd>↑</kbd> (cloud-arrow-up) di baris ONU → modal kecil muncul, isi password → Push
- **Dari halaman detail ONU** — klik *Muat Info ACS* → edit PPPoE → *Push ke ONU*

---

## Catatan OLT & ONU

### OLT ZTE C320 (v1.2) — CLI yang Digunakan

```
show gpon onu uncfg                          → scan ONU belum dikonfigurasi
show gpon onu state                          → status semua ONU (working/los/lofi)
show gpon onu baseinfo gpon-olt_B/S/P        → SN dan tipe ONU per port
show pon power attenuation gpon-onu_B/S/P:I  → sinyal RX/TX
onu INDEX type TYPE sn SN                    → register ONU di port PON
write                                        → simpan konfigurasi ke flash
```

### Format CLI Register ONU (Diverifikasi vs ZTE C320 v1.2)

```
interface gpon-olt_1/1/1
  onu 1 type ALL-ONT sn FHTTXXXXXXXX
exit
interface gpon-onu_1/1/1:1
  name NAMA PELANGGAN
  sn-bind enable sn
  tcont 1 name tcont profile 250M
  gemport 1 name gemport tcont 1
  gemport 1 traffic-limit upstream 250M downstream 250M
  service-port 1 vport 1 user-vlan 100 vlan 100
  service-port 2 vport 1 user-vlan 155 vlan 155
exit
write
```

> Semua `service-port` menggunakan `vport 1`. PPPoE **tidak** dikonfigurasi via OMCI (`pon-onu-mng ip-host`) — dipush via GenieACS/TR-069 setelah ONU online, untuk semua brand ONU.

### Brand ONU di OLT ZTE

| SN Prefix | Brand | Type di OLT | Provisioning PPPoE |
|---|---|---|---|
| `FHTT...` / `FHSC...` | Fiberhome | `ALL-ONT` | GenieACS/TR-069 |
| `ZTEG...` | ZTE | `ZTE-F609`, dll | GenieACS/TR-069 |
| `HWTC...` | Huawei | `ALL-ONT` | GenieACS/TR-069 |
| `RCMG...` | Raisecom | `ALL-ONT` | GenieACS/TR-069 |

### ACS (GenieACS) — WAN Path per Brand ONU

Path WAN PPPoE berbeda per brand (dideteksi otomatis dari `_deviceId._Manufacturer`):

| Brand ONU | WANConnectionDevice Index | Full Path |
|---|---|---|
| Fiberhome | **2** | `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1` |
| ZTE, Huawei, default | **1** | `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1` |

---

## Performa & Beban OLT

| Operasi | Perintah ke OLT | Keterangan |
|---|---|---|
| Scan ONU Baru | **1** | `show gpon onu uncfg` |
| Sync Cache | **1 + N port** | `show gpon onu state` + `show gpon onu baseinfo` per port aktif |
| Register ONU | **~6** | gpon-olt + gpon-onu interface + `write` |
| Cek Sinyal | **1** | `show pon power attenuation` |
| Test Koneksi | **1** | login + disconnect |
| Cek ACS Status | 0 (query ke ACS) | Batch query GenieACS, max 200 ONU |

> **Sync Cache** adalah operasi berat — lakukan sekali di awal, lalu biarkan cache dijaga otomatis oleh aplikasi (register/delete update cache). Ulangi hanya jika ada perubahan manual di OLT.

---

## Arsitektur Kode

```
app/
├── Controllers/
│   ├── AuthController.php       Login/logout (registrasi publik dinonaktifkan)
│   ├── AdminController.php      User management, overview semua OLT/ACS/log
│   ├── OltController.php        CRUD OLT, scan, refresh-cache, acs-status, test-telnet
│   ├── OnuController.php        Register, delete, signal, acs-info, acs-set
│   ├── AcsController.php        CRUD ACS server, test koneksi
│   └── TemplateController.php   CRUD template konfigurasi
│
├── Libraries/
│   ├── TelnetService.php        Raw Telnet via fsockopen + IAC negotiation
│   ├── OltDriverFactory.php     Factory: brand OLT → driver class
│   ├── OnuCacheService.php      Cache ONU terdaftar ke JSON lokal
│   ├── AcsService.php           GenieACS REST API client
│   └── Drivers/
│       ├── OltDriverInterface.php
│       ├── ZteDriver.php        Driver ZTE (diverifikasi vs C320 v1.2)
│       └── FiberhomeDriver.php  Driver FH (stub, siap dikembangkan)
│
└── Filters/
    ├── AuthFilter.php           Redirect ke /login jika belum login
    └── AdminFilter.php          Redirect jika bukan admin

writable/
└── onu_cache/
    └── olt_{id}.json           Cache ONU per OLT (index, SN, type, status OLT)
```

---

## Menambah Brand OLT Baru

1. Buat `app/Libraries/Drivers/HuaweiDriver.php` implementasi `OltDriverInterface`
2. Tambah case di `OltDriverFactory.php`:
   ```php
   case 'huawei': return new HuaweiDriver($oltConfig);
   ```
3. Tambah WAN path di `AcsService::WAN_PATHS` jika berbeda dari default

---

## Perintah Docker Berguna

```bash
# Log real-time
docker compose logs -f app

# Masuk ke container app
docker compose exec app bash

# Restart app saja
docker compose restart app

# Update kode (development)
git pull
docker compose restart app

# Update kode (production)
git pull
docker build -t gpon-manager:latest .
docker compose -f docker-compose.prod.yml up -d app

# Backup database
docker compose exec mysql mysqldump -u gpon -pgpon_secret gpon_manager > backup_$(date +%Y%m%d).sql

# Restore database
docker compose exec -T mysql mysql -u gpon -pgpon_secret gpon_manager < backup.sql

# Reset total (HAPUS SEMUA DATA)
docker compose down -v
```
