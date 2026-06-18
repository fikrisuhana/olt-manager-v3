<?php
/**
 * GenieACS API probe - verifikasi endpoint dan struktur data
 */
$acsApi = "http://136.1.1.8:7557";

function req(string $url, string $method = 'GET', ?array $body = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $resp];
}

// Ambil semua device dan analisis struktur
echo "=== GET /devices (limit 1) ===\n";
$r = req("{$acsApi}/devices?limit=1");
echo "HTTP {$r['code']}\n";
$devices = json_decode($r['body'], true);
if ($devices) {
    $d = $devices[0];
    echo "Device ID: " . $d['_id'] . "\n";
    echo "Keys level-1: " . implode(', ', array_keys($d)) . "\n\n";

    // Cari field SN
    if (isset($d['DeviceID'])) {
        echo "DeviceID fields:\n";
        print_r($d['DeviceID']);
    }
    // Cari di InternetGatewayDevice.DeviceInfo
    $sn = $d['InternetGatewayDevice']['DeviceInfo']['SerialNumber']['_value'] ??
          $d['DeviceID']['SerialNumber'] ?? 'N/A';
    echo "\nSerial Number: $sn\n";
}

echo "\n=== GET /devices (DeviceID fields) ===\n";
$r = req("{$acsApi}/devices?limit=2&projection=_id,DeviceID,_lastInform");
$devices = json_decode($r['body'], true);
foreach (($devices ?? []) as $d) {
    echo "ID: " . $d['_id'] . "\n";
    if (isset($d['DeviceID'])) {
        echo "DeviceID.SerialNumber: " . ($d['DeviceID']['SerialNumber']['_value'] ?? '?') . "\n";
        echo "DeviceID.OUI: " . ($d['DeviceID']['OUI']['_value'] ?? '?') . "\n";
        echo "DeviceID.ProductClass: " . ($d['DeviceID']['ProductClass']['_value'] ?? '?') . "\n";
    }
    echo "Last Inform: " . ($d['_lastInform'] ?? '?') . "\n\n";
}

echo "\n=== Search by SN (FHTTC0197EFD) ===\n";
$query = urlencode(json_encode(['DeviceID.SerialNumber' => 'FHTTC0197EFD']));
$r = req("{$acsApi}/devices?query={$query}&projection=_id,DeviceID");
echo "HTTP {$r['code']}\n" . $r['body'] . "\n";

echo "\n=== Count all devices ===\n";
$r = req("{$acsApi}/devices?limit=1000&projection=_id");
$all = json_decode($r['body'], true);
echo "Total devices: " . count($all ?? []) . "\n";
