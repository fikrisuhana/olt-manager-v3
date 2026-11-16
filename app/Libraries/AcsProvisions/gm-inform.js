// gpon-manager — gm-inform
// Dipush otomatis dari halaman "Kebijakan ACS" gpon-manager. JANGAN edit di UI GenieACS,
// perubahan akan ditimpa saat kebijakan disimpan ulang.
//
// Memaksa modem rutin lapor (periodic inform) dan bisa dihubungi ACS kapan saja
// (Connection Request), tanpa layanan luar / ext apa pun.
// args: [intervalDetik, crUsername, crPassword, acsUrl]
//   - nilai kosong / 0 = parameter itu tidak disentuh
//   - acsUrl opsional: hanya diisi kalau mau memindahkan modem ke URL ACS lain
// Ditulis maksimal 1x per hari per modem (timestamp harian), tidak tiap inform.

const interval = parseInt(args[0], 10) || 0;
const crUser   = String(args[1] || "");
const crPass   = String(args[2] || "");
const acsUrl   = String(args[3] || "");
const daily    = Date.now(86400000);

// ConnectionRequestURL selalu ikut di pesan Inform → sudah ada di cache, jadi aman
// dipakai untuk menentukan data model (TR-098 vs TR-181) tanpa query ke modem.
const igd  = declare("InternetGatewayDevice.ManagementServer.ConnectionRequestURL", {value: 1});
const root = igd.size ? "InternetGatewayDevice" : "Device";
const ms   = root + ".ManagementServer";

if (interval > 0) {
  declare(ms + ".PeriodicInformEnable", {value: daily}, {value: true});
  declare(ms + ".PeriodicInformInterval", {value: daily}, {value: interval});
}
if (crUser) declare(ms + ".ConnectionRequestUsername", {value: daily}, {value: crUser});
if (crPass) declare(ms + ".ConnectionRequestPassword", {value: daily}, {value: crPass});
if (acsUrl) declare(ms + ".URL", {value: daily}, {value: acsUrl});
