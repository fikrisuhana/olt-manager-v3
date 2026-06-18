<?php
/**
 * OLT Probe 2 - explore ONU detail commands
 */

$host = '136.1.1.200'; $port = 23; $user = 'zte'; $pass = 'zte';

$sock = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$sock) die("FAILED: {$errstr}\n");
stream_set_timeout($sock, 5);

function read($sock, array $prompts, int $t = 10): string {
    $buf = ''; $start = microtime(true);
    while (microtime(true) - $start < $t) {
        $chunk = @fread($sock, 4096);
        if ($chunk !== false && $chunk !== '') {
            $buf .= preg_replace('/\xFF[\xFB-\xFF][\x00-\xFF]/', '', $chunk);
            foreach ($prompts as $p) if (stripos($buf, $p) !== false) return $buf;
        }
        usleep(100000);
    }
    return $buf;
}
function cmd($sock, string $c, int $t = 8): string {
    fwrite($sock, $c . "\r\n"); usleep(400000);
    return read($sock, ['ZXAN#', 'ZXAN('], $t);
}

// Login
read($sock, ['Username:'], 10);
fwrite($sock, $user . "\r\n"); usleep(300000);
read($sock, ['Password:'], 5);
fwrite($sock, $pass . "\r\n"); usleep(300000);
read($sock, ['ZXAN#'], 8);
cmd($sock, 'terminal length 0');

echo "=== show gpon onu baseinfo gpon-olt_1/1/1 ===\n";
echo cmd($sock, 'show gpon onu baseinfo gpon-olt_1/1/1', 15) . "\n";

echo "=== show gpon onu sn-bind gpon-olt_1/1/1 ===\n";
echo cmd($sock, 'show gpon onu sn-bind gpon-olt_1/1/1', 15) . "\n";

echo "=== show gpon onu detail-info gpon-onu_1/1/1:1 ===\n";
echo cmd($sock, 'show gpon onu detail-info gpon-onu_1/1/1:1', 10) . "\n";

echo "=== show pon power attenuation gpon-onu_1/1/1:1 ===\n";
echo cmd($sock, 'show pon power attenuation gpon-onu_1/1/1:1', 8) . "\n";

echo "=== show interface gpon-onu_1/1/1:1 ===\n";
echo cmd($sock, 'show interface gpon-onu_1/1/1:1', 8) . "\n";

echo "=== show gpon remote-onu interface gpon-onu_1/1/1:1 ===\n";
echo cmd($sock, 'show gpon remote-onu interface gpon-onu_1/1/1:1', 8) . "\n";

fwrite($sock, "exit\r\n");
fclose($sock);
