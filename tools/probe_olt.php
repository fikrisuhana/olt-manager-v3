<?php
/**
 * OLT Probe Script - untuk verifikasi prompt dan output format
 * Usage: php tools/probe_olt.php
 */

$host = '136.1.1.200';
$port = 23;
$user = 'zte';
$pass = 'zte';

echo "Connecting to {$host}:{$port}...\n";

$sock = @fsockopen($host, $port, $errno, $errstr, 10);
if (!$sock) {
    die("FAILED: {$errstr} ({$errno})\n");
}
echo "Connected!\n";
stream_set_timeout($sock, 5);

function readUntil($sock, array $prompts, int $timeout = 8): string {
    $buf = '';
    $start = microtime(true);
    while (microtime(true) - $start < $timeout) {
        $chunk = @fread($sock, 4096);
        if ($chunk !== false && $chunk !== '') {
            // Strip IAC
            $clean = preg_replace('/\xFF[\xFB-\xFF][\x00-\xFF]/', '', $chunk);
            // Also strip raw IAC sequences
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
            $buf .= $clean;
            foreach ($prompts as $p) {
                if (stripos($buf, $p) !== false) return $buf;
            }
        }
        // Handle IAC negotiation inline
        usleep(100000);
    }
    return $buf;
}

function sendCmd($sock, string $cmd): void {
    fwrite($sock, $cmd . "\r\n");
    usleep(300000);
}

// --- Login ---
echo "\n[RAW LOGIN SEQUENCE]\n";
$resp = readUntil($sock, ['Username:', 'login:', 'user:'], 10);
echo ">>> After connect:\n" . $resp . "\n";

sendCmd($sock, $user);
$resp = readUntil($sock, ['Password:', 'password:'], 5);
echo ">>> After username:\n" . $resp . "\n";

sendCmd($sock, $pass);
$resp = readUntil($sock, ['#', '>', 'ZXAN', '%'], 6);
echo ">>> After password:\n" . $resp . "\n";

// Disable pager
sendCmd($sock, 'terminal length 0');
$resp = readUntil($sock, ['#', 'ZXAN'], 5);
echo ">>> After 'terminal length 0':\n" . $resp . "\n";

// --- Show uncfg ---
echo "\n[SHOW GPON ONU UNCFG]\n";
sendCmd($sock, 'show gpon onu uncfg');
$resp = readUntil($sock, ['#', 'ZXAN#'], 15);
echo $resp . "\n";

// --- Show onu state (first 20 lines) ---
echo "\n[SHOW GPON ONU STATE]\n";
sendCmd($sock, 'show gpon onu state');
$resp = readUntil($sock, ['#', 'ZXAN#'], 20);
$lines = array_slice(explode("\n", $resp), 0, 25);
echo implode("\n", $lines) . "\n";

// --- Show version ---
echo "\n[SHOW VERSION]\n";
sendCmd($sock, 'show version');
$resp = readUntil($sock, ['#', 'ZXAN#'], 5);
echo $resp . "\n";

fwrite($sock, "exit\r\n");
fclose($sock);
echo "\nDone.\n";
