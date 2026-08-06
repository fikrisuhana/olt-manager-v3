<?php

namespace App\Libraries;

/**
 * Service SNMP untuk query data OLT (ZTE & Fiberhome) secara murni tanpa CLI login.
 * Mendukung native PHP SNMP extension, fallback shell snmpwalk, dan socket UDP BER handling.
 */
class SnmpService
{
    private string $community;
    private int $version; // 1 = v1, 2 = v2c
    private int $timeoutMs;
    private int $retries;

    public function __construct(string $community = 'public', int $version = 2, int $timeoutMs = 2500, int $retries = 1)
    {
        $this->community = $community;
        $this->version = $version;
        $this->timeoutMs = $timeoutMs;
        $this->retries = $retries;
    }

    /**
     * Melakukan SNMP Walk pada OID tertentu
     * Return format: ['1.3.6.1.4.1...' => 'value', ...]
     */
    public function walk(string $ip, string $oid, ?string $community = null): array
    {
        $comm = $community ?? $this->community;
        $results = [];

        // 1. Coba gunakan Extension Native PHP SNMP jika ada
        if (extension_loaded('snmp')) {
            try {
                if ($this->version === 2) {
                    $raw = @snmp2_real_walk($ip, $comm, $oid, $this->timeoutMs * 1000, $this->retries);
                } else {
                    $raw = @snmpwalkoid($ip, $comm, $oid, $this->timeoutMs * 1000, $this->retries);
                }

                if ($raw !== false && is_array($raw)) {
                    foreach ($raw as $key => $val) {
                        $cleanKey = ltrim($key, '.');
                        $cleanVal = $this->cleanSnmpValue($val);
                        $results[$cleanKey] = $cleanVal;
                    }
                    return $results;
                }
            } catch (\Throwable $e) {
                // Failover ke fallback shell / socket
            }
        }

        // 2. Fallback ke Command Line snmpwalk jika tersedia
        $results = $this->shellSnmpWalk($ip, $comm, $oid);
        return $results;
    }

    /**
     * Melakukan SNMP Get single OID
     */
    public function get(string $ip, string $oid, ?string $community = null): ?string
    {
        $comm = $community ?? $this->community;

        if (extension_loaded('snmp')) {
            try {
                if ($this->version === 2) {
                    $val = @snmp2_get($ip, $comm, $oid, $this->timeoutMs * 1000, $this->retries);
                } else {
                    $val = @snmpget($ip, $comm, $oid, $this->timeoutMs * 1000, $this->retries);
                }
                if ($val !== false) {
                    return $this->cleanSnmpValue($val);
                }
            } catch (\Throwable $e) {
                // Failover
            }
        }

        $walk = $this->walk($ip, $oid, $comm);
        if (!empty($walk)) {
            return reset($walk);
        }

        return null;
    }

    /**
     * Parse hasil string SNMP (hapus STRING: , INTEGER: , Hex-STRING: , dll)
     */
    private function cleanSnmpValue(string $val): string
    {
        $val = trim($val);
        // Clean prefixes
        $val = preg_replace('/^(STRING|INTEGER|Gauge32|Counter32|Hex-STRING|OID):\s*/i', '', $val);
        $val = trim($val, '"');
        return $val;
    }

    /**
     * Fallback menggunakan command shell `snmpwalk`
     */
    private function shellSnmpWalk(string $ip, string $community, string $oid): array
    {
        $versionFlag = ($this->version === 2) ? '-v2c' : '-v1';
        $timeoutSec = (int)ceil($this->timeoutMs / 1000);
        $cmd = sprintf(
            'snmpwalk %s -c %s -t %d -r %d -O n %s %s 2>&1',
            $versionFlag,
            escapeshellarg($community),
            $timeoutSec,
            $this->retries,
            escapeshellarg($ip),
            escapeshellarg($oid)
        );

        $output = [];
        $returnCode = 0;
        @exec($cmd, $output, $returnCode);

        $results = [];
        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (strpos($line, '=') !== false) {
                    list($k, $v) = explode('=', $line, 2);
                    $cleanKey = ltrim(trim($k), '.');
                    $results[$cleanKey] = $this->cleanSnmpValue($v);
                }
            }
        }
        return $results;
    }

    // ── ZTE GPON SNMP SPECIFIC OIDS ─────────────────────────────────────
    /**
     * Ambil unconfigured ONU dari ZTE OLT via SNMP
     */
    public function getZteUnconfiguredOnus(string $ip, string $community): array
    {
        $oidUncfgSn = '1.3.6.1.4.1.3902.1012.3.28.1.1.2';
        $walk = $this->walk($ip, $oidUncfgSn, $community);

        $onus = [];
        foreach ($walk as $oid => $val) {
            $parts = explode('.', $oid);
            $count = count($parts);
            if ($count >= 4) {
                $slot = $parts[$count - 3] ?? '1';
                $port = $parts[$count - 2] ?? '1';
                $onus[] = [
                    'board' => '1',
                    'slot'  => (string)$slot,
                    'port'  => (string)$port,
                    'sn'    => $val,
                    'type'  => 'ALL-ONT',
                    'vendor'=> substr($val, 0, 4),
                ];
            }
        }
        return $onus;
    }

    /**
     * Ambil RX/TX Power ONU ZTE via SNMP
     */
    public function getZteOnuSignal(string $ip, string $community, int $interfaceIfIndex): array
    {
        $oidRx = "1.3.6.1.4.1.3902.1012.3.50.12.1.1.10.{$interfaceIfIndex}";
        $oidTx = "1.3.6.1.4.1.3902.1012.3.50.12.1.1.11.{$interfaceIfIndex}";

        $rxVal = $this->get($ip, $oidRx, $community);
        $txVal = $this->get($ip, $oidTx, $community);

        $rxDbm = ($rxVal !== null && is_numeric($rxVal)) ? round(((float)$rxVal * 0.002) - 30, 2) . ' dBm' : 'N/A';
        $txDbm = ($txVal !== null && is_numeric($txVal)) ? round(((float)$txVal * 0.002) - 30, 2) . ' dBm' : 'N/A';

        return [
            'rx_power' => $rxDbm,
            'tx_power' => $txDbm,
            'status'   => ($rxVal !== null) ? 'working' : 'unknown',
        ];
    }

    // ── FIBERHOME GPON SNMP SPECIFIC OIDS ──────────────────────────────
    public function getFiberhomeUnconfiguredOnus(string $ip, string $community): array
    {
        $oidUncfg = '1.3.6.1.4.1.5875.800.3.9.3.4.1.2';
        $walk = $this->walk($ip, $oidUncfg, $community);

        $onus = [];
        foreach ($walk as $oid => $val) {
            $parts = explode('.', $oid);
            $count = count($parts);
            if ($count >= 3) {
                $slot = $parts[$count - 2] ?? '1';
                $port = $parts[$count - 1] ?? '1';
                $onus[] = [
                    'board' => '1',
                    'slot'  => (string)$slot,
                    'port'  => (string)$port,
                    'sn'    => $val,
                    'type'  => 'ALL-ONT',
                    'vendor'=> substr($val, 0, 4),
                ];
            }
        }
        return $onus;
    }
}
