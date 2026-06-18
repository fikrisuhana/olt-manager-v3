<?php
// Lihat field lengkap WANPPPConnection.1 di WANConnectionDevice.2
$acsApi = "http://136.1.1.8:7557";
function req($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code'=>$c,'body'=>$r];
}

$r = req("{$acsApi}/devices?limit=1&projection=_id,InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1");
$devices = json_decode($r['body'], true);
$d = $devices[0] ?? [];
echo "Device: " . $d['_id'] . "\n";
$ppp = $d['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice']['2']['WANPPPConnection']['1'] ?? [];
echo "PPP fields:\n";
foreach ($ppp as $key => $val) {
    if (!is_array($val)) continue;
    if (str_starts_with($key, '_')) continue;
    echo "  {$key} = " . ($val['_value'] ?? json_encode($val)) . "\n";
}
