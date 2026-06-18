<?php
$acsApi = "http://136.1.1.8:7557";

function req(string $url, string $method = 'GET', ?array $body = null, bool $verbose = false): array {
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

// Lihat struktur _deviceId
echo "=== Check _deviceId field structure ===\n";
$r = req("{$acsApi}/devices?limit=1&projection=_id,_deviceId,_lastInform");
$devices = json_decode($r['body'], true);
$d = $devices[0] ?? [];
echo "ID: " . $d['_id'] . "\n";
echo "_deviceId fields:\n";
print_r($d['_deviceId'] ?? []);

// Search by _deviceId.SerialNumber
echo "\n=== Search by _deviceId.SerialNumber ===\n";
$sn = 'FHTTC0197EFD';
$query = urlencode(json_encode(['_deviceId._SerialNumber' => $sn]));
$r = req("{$acsApi}/devices?query={$query}&projection=_id,_deviceId");
echo "HTTP {$r['code']}: " . $r['body'] . "\n";

// Try different search patterns
echo "\n=== Search by ID suffix (contains SN) ===\n";
// GenieACS device ID ends with SN
$query2 = urlencode(json_encode(['_id' => ['$regex' => "FHTTC0197EFD"]]));
$r2 = req("{$acsApi}/devices?query={$query2}&projection=_id,_deviceId");
echo "HTTP {$r2['code']}: " . $r2['body'] . "\n";

// Get one device's WAN structure
echo "\n=== WANDevice structure of first device ===\n";
$deviceId = urlencode($devices[0]['_id'] ?? '000AC2-HG6145D2-FHTTC0197EFD');
$r = req("{$acsApi}/devices?limit=1&projection=_id,InternetGatewayDevice.WANDevice");
$devices2 = json_decode($r['body'], true);
$wan = $devices2[0]['InternetGatewayDevice']['WANDevice'] ?? [];
echo "WANDevice keys: " . implode(', ', array_keys($wan)) . "\n";
if (isset($wan['1'])) {
    $wanDev = $wan['1']['WANConnectionDevice'] ?? [];
    echo "WANConnectionDevice keys: " . implode(', ', array_keys($wanDev)) . "\n";
    foreach ($wanDev as $idx => $wcd) {
        if (is_array($wcd) && is_numeric($idx)) {
            echo "  WANConnectionDevice.{$idx} keys: " . implode(', ', array_keys($wcd)) . "\n";
            // Check for WANPPPConnection
            foreach (['WANPPPConnection', 'WANIPConnection'] as $connType) {
                if (isset($wcd[$connType])) {
                    echo "  -> {$connType} indices: " . implode(', ', array_filter(array_keys($wcd[$connType]), 'is_numeric')) . "\n";
                    foreach ($wcd[$connType] as $ci => $conn) {
                        if (is_numeric($ci) && is_array($conn)) {
                            $enabled = $conn['Enable']['_value'] ?? '?';
                            $type    = $conn['ConnectionType']['_value'] ?? '?';
                            $status  = $conn['ConnectionStatus']['_value'] ?? '?';
                            echo "     [{$ci}] Enable={$enabled} Type={$type} Status={$status}\n";
                        }
                    }
                }
            }
        }
    }
}
