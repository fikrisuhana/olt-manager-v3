# Fiberhome OLT — Referensi Command (TERVERIFIKASI di OLT 136.1.1.103)

> OLT: **AN6000-2** (bukan AN5516). Login telnet port 23: `GEPON` / `GEPON`, enable `GEPON`.
> Banner = ASCII art FIBERHOME + indikator "Master". CLI **ala Cisco** (config mode `Admin(config)#`),
> BUKAN gaya `cd gpononu\` seperti AN5516 di repo GitHub.
> Diverifikasi langsung 2026-07-23 termasuk siklus register→verify→delete (state dikembalikan bersih, tanpa `save`).

## Hardware (show card)
```
---------------------AN6000-2---------------------
CARD   EXIST  CONFIG  DETECT   DETAIL
  1     YES    GFOA    GFOA     MATCH      ← kartu GPON
  2     YES    GFOA    GFOA     MATCH      ← kartu GPON (ONU uji ada di sini)
  3/4   YES    HSUD    HSUD     MATCH/M,S  ← uplink master/slave
  5     YES    PIDH    PIDH     MATCH      ← power
  7     YES    FAN     FAN      MATCH
```

## Numbering — frame/slot/port(pon)/onuid
- Format interface: `frame/slot/pon` mis. `1/2/16` (frame 1, slot 2, PON 16).
- Pemetaan ke schema app gpon-manager: **board=frame(1), slot=slot, port=pon, onu_index=onuid**.
  (kolom DB existing board/slot/port/onu_index dipakai apa adanya)

## Login & mode
```
Login: GEPON
Password: GEPON
User> enable
Password: GEPON
Admin#                       ← privileged
Admin# config
Admin(config)#               ← config mode
Admin(config)# terminal length 0    ← matikan paging (WAJIB sebelum show besar)
```
Prompt per konteks:
- privileged: `Admin#`
- config: `Admin(config)#`
- interface pon: `Admin(config-if-pon-1/2/16)#`

## Discovery — ONU belum authorized (getUnconfiguredOnus)
```
Admin(config)# show discovery                 ← semua
Admin(config)# show discovery 1/2/16          ← per pon
```
Output:
```
----- ONU Unauth Table, SLOT = 2, PON = 16, ITEM = 1 -----
No  OnuType   PhyId        PhyPwd    LogicId    LogicPwd  Why Vendor RealType      SoftWareVersion HardWareVersion
1   HG6145E   ZTEGdce2cf07 GDCE2CF07 123456789  123456    1   ZTEG   F6600PV9.0.12 V9.0.10P5N30B   V9.0.12
```
→ PhyId = serial (SN). OnuType & Vendor & RealType tersedia.

## Authorization table — ONU terdaftar (getRegisteredOnus)
```
Admin(config)# show authorization             ← semua pon
Admin(config)# show authorization 1/2/16       ← per pon
```
Output:
```
-----  ONU Auth Table, SLOT = 2, PON = 16, ITEM = 2 -----
Slot Pon Onu OnuType  ST Lic OST PhyId        ... Vendor RealType
2    16  1   HG6145E  A  0   dn  FHTT9d308858     FHTT   HG6145D2
2    16  3   HG6145E  A  0   up  ZTEGd8161ada     ZTEG   F672YV9.1
```
→ ST: A=Authorized P=Preauth R=Reserved. OST: up=online, dn=offline.

## REGISTER ONU  ✅ terverifikasi
PON dalam mode whitelist (bukan NO_AUTH) → WAJIB pakai `whitelist add`, `authorize` ditolak.
```
Admin(config)# whitelist add phy-id <SN> type <ONU_TYPE> slot <slot> pon <pon> onuid <index>
# contoh (diverifikasi):
Admin(config)# whitelist add phy-id ZTEGdce2cf07 type HG6145E slot 2 pon 16 onuid 2
```
- `type` WAJIB (mis. HG6145E). Ambil dari kolom OnuType hasil `show discovery`.
- Tanpa slot/pon/onuid → OLT auto-assign; dengan → kita kontrol index.
- Setelah masuk whitelist, ONU auto-authorize (muncul di auth table).

## VLAN / SERVICE  ✅ terverifikasi tulis (register onu 2 bench → masuk running-config → dihapus)
Dikonfig di context `interface pon`, command `onu wan-cfg <onuid> ...`.

