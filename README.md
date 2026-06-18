# GPON Manager

Aplikasi web multi-user untuk manajemen ONU/ONT pada OLT GPON. Mendukung registrasi ONU via Telnet CLI dan auto-provisioning PPPoE/WiFi via GenieACS (TR-069).

---

## Fitur

- **Multi-user** — setiap user punya OLT dan ACS server sendiri, terisolasi satu sama lain
- **Multi-brand OLT** — driver terpisah per brand (ZTE aktif, Fiberhome stub/siap dikembangkan)
- **Multi-brand ONU** — ONU Fiberhome, ZTE, Huawei, RCMG, dll bisa didaftarkan ke OLT ZTE menggunakan type `ALL-ONT`
- **Scan ONU** — deteksi ONU belum terkonfigurasi via `show gpon onu uncfg` (1 perintah, ringan)
- **JSON Cache** — data ONU terdaftar disimpan lokal di `writable/onu_cache/` agar tidak perlu terus-menerus query OLT
- **Auto-index** — nomor index ONU berikutnya dihitung otomatis dari cache
- **Register ONU** — kirim konfigurasi ke OLT via Telnet CLI, pilih template, isi nama pelanggan
- **ACS Auto-Provision** — setelah ONU online, push username/password PPPoE langsung via GenieACS REST API
- **Edit WiFi & PPPoE** — ubah SSID, password WiFi, PPPoE user/pass dari halaman detail ONU
- **Cek Status ACS** — batch query GenieACS untuk semua ONU dalam satu OLT (online/offline, model)
- **Signal RX/TX** — tarik level sinyal ONU langsung dari OLT
- **Template Config** — simpan script CLI yang dijalankan otomatis saat register ONU
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
# Clone project
git clone <repo-url> gpon-manager
cd gpon-manager

# Jalankan (build + start semua service)
docker compose up -d --build

# Cek log
docker compose logs -f app
```

Akses di **http://localhost:8080** — MySQL otomatis membuat database, menjalankan migration, dan seed admin saat pertama kali container dibuat.

### Production

**1. Sesuaikan `docker-compose.prod.yml`** — ganti semua nilai yang ditandai `# <-- GANTI`:

```yaml
APP_BASE_URL: "https://gpon.yourdomain.com/"
DB_PASS: password_kuat
MYSQL_ROOT_PASSWORD: root_password_kuat
MYSQL_PASSWORD: password_kuat   # harus sama dengan DB_PASS
```

**2. Generate `ENCRYPTION_KEY`** (lakukan sekali, simpan hasilnya):

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Set hasilnya ke `ENCRYPTION_KEY` di `docker-compose.prod.yml`.

**3. Deploy:**

```bash
# Build image
docker build -t gpon-manager:latest .

# Jalankan
docker compose -f docker-compose.prod.yml up -d
```

> Kalau pakai reverse proxy (Nginx/Caddy), ganti port di prod compose:
> `"127.0.0.1:8080:80"` — lalu proxy dari port 80/443 ke 8080.

---

## Login Default

| Username | Password |
|---|---|
| `admin` | `admin123` |

> **Ganti password setelah pertama kali login!**

---

## Environment Variables

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
| `olts` | OLT per user (IP, brand, model, kredensial Telnet) |
| `onus` | ONU terdaftar per OLT (SN, nama, port, index, ACS device ID) |
| `templates` | Template script CLI untuk konfigurasi ONU |
| `acs_servers` | ACS server (GenieACS) per user |
| `provision_logs` | Log semua aktivitas register/delete/ACS provision |

Migration ada di `database/migrations/`. Dijalankan otomatis oleh Docker saat container MySQL pertama kali dibuat.

---

## Alur Penggunaan

### Setup Awal

1. Login sebagai `admin`
2. **Tambah ACS Server** → menu *ACS Server* → isi URL GenieACS (`http://IP:7557`) → set sebagai Default
3. **Tambah OLT** → menu *OLT* → isi IP, port Telnet, username, password
4. **Tambah Template** _(opsional)_ → menu *Template Config* → isi script CLI untuk interface `gpon-onu` dan `pon-onu-mng`

