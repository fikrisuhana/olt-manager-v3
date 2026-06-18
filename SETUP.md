# GPON Manager — Setup di Server EasyEngine

## 1. Upload ke Server

```bash
# Di server EasyEngine
cd /opt/easyengine/sites/
ee site create gpon.domain.com --type=php --php=8.2
cd /var/www/gpon.domain.com/htdocs

# Clone/upload project (hapus isi default dulu)
rm -rf *
```

## 2. Install Dependencies

```bash
cd /var/www/gpon.domain.com/htdocs
composer install --no-dev --optimize-autoloader
```

## 3. Setup Database

```bash
mysql -u root -p
```
```sql
CREATE DATABASE gpon_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'gponuser'@'localhost' IDENTIFIED BY 'passwordkuat';
GRANT ALL ON gpon_manager.* TO 'gponuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```
```bash
mysql -u gponuser -p gpon_manager < database/migrations/001_create_gpon_tables.sql
```

## 4. Konfigurasi .env

```bash
cp env .env
nano .env
```
Ubah:
```
CI_ENVIRONMENT = production
app.baseURL = 'https://gpon.domain.com/'
database.default.hostname = localhost
database.default.database = gpon_manager
database.default.username = gponuser
database.default.password = passwordkuat
```

## 5. Permissions

```bash
chmod -R 777 writable/
chown -R www-data:www-data .
```

## 6. Nginx Config EasyEngine

EasyEngine sudah auto-config. Pastikan document root diarahkan ke `/public`:

```bash
# Edit nginx config
ee site edit gpon.domain.com
```
Ubah `root /var/www/.../htdocs;` → `root /var/www/.../htdocs/public;`

Kemudian:
```bash
ee site reload gpon.domain.com
```

## 7. Akses

Buka `https://gpon.domain.com` → Register akun → Tambah OLT → Scan ONU

---

## Catatan OLT ZTE C320

- Port Telnet default: **23**
- Login: `zte` / `zte` (ganti setelah setup)
- Setelah login, OLT tampilkan warning password lemah — tidak masalah, sistem tetap berjalan
- Command kunci: `show gpon onu uncfg` untuk ONU belum dikonfigurasi
- Untuk register ONU, gunakan type `ALL-ONT` jika tidak tahu tipe spesifiknya

## ONU Type yang Umum di ZTE C320

| Prefix SN | Brand      | Contoh Type  |
|-----------|-----------|--------------|
| FHTT      | Fiberhome  | ALL-ONT      |
| ZTEG      | ZTE        | ZTE-F609, ZTE-F660, ALL-ONT |
| HWTC      | Huawei     | ALL-ONT, HG8243C-OPEN |
| RCMG      | RouterCom  | ALL-ONT      |
| ZICG      | ZTE        | ALL-ONT      |
