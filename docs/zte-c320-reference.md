# ZTE C320 — Referensi Command & Skema VLAN (TERVERIFIKASI di OLT 136.1.1.210)

> OLT: **ZTE C320**, telnet port 23, login `zte`/`zte`, enable `zte`. Firmware **v2.x** (v2.1).
> Prompt: `ZXAN>` (user) → `ZXAN#` (enable) → `ZXAN(config)#` (conf t) → `ZXAN(config-if)#` (interface).
> Konteks pon-onu-mng v2.1: `ZXAN(gpon-onu-mng 1/1/1:15)#` (BUKAN `config-pon-onu` gaya v1.2).
> Diverifikasi 2026-07-23 saat kerja FH-on-ZTE (register FiberHome di OLT ZTE) + diagnosa ONU tak-tembus-ACS.

## Listing ONU
```
show gpon onu state                              ← semua ONU: interface, admin, oper, phase(working/DyingGasp), mode
show gpon onu baseinfo gpon-olt_1/1/1            ← per PON: OnuIndex, Type, AuthInfo (SN:...), State  ← ADA SN
show gpon onu detail-info gpon-onu_1/1/1:15      ← 1 ONU: State, Phase state, Config state, Online Duration, Distance, riwayat offline
show running-config interface gpon-onu_1/1/1:15  ← config interface: name, tcont, gemport, service-port
```
- `baseinfo` butuh argumen **port** (`gpon-olt_B/S/P`); tanpa argumen → kosong.
- `state`/`detail-info` per-ONU pakai `gpon-onu_B/S/P:I` (BUKAN `gpon-olt`).
- ⚠️ Baca via telnet kadang **glitch** (output ke-swallow → keliatan kosong). SELALU baca 2x utk konfirmasi sebelum ambil keputusan.

## Skema VLAN — "BEDA PON BEDA JALUR" (penting!)
Di jaringan ini ONU beda pakai VLAN internet beda (jalur/BRAS beda), tapi VLAN mgmt/ACS umumnya sama:
- **VLAN 100 = ACS/mgmt** (label `1_TR069_VOIP_R_VID_100`). Umum di banyak ONU.
- **VLAN internet = beda per jalur**: mis. 155, 145, 210. (label `2_INTERNET_R_VID_155`, dst).
  Contoh nyata OLT 210 PON 1/1/1: `:17` mgmt=100+internet=155; `:15` internet=155+145 (dua kanal, TANPA 100).
- **Jangan hardcode/asumsi VLAN dari ONU tetangga** — cek service-port ONU-nya sendiri dulu.

## Register FH ONU di OLT ZTE (FH-on-ZTE) — pola yang TEMBUS ke ACS
Interface (switching OLT):
```
conf t
interface gpon-olt_1/1/1
  onu <idx> type <ALL-ONT|ZTE-F601|..> sn <SN>          ← ALL-ONT = generic (aman utk FH)
exit
interface gpon-onu_1/1/1:<idx>
  name <NAMA>
  tcont 1 name tcont profile <200M|..>                  ← profil kegedean → "Parameter exceeds range" (cascade gagal!)
  gemport 1 name gemport tcont 1
  service-port 1 vport 1 user-vlan 100 vlan 100          ← mgmt/ACS
  service-port 2 vport 1 user-vlan <internet> vlan <internet>
exit
```
pon-onu-mng (OMCI ke ONU) — inti "tembus":
```
pon-onu-mng gpon-onu_1/1/1:<idx>
  service acs gemport 1 vlan 100                          ← kanal ACS (WAJIB biar nyampe GenieACS)
  service int gemport 1 vlan <internet>                  ← v2.x FH: 'int'  (v2.x+PPPoE ZTE: 'ppp'; v1.x: 'hsi')
  vlan port veip_1 mode hybrid                            ← bridge VLAN ke veip → ONU FH pakai TR-069/PPPoE sendiri
exit
exit
write
```
> ONU FiberHome dapat ACS URL via **DHCP option-43** di VLAN 100 (bukan tr069-mgmt). ONU ZTE butuh `wan-ip 1 mode ... dhcp` + `tr069-mgmt 1 acs <url>`.

## Diagnosa "ONU ready di OLT tapi GAK ada di ACS"
1. `show running-config interface gpon-onu_..:I` — cek service-port. **Kurang service-port VLAN 100 (mgmt)?** → itu sebabnya (cuma punya VLAN internet).
2. `show gpon onu detail-info ..` — `Config state`:
   - **`fail` BUKAN blocker mutlak** — ONU bisa `Config state: fail` TAPI tetap tembus ke ACS (contoh indar `:19`). Jadi jangan panik lihat `fail`.
   - `Phase state: working` = link up; `DyingGasp`/flap (riwayat `SFi`) = link fiber marginal (masalah fisik, bukan config).
3. Kalau mgmt (100) ilang: tambah `service-port <idx-bebas> vport 1 user-vlan 100 vlan 100` + `service acs gemport 1 vlan 100` + `vlan port veip_1 mode hybrid`.
   ⚠️ Sebagian profil ONU cap jumlah service-port (mis. 2) — nambah port ke-3 BISA memicu `Config state: fail`. Verifikasi setelah nulis.

## BUG cascade (sudah difix di ZteDriver::registerOnu, commit eb8759a)
`tcont` profil kegedean → `"Parameter exceeds range"` (tadinya LOLOS deteksi yg cuma cek "Error"/"Invalid")
→ `gemport` tak terbentuk → `service acs/int gemport 1` gagal diam-diam → ONU incomplete tapi lapor sukses.
Kini `isCliError()` deteksi luas + register return `{partial, warnings}` + UI amber "TIDAK LENGKAP".
```
%Code 66661: The service is already existed.   ← service-port index sudah dipakai (idempotent, aman)
%Code 63869-GPONRM : Record already exists.     ← pon-onu-mng service sudah ada (idempotent, aman)
```