**Grammar lengkap (diverifikasi via `?` step-by-step):**
```
onu wan-cfg <onuid> index <n> mode <MODE> type <route|bridge> <vid> <cos> nat <enable|disable>
    qos <enable|disable> [vlanmode ..] [qinq ..] dsp <dhcp|static|pppoe|null> [params]
    [active enable|disable] [service-type ..] [entries <n>] [fe1-4|ssid1-8|10glan]
```
- MODE: `tr069`, `internet`, `tr069-internet`, `voip`, `voip-internet`, `iptv`, `other`,
  `multi`, `radius`, `radius-internet`, `unicast-iptv`, `multicast-iptv`.
- `<cos>` = 0-7, atau `65535` untuk default/untagged-priority.
- `type route` = routed (NAT di ONU), `type bridge` = bridge.

**Contoh yang DIVERIFIKASI TULIS (masuk running-config, "set ok!"):**
```
onu wan-cfg 2 index 1 mode tr069    type route 100 65535 nat disable qos disable dsp dhcp entries 0
onu wan-cfg 2 index 2 mode internet type route 210 65535 nat enable  qos disable dsp dhcp entries 0
onu description 2 <nama> id 0
```
Running-config menyimpannya (bentuk singkat): `onu wan-cfg 2 ind 1 mode tr069 ty r 100 ...`,
`onu wan-cfg 2 ind 2 mode inter ty r 210 ...`.

**Grammar PPPoE (diverifikasi tulis):**
```
onu wan-cfg <id> index <n> mode internet type route <vid> <cos> nat enable qos disable \
    dsp pppoe pro <enable|disable> <username> <password> <servname|null> <auto|payload|manual> \
    entries <n> <fe1..fe4|ssid1..ssid8|10glan>...
```
Password otomatis DIENKRIPSI OLT saat disimpan (`testpass` → running-config `key:*9+*.=++`).
→ getOnuConfig hanya bisa baca username, bukan password (sama seperti ZTE).

**Strategi gpon-manager (KEPUTUSAN FINAL user — TUJUAN APP: "tembus" lintas-merk):**
- **ONU Fiberhome (SN FHTT/FHSC)** → `onu wan-cfg`: kanal `index 1 mode tr069 <VLAN-ACS> dsp dhcp`
  (ACS) + kanal `index 2 mode internet <VLAN-INTERNET> dsp pppoe <user> <pass>` = **PPPoE FULL DI OLT**
  (kebalikan dari OLT ZTE). Verified.
- **ONU non-FH (ZTE/Huawei/dll)** → **BUKAN wan-cfg** (routed via OMCI FH TIDAK dihormati ONU non-FH →
  tak nyampe ACS). Pakai **`onu veip`** (bridge VLAN transparan ke veip) — lihat section VEIP di bawah.

## VEIP — bridge VLAN ke ONU non-FH  ✅ TERVERIFIKASI (kunci "tembus" lintas-merk)
Masalah: ONU non-FH (ZTE) di OLT FH pakai `onu wan-cfg ... type route` (routed WAN via OMCI FH) **TIDAK
nyampe ACS** — ONU non-FH gak bikin/gak pakai WAN routed FH itu buat agent TR-069-nya.
Bukti live OLT 103 PON 2/16: `:3` (F672Y, config veip) NYAMPE ACS (inform ~22 mnt);
`:4` (F6600P, config wan-cfg routed) TIDAK (inform 600+ mnt).

Solusi = bridge tiap VLAN transparan ke **veip** ONU (analog `vlan port veip_1 mode hybrid` di OLT ZTE).
ONU pakai router/agent TR-069 + PPPoE-nya SENDIRI (di-provision ACS), ambil ACS URL via DHCP option-43.

**Grammar interaktif (di context `interface pon f/s/p`) — TERVERIFIKASI TULIS:**
```
onu veip <onuid> cvlan-id <VLAN> cvlan-cos 65535 svlan-tpid 33024 svlan-vid <VLAN> svlan-cos 65535
```
- cvlan-id = svlan-vid = VLAN service (single-tag: S=C). `svlan-vid` WAJIB lewat `svlan-tpid 33024` dulu
  (kalau `svlan-vid` langsung → "% Unknown command"). cos 65535 = default/untagged-priority.
- OLT **auto-assign** onuveip index per cvlan unik: kanal ACS dibuat dulu → onuveip 1, internet → onuveip 2.
- Bentuk di running-config PANJANG (`onu veip 4 eth 1 onuveip 1 cvlan-tpid ... svlan-vid ...`) =
  **bentuk simpan/reboot**, DITOLAK bila diketik ulang ("this command only used for reboot to write config").
- OLT **nolak MODIF** veip existing → untuk re-config **hapus dulu**: `no onu veip <onuid> cvlan-id <VLAN>`.

