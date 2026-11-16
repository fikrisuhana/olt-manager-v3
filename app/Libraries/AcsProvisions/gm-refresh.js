// gpon-manager — gm-refresh
// Dipush otomatis dari halaman "Kebijakan ACS" gpon-manager. JANGAN edit di UI GenieACS.
//
// Rutin membaca info modem yang ditampilkan gpon-manager (PPPoE, IP WAN, redaman RX,
// uptime, SSID, perangkat terhubung) supaya status di aplikasi tidak basi.
// args: [intervalMenit]
//   - struktur parameter (path) ditemukan ulang 1x sehari
//   - nilai dibaca ulang tiap intervalMenit (dibatasi juga oleh interval inform modem)

const mins  = Math.max(parseInt(args[0], 10) || 60, 5);
const t     = Date.now(mins * 60000);
const daily = Date.now(86400000);

const igd = declare("InternetGatewayDevice.ManagementServer.ConnectionRequestURL", {value: 1}).size;

if (igd) {
  // Struktur WAN + antarmuka PON vendor (X_FH_/X_ZTE-COM_/X_GponInterafceConfig ...)
  declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANPPPConnection.*.*", {path: daily});
  declare("InternetGatewayDevice.WANDevice.*.WANConnectionDevice.*.WANIPConnection.*.*", {path: daily});
  declare("InternetGatewayDevice.WANDevice.*.*.*", {path: daily});
  // WiFi + perangkat terhubung
  declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.*.SSID", {path: daily, value: t});
  declare("InternetGatewayDevice.LANDevice.1.WLANConfiguration.*.Enable", {path: daily, value: t});
  declare("InternetGatewayDevice.LANDevice.1.Hosts.Host.*.*", {path: t, value: t});
} else {
  declare("Device.Hosts.Host.*.*", {path: t, value: t});
  declare("Device.WiFi.SSID.*.SSID", {path: daily, value: t});
}

// Virtual parameter (multi-vendor) yang dibaca gpon-manager
const vps = ["pppoeUsername", "AddressWanPPP", "AddressWanIP", "RXPower", "gettemp",
             "getdeviceuptime", "getpppuptime", "getponmode", "MacWAN"];
for (const vp of vps) {
  declare("VirtualParameters." + vp, {value: t});
}
