// gpon-manager — gm-credentials
// Dipush otomatis dari halaman "Kebijakan ACS" gpon-manager. JANGAN edit di UI GenieACS.
//
// Memaksa akun login web modem (akun "user" dan akun admin/super) sesuai pengaturan
// gpon-manager. Model yang menolak/abaikan perubahan akun dikecualikan lewat
// precondition preset (diatur di halaman Kebijakan ACS), bukan di sini.
// args: [userName, userPass, adminName, adminPass]  (kosong = tidak disentuh)
//
// Pola: path kandidat per vendor ditemukan 1x sehari; hanya path yang BENAR-BENAR ada
// di modem itu yang ditulis (menulis path yang tak ada → fault 9005 & batch gagal).
// Ditulis maksimal 1x per hari — tidak membandingkan nilai lama, karena banyak firmware
// membaca balik password sebagai kosong/tersamar (perbandingan akan loop tiap inform).
// Catatan: Huawei menolak password < 8 karakter.

const userName  = String(args[0] || "");
const userPass  = String(args[1] || "");
const adminName = String(args[2] || "");
const adminPass = String(args[3] || "");
const daily     = Date.now(86400000);

const PATHS = {
  userName: [
    "InternetGatewayDevice.X_CU_Function.Web.UserName",
    "InternetGatewayDevice.UserInterface.X_ZTE-COM_WebUserInfo.UserName",
    "InternetGatewayDevice.UserInterface.X_HW_WebUserInfo.1.UserName",
    "InternetGatewayDevice.DeviceInfo.X_FH_Account.X_FH_WebUserInfo.WebUsername",
    "InternetGatewayDevice.User.2.Username",
    "InternetGatewayDevice.X_ZTE-COM_UserInterface.X_ZTE-COM_WebUserInfo.UserName",
    "InternetGatewayDevice.X_Authentication.Account.UserName",
  ],
  userPass: [
    "InternetGatewayDevice.X_CU_Function.Web.UserPassword",
    "InternetGatewayDevice.UserInterface.X_ZTE-COM_WebUserInfo.UserPassword",
    "InternetGatewayDevice.UserInterface.X_HW_WebUserInfo.1.Password",
    "InternetGatewayDevice.DeviceInfo.X_FH_Account.X_FH_WebUserInfo.WebPassword",
    "InternetGatewayDevice.User.2.Password",
    "InternetGatewayDevice.X_ZTE-COM_UserInterface.X_ZTE-COM_WebUserInfo.UserPassword",
    "InternetGatewayDevice.X_Authentication.Account.Password",
  ],
  adminName: [
    "InternetGatewayDevice.X_CU_Function.Web.AdminName",
    "InternetGatewayDevice.UserInterface.X_ZTE-COM_WebUserInfo.AdminName",
    "InternetGatewayDevice.UserInterface.X_HW_WebUserInfo.2.UserName",
    "InternetGatewayDevice.DeviceInfo.X_CMCC_TeleComAccount.Username",
    "InternetGatewayDevice.DeviceInfo.X_FH_Account.X_FH_WebUserInfo.WebSuperUsername",
    "InternetGatewayDevice.User.1.Username",
    "InternetGatewayDevice.X_Authentication.WebAccount.UserName",
    "InternetGatewayDevice.X_ZTE-COM_UserInterface.X_ZTE-COM_WebUserInfo.AdminName",
  ],
  adminPass: [
    "InternetGatewayDevice.X_CU_Function.Web.AdminPassword",
    "InternetGatewayDevice.UserInterface.X_ZTE-COM_WebUserInfo.AdminPassword",
    "InternetGatewayDevice.UserInterface.X_HW_WebUserInfo.2.Password",
    "InternetGatewayDevice.DeviceInfo.X_CMCC_TeleComAccount.Password",
    "InternetGatewayDevice.DeviceInfo.X_FH_Account.X_FH_WebUserInfo.WebSuperPassword",
    "InternetGatewayDevice.User.1.Password",
    "InternetGatewayDevice.X_Authentication.WebAccount.Password",
    "InternetGatewayDevice.DeviceInfo.X_CT-COM_TeleComAccount.Password",
    "InternetGatewayDevice.X_ZTE-COM_UserInterface.X_ZTE-COM_WebUserInfo.AdminPassword",
  ],
};
const TARGET = {userName, userPass, adminName, adminPass};

for (const key of Object.keys(PATHS)) {
  const target = TARGET[key];
  if (!target) continue;
  for (const p of PATHS[key]) {
    // Temukan path (1x sehari). Path yang tidak ada di firmware ini → size 0 → dilewati.
    const probe = declare(p, {path: daily});
    if (probe.size) {
      declare(p, {value: daily}, {value: target});
    }
  }
}
