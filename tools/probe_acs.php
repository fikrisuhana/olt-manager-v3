<?php
$base = "http://136.1.1.8";
$user = "admin";
$pass = "darkim091281";

function req(string $url, ?string $user = null, ?string $pass = null, string $method = 'GET', ?array $body = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($user) curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => substr($resp ?: '', 0, 1000), 'err' => $err];
}

echo "=== HTTP Port 80 ===\n";
$r = req("{$base}/");
echo "HTTP {$r['code']}: " . substr($r['body'], 0, 200) . "\n\n";

echo "=== GenieACS Port 7557 root ===\n";
$r = req("{$base}:7557/");
echo "HTTP {$r['code']}: " . $r['body'] . "\n\n";

echo "=== GenieACS GET /devices (no auth) ===\n";
$r = req("{$base}:7557/devices?limit=1");
echo "HTTP {$r['code']}: " . $r['body'] . "\n\n";

echo "=== GenieACS GET /devices (with auth) ===\n";
$r = req("{$base}:7557/devices?limit=2", $user, $pass);
echo "HTTP {$r['code']}: " . $r['body'] . "\n\n";

echo "=== Port 3000 ===\n";
$r = req("{$base}:3000/");
echo "HTTP {$r['code']}: " . substr($r['body'], 0, 300) . "\n\n";

echo "=== Port 3000 /api ===\n";
$r = req("{$base}:3000/api", $user, $pass);
echo "HTTP {$r['code']}: " . $r['body'] . "\n\n";

echo "=== Port 443 HTTPS ===\n";
$r = req("https://136.1.1.8/");
echo "HTTP {$r['code']}: " . substr($r['body'], 0, 200) . "\n";