Contoh (ACS 100 + internet 210):
```
interface pon 1/2/16
  no onu veip 4 cvlan-id 100
  onu veip 4 cvlan-id 100 cvlan-cos 65535 svlan-tpid 33024 svlan-vid 100 svlan-cos 65535   ← ACS/mgmt
  onu veip 4 cvlan-id 210 cvlan-cos 65535 svlan-tpid 33024 svlan-vid 210 svlan-cos 65535   ← internet
exit
save
```
Diimplement di `FiberhomeDriver::setService()` (cabang non-FH). getOnuConfig parse `onu veip` (onuveip 1=ACS, 2=internet).

> ⚠️ ACS 136.1.1.8 punya backend custom (`auto-configure-fiberhome`) yang juga auto-create WAN FH.
> Karena strategi = full di OLT untuk FH, pastikan device yang dikelola gpon-manager TIDAK dobel
> di-provision backend ACS (bisa tabrakan). Perlu koordinasi kalau device sama.

## DELETE ONU  ✅ terverifikasi
Di context `interface pon` (sudah ter-scope ke f/s/p):
```
Admin(config)# interface pon 1/2/16
Admin(config-if-pon-1/2/16)# no whitelist <onuid>     ← hapus whitelist + deauth sekaligus
# contoh (diverifikasi): no whitelist 2  →  "Deauth ONU 2 success."
```
Catatan: `no authorize <onuid>` = deauth saja (tanpa hapus whitelist → bisa auto re-auth).
`no whitelist <onuid>` sudah mencakup deauth. Terima juga `<onulist>` (mis. `1-10` / `1,3,5`) dan `all`.

## Config satu ONU (getOnuConfig / signal / info)
```
Admin(config)# show onu running-config 1/2/16 <onuid>   ← full cfg (parse wan-cfg → VLAN)
Admin(config)# show onu service 1/2/16 <onuid>           ← FE/VEIP/VOIP service info
Admin(config)# show onu summary                          ← ringkas semua ONU
Admin(config)# show onu state ...                         ← status
```
Sinyal optik: `show onu optical ...` — syntax masih rewel (config-mode "Please input correct
interface", pon-context `show onu optical 1` "Ambiguous"). PERLU ditemukan syntax pastinya.
Kandidat: `show optical <f/s/p>` (level port) atau `show onu optical <onuid>` dari konteks tertentu.

## Profiles (dropdown register)
```
Admin(config)# show gpon-dba-profile        ← DBA/bandwidth (setara TCONT ZTE)
Admin(config)# show gpon-onu-type-define    ← daftar tipe ONU didukung (besar; buat validasi type)
```

## Perbedaan kunci vs driver ZTE (utk implementasi)
| Aspek | ZTE C320 | Fiberhome AN6000 |
|---|---|---|
| Mode config | `conf t` → `interface gpon-onu_x/y/z:i` | `config` → `interface pon f/s/p` (ONU via `onu <cmd> <onuid>`) |
| Prompt | `...(config-if)#` | `Admin(config-if-pon-1/2/16)#` |
| List uncfg | `show gpon onu uncfg` | `show discovery` |
| List terdaftar | `show gpon onu baseinfo` per port | `show authorization` (semua sekaligus) |
| Register | `onu N type ALL-ONT sn SN` | `whitelist add phy-id SN type <TIPE> slot.. pon.. onuid..` |
| Tipe ONU | bisa `ALL-ONT` | WAJIB tipe spesifik (HG6145E, dll) |
| VLAN/service | `pon-onu-mng` service hsi/acs + wan-ip | `interface pon` → `onu wan-cfg <id> ind k mode tr069/inter ty r <vlan> ...` |
| Delete | `no onu N` | `interface pon` → `no whitelist <onuid>` |
| Paging off | `terminal length 0` | `terminal length 0` |
| Simpan | `write` | `save` |

## Catatan integrasi
- ONU di FH OLT ini campur brand (FHTT & ZTEG) — semua ditype-kan `HG6145E` di contoh. Tipe = model FH-equivalent.
- Untuk PPPoE tetap via **GenieACS/TR-069** (sesuai keinginan user "luruskan VLAN aja, sisanya ACS").
- Port TL1 (3337/3341/3339) CLOSED → hanya CLI.

## Sumber prior (GitHub, untuk pola AN5516 — CLI-nya beda dari AN6000 ini)
- https://github.com/raphaelrrl/olt_fiberhome (CLI AN5516, gaya `cd`)
- https://github.com/igorcardoso14/Fiberhome_TL1 (TL1)