### Register ONU Baru

1. Buka halaman OLT
2. Klik **Sync Cache** — sinkronisasi data ONU terdaftar dari OLT *(lakukan sekali, atau jika ada perubahan manual di OLT)*
3. Klik **Scan ONU Baru** — deteksi ONU yang baru colok *(hanya 1 perintah ke OLT)*
4. Klik **Register** di baris ONU yang muncul
5. Isi: nama pelanggan, tipe ONU, VLAN Internet, VLAN ACS, TCONT Profile, PPPoE user/pass
6. Klik **Preview CLI** untuk melihat persis perintah yang akan dikirim ke OLT sebelum eksekusi
7. Klik **Register** untuk eksekusi

**Catatan PPPoE per brand ONU:**
- **ZTE ONU** (`ZTEG*`): PPPoE dikonfigurasi via OMCI (`ip-host 1 dhcp`) langsung dari OLT — langsung aktif
- **Fiberhome ONU** (`FHTT*`): OMCI `ip-host` tidak compatible di ZTE C320 → PPPoE harus dipush via GenieACS/TR-069 setelah ONU terdeteksi di ACS. Centang **Juga push ke GenieACS** atau push manual dari halaman detail ONU.

### Edit WiFi / PPPoE

1. Klik SN ONU di tabel → masuk halaman detail ONU
2. Klik **Muat Info ACS** → lihat IP, status WAN, SSID WiFi saat ini
3. Edit PPPoE atau WiFi → klik **Push ke ONU**

---

## Catatan OLT & ONU

### OLT ZTE C320 (v1.2)

Perintah yang digunakan aplikasi ini:

```
show gpon onu uncfg                          → scan ONU belum dikonfigurasi
show gpon onu state                          → status semua ONU (working/los/lofi)
show gpon onu baseinfo gpon-olt_B/S/P        → SN dan tipe ONU per port
show pon power attenuation                   → sinyal RX/TX
onu INDEX type TYPE sn SN                    → register ONU
```

### Brand ONU di OLT ZTE

ONU dari brand berbeda didaftarkan ke OLT ZTE menggunakan type `ALL-ONT`:

| SN Prefix | Brand | Type di OLT | Provisioning PPPoE |
|---|---|---|---|
| `FHTT...` | Fiberhome | `ALL-ONT` | GenieACS/TR-069 saja (OMCI tidak compatible) |
| `ZTEG...` | ZTE | `ZTE-F609`, `ZTE-F660`, dll | OMCI `ip-host` + opsional ACS |
| `HWTC...` | Huawei | `ALL-ONT` | GenieACS/TR-069 |
| `RCMG...` | Raisecom | `ALL-ONT` | GenieACS/TR-069 |

Aplikasi mendeteksi brand dari SN prefix secara otomatis dan memilih metode provisioning yang tepat.

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
| Register ONU | **~8** | Urutan konfigurasi CLI + `write` |
| Cek Sinyal | **1** | `show pon power attenuation` |
| Cek ACS Status | 0 (query ke ACS) | Batch query GenieACS, max 200 ONU |

> **Sync Cache** adalah operasi berat — lakukan sekali di awal, lalu biarkan cache dijaga otomatis oleh aplikasi (register/delete update cache). Ulangi Sync Cache hanya jika ada perubahan manual di OLT.

---

## Arsitektur Kode

```
app/
├── Controllers/
│   ├── AuthController.php       Login/logout (registrasi publik dinonaktifkan)
│   ├── AdminController.php      User management, overview semua OLT/ACS/log
│   ├── OltController.php        CRUD OLT, scan, refresh-cache, acs-status
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

# Rebuild image setelah update kode (production)
docker build -t gpon-manager:latest .
docker compose -f docker-compose.prod.yml up -d app

# Backup database
docker compose exec mysql mysqldump -u gpon -pgpon_secret gpon_manager > backup_$(date +%Y%m%d).sql

# Restore database
docker compose exec -T mysql mysql -u gpon -pgpon_secret gpon_manager < backup.sql

# Reset total (HAPUS SEMUA DATA)
docker compose down -v
```
