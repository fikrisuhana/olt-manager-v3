<?php

namespace App\Libraries;

/**
 * GenieACS REST API integration untuk auto-provisioning WAN ONU via TR-069
 *
 * Diverifikasi dengan GenieACS di 136.1.1.8:7557
 * Device format Fiberhome: InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1
 */
class AcsService
{
    private string $baseUrl;
    private ?string $username;
    private ?string $password;

    // Path WAN per brand/model ONU (WANConnectionDevice index yang berbeda tiap brand)
    // Fiberhome (FH): WCD.2.WANPPPConnection.1 → diverifikasi langsung dari OLT
    // Mayoritas ZTE, Huawei, dll: WCD.1.WANPPPConnection.1 (biasanya)
    private const WAN_PATHS = [
        'fiberhome' => [
            'wcd_index' => '2',  // WANConnectionDevice index
            'ppp_index' => '1',  // WANPPPConnection index
        ],
        'zte' => [
            'wcd_index' => '1',
            'ppp_index' => '1',
        ],
        'default' => [
            'wcd_index' => '1',
            'ppp_index' => '1',
        ],
    ];

    public function __construct(array $acsConfig)
    {
        $this->baseUrl  = rtrim($acsConfig['url'], '/');
        $this->username = $acsConfig['username'] ?? null;
        $this->password = $acsConfig['password'] ?? null;
    }

    /**
     * Cari device di GenieACS berdasarkan ONU Serial Number.
     * Field yang benar: _deviceId._SerialNumber (bukan DeviceID.SerialNumber)
     */
    public function findDeviceBySn(string $sn): ?array
    {
        $query    = urlencode(json_encode(['_deviceId._SerialNumber' => $sn]));
        $response = $this->request('GET', "/devices?query={$query}&limit=1");

        if ($response['status'] === 200) {
            $devices = json_decode($response['body'], true);
            return !empty($devices) ? $devices[0] : null;
        }
        return null;
    }

    /**
     * Deteksi brand ONU dari manufacturer di GenieACS device.
     */
    public function getDeviceBrand(array $device): string
    {
        $mfr = strtolower($device['_deviceId']['_Manufacturer'] ?? '');
        if (str_contains($mfr, 'fiber') || str_contains($mfr, 'fh')) return 'fiberhome';
        if (str_contains($mfr, 'zte'))                                  return 'zte';
        if (str_contains($mfr, 'huawei'))                               return 'huawei';
        return 'default';
    }

    /**
     * Set PPPoE username dan password ONU via GenieACS.
     * Fiberhome: create WANPPPConnection jika belum ada, set VLAN + ServiceList lengkap.
     * Brand lain: set Username, Password, Enable saja.
     *
     * $extra: ['vlan_internet' => int]
     */
    public function provisionPppoe(string $deviceId, string $pppoeUser, string $pppoePass, string $brand = 'default', array $extra = []): array
    {
        $brand  = strtolower($brand);
        $paths  = self::WAN_PATHS[$brand] ?? self::WAN_PATHS['default'];
        $wcd    = $paths['wcd_index'];
        $ppp    = $paths['ppp_index'];
        $base   = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wcd}.WANPPPConnection.{$ppp}";
        $encodedId = rawurlencode($deviceId);

        if ($brand === 'fiberhome') {
            // Pastikan WAN PPP slot ada — return actual WCD index (bisa 1 jika fresh ONU)
            $actualWcd = $this->ensureFiberhomePppWan($encodedId, $wcd);
            $base      = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$actualWcd}.WANPPPConnection.{$ppp}";

            $vlanId   = (int)($extra['vlan_internet'] ?? 0);
            $connName = $vlanId ? "2_INTERNET_R_VID_{$vlanId}" : '2_INTERNET_R_VID';

            $paramValues = [
                ["{$base}.Enable",           true,        'xsd:boolean'],
                ["{$base}.ConnectionType",   'IP_Routed', 'xsd:string'],
                ["{$base}.NATEnabled",       true,        'xsd:boolean'],
                ["{$base}.X_FH_ServiceList", 'INTERNET',  'xsd:string'],
                ["{$base}.Username",         $pppoeUser,  'xsd:string'],
                ["{$base}.Password",         $pppoePass,  'xsd:string'],
            ];

            if ($vlanId > 0) {
                $paramValues[] = ["{$base}.VLANEnable", true,      'xsd:boolean'];
                $paramValues[] = ["{$base}.VLANID",     $vlanId,   'xsd:unsignedInt'];
                $paramValues[] = ["{$base}.Name",       $connName, 'xsd:string'];
            }

            $task     = ['name' => 'setParameterValues', 'parameterValues' => $paramValues];
            $response = $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=10000", $task);

            return [
                'success'  => in_array($response['status'], [200, 201, 202]),
                'status'   => $response['status'],
                'body'     => $response['body'],
                'wan_path' => $base,
            ];
        }

        // Default: hanya set Username, Password, Enable
        $task = [
            'name'            => 'setParameterValues',
            'parameterValues' => [
                ["{$base}.Username", $pppoeUser, 'xsd:string'],
                ["{$base}.Password", $pppoePass, 'xsd:string'],
                ["{$base}.Enable",   true,        'xsd:boolean'],
            ],
        ];

        $response = $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=8000", $task);

        return [
            'success'  => in_array($response['status'], [200, 201, 202]),
            'status'   => $response['status'],
            'body'     => $response['body'],
            'wan_path' => $base,
        ];
    }

    /**
     * Pastikan WANConnectionDevice dan WANPPPConnection ada untuk FH ONU.
     * Return: WCD index yang aktual digunakan.
     *
     * WANConnectionDeviceNumberOfEntries._value bisa null pada ONU fresh (belum dilaporkan).
     * Solusi: hitung dari actual WCD keys di GenieACS tree (bukan dari NumberOfEntries).
     * addObject WANConnectionDevice. selalu membuat index = current_count + 1.
     */
    private function ensureFiberhomePppWan(string $encodedId, string $targetWcd): string
    {
        $wanDevPath = "InternetGatewayDevice.WANDevice.1";
        $query      = urlencode(json_encode(['_id' => rawurldecode($encodedId)]));

        // 1. Ambil seluruh WCD tree — hitung instance numeric (lebih andal dari NumberOfEntries)
        $proj     = "{$wanDevPath}.WANConnectionDevice";
        $response = $this->request('GET', "/devices?query={$query}&projection={$proj}&limit=1");
        $wcdCount = 0;
        $wcdTree  = [];
        if ($response['status'] === 200) {
            $devices = json_decode($response['body'], true);
            $wcdTree = $devices[0]['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'] ?? [];
            $wcdCount = count(array_filter(array_keys($wcdTree), 'is_numeric'));
        }

        // addObject akan buat di index = count+1; jika sudah cukup pakai targetWcd langsung
        $actualWcd = $wcdCount >= (int)$targetWcd ? $targetWcd : (string)($wcdCount + 1);

        if ($wcdCount < (int)$targetWcd) {
            $task = ['name' => 'addObject', 'objectName' => "{$wanDevPath}.WANConnectionDevice"];
            $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=10000", $task);
            sleep(3);
        }

        // 2. Cek WANPPPConnection di WCD yang aktual (ambil dari tree yang sudah ada)
        $pppCount = (int)($wcdTree[$actualWcd]['WANPPPConnectionNumberOfEntries']['_value'] ?? 0);
        if ($pppCount > 0) {
            return $actualWcd;
        }

        $wcdPath = "{$wanDevPath}.WANConnectionDevice.{$actualWcd}";
        $task    = ['name' => 'addObject', 'objectName' => "{$wcdPath}.WANPPPConnection"];
        $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=10000", $task);
        sleep(3);

        return $actualWcd;
    }

    /**
     * Ambil info WAN PPPoE yang sedang aktif dari GenieACS.
     */
    public function getWanInfo(string $deviceId, string $brand = 'default'): ?array
    {
        $brand  = strtolower($brand);
        $paths  = self::WAN_PATHS[$brand] ?? self::WAN_PATHS['default'];
        $wcd    = $paths['wcd_index'];
        $ppp    = $paths['ppp_index'];
        $proj   = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wcd}.WANPPPConnection.{$ppp}";

        $encodedId = rawurlencode($deviceId);
        $response  = $this->request('GET', "/devices?query=" . urlencode(json_encode(['_id' => $deviceId]))
            . "&projection=_id,_deviceId,_lastInform,{$proj}&limit=1");

        if ($response['status'] !== 200) return null;
        $devices = json_decode($response['body'], true);
        if (empty($devices)) return null;

        $d = $devices[0];
        $pppData = $d['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'][$wcd]['WANPPPConnection'][$ppp] ?? [];

        return [
            'device_id'  => $d['_id'],
            'last_inform'=> $d['_lastInform'] ?? null,
            'username'   => $pppData['Username']['_value'] ?? null,
            'status'     => $pppData['ConnectionStatus']['_value'] ?? null,
            'ip'         => $pppData['ExternalIPAddress']['_value'] ?? null,
            'uptime'     => $pppData['Uptime']['_value'] ?? null,
        ];
    }

    /**
     * Ambil info WiFi + WAN + status + clients dari GenieACS untuk satu device.
     */
    public function getDeviceInfo(string $deviceId, string $brand = 'default'): ?array
    {
        $brand     = strtolower($brand);
        $wifiBase  = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1';
        $wifi5Base = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.5';

        // Project seluruh WANConnectionDevice agar bisa iterasi semua PPP index
        // (ZTE F609 pakai WANPPPConnection.2, F670L pakai .1, FH pakai WCD.2.PPP.1)
        $proj = implode(',', [
            '_id', '_deviceId', '_lastInform',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice',
            $wifiBase . '.SSID',
            $wifiBase . '.Enable',
            $wifiBase . '.KeyPassphrase',
            $wifiBase . '.PreSharedKey.1.PreSharedKey',
            $wifiBase . '.PreSharedKey.1.KeyPassphrase',
            $wifi5Base . '.SSID',
            $wifi5Base . '.Enable',
            $wifi5Base . '.KeyPassphrase',
            $wifi5Base . '.PreSharedKey.1.PreSharedKey',
            $wifi5Base . '.PreSharedKey.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.Hosts.Host',
        ]);

        $query    = urlencode(json_encode(['_id' => $deviceId]));
        $response = $this->request('GET', "/devices?query={$query}&projection={$proj}&limit=1");

        if ($response['status'] !== 200) return null;
        $devices = json_decode($response['body'], true);
        if (empty($devices)) return null;

        $d     = $devices[0];
        $wcd   = $d['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'] ?? [];
        $wifi  = $d['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1'] ?? [];
        $wifi5 = $d['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['5'] ?? [];
        $hosts = $d['InternetGatewayDevice']['LANDevice']['1']['Hosts']['Host'] ?? [];
        $tenMinAgo = strtotime('-20 minutes');
        $lastInf   = $d['_lastInform'] ?? null;

        // Cari WANPPPConnection aktif: iterasi WCD index (1,2) dan PPP index (1,2,...)
        $wanData = [];
        foreach (['1', '2'] as $wcdIdx) {
            $pppConns = $wcd[$wcdIdx]['WANPPPConnection'] ?? [];
            foreach ($pppConns as $pppIdx => $ppp) {
                if (!is_array($ppp) || empty($ppp['Username']['_value'] ?? '')) continue;
                $wanData = $ppp;
                break 2;
            }
        }

        return [
            'device_id'   => $d['_id'],
            'last_inform' => $lastInf,
            'online'      => $lastInf && strtotime($lastInf) >= $tenMinAgo,
            'manufacturer'=> $d['_deviceId']['_Manufacturer'] ?? '',
            'model'       => $d['_deviceId']['_ProductClass'] ?? '',
            'wan' => [
                'pppoe_user' => $wanData['Username']['_value'] ?? null,
                'pppoe_pass' => $wanData['Password']['_value'] ?? null,
                'status'     => $wanData['ConnectionStatus']['_value'] ?? null,
                'ip'         => $wanData['ExternalIPAddress']['_value'] ?? null,
                'uptime'     => $wanData['Uptime']['_value'] ?? null,
            ],
            'wifi' => [
                'ssid'     => $wifi['SSID']['_value'] ?? null,
                'enabled'  => $wifi['Enable']['_value'] ?? null,
                'password' => $this->readWifiPassword($wifi),
            ],
            'wifi5' => [
                'ssid'     => $wifi5['SSID']['_value'] ?? null,
                'enabled'  => $wifi5['Enable']['_value'] ?? null,
                'password' => $this->readWifiPassword($wifi5),
            ],
            'clients' => $this->parseHosts($hosts),
        ];
    }

    /** Baca password WiFi dengan fallback antar format brand */
    private function readWifiPassword(array $wlan): ?string
    {
        $pw = $wlan['PreSharedKey']['1']['PreSharedKey']['_value']
           ?? $wlan['PreSharedKey']['1']['KeyPassphrase']['_value']
           ?? $wlan['KeyPassphrase']['_value']
           ?? null;
        return ($pw !== null && $pw !== '') ? $pw : null;
    }

    /** Parse LANDevice.Hosts.Host menjadi array client sederhana */
    private function parseHosts(array $hostEntries): array
    {
        $clients = [];
        foreach ($hostEntries as $entry) {
            if (!is_array($entry) || !isset($entry['IPAddress'])) continue;
            $layer2 = $entry['Layer2Interface']['_value'] ?? '';
            $band   = '';
            if (str_contains($layer2, 'WLANConfiguration.5'))      $band = '5GHz';
            elseif (str_contains($layer2, 'WLANConfiguration.1'))  $band = '2.4GHz';
            $clients[] = [
                'hostname' => $entry['HostName']['_value'] ?? '',
                'ip'       => $entry['IPAddress']['_value'] ?? '',
                'mac'      => $entry['MACAddress']['_value'] ?? '',
                'type'     => $entry['InterfaceType']['_value'] ?? '',
                'band'     => $band,
                'active'   => (bool)($entry['Active']['_value'] ?? false),
            ];
        }
        usort($clients, fn($a, $b) => $b['active'] <=> $a['active']);
        return $clients;
    }

    /**
     * Set WiFi SSID dan password via GenieACS.
     * Parameter password berbeda per brand:
     *   Fiberhome: PreSharedKey.1.PreSharedKey + PreSharedKey.1.KeyPassphrase
     *   ZTE:       KeyPassphrase (direct) + PreSharedKey.1.KeyPassphrase
     *              (ZTE tidak punya PreSharedKey.1.PreSharedKey — device akan fault)
     *   Huawei/Nokia/default: PreSharedKey.1.KeyPassphrase + KeyPassphrase (direct)
     */
    public function setWifi(string $deviceId, string $ssid, string $password, bool $dualBand = false, string $brand = 'default'): array
    {
        $brand  = strtolower($brand);
        $params = [];

        foreach ($dualBand ? [1, 5] : [1] as $idx) {
            $base = "InternetGatewayDevice.LANDevice.1.WLANConfiguration.{$idx}";
            $params[] = ["{$base}.SSID", $ssid, 'xsd:string'];

            if ($brand === 'fiberhome') {
                $params[] = ["{$base}.PreSharedKey.1.PreSharedKey",  $password, 'xsd:string'];
                $params[] = ["{$base}.PreSharedKey.1.KeyPassphrase", $password, 'xsd:string'];
            } elseif ($brand === 'zte') {
                // ZTE: tidak ada PreSharedKey.1.PreSharedKey — hanya KeyPassphrase direct dan sub-KeyPassphrase
                $params[] = ["{$base}.KeyPassphrase",                $password, 'xsd:string'];
                $params[] = ["{$base}.PreSharedKey.1.KeyPassphrase", $password, 'xsd:string'];
            } else {
                // Huawei, Nokia, default
                $params[] = ["{$base}.KeyPassphrase",                $password, 'xsd:string'];
                $params[] = ["{$base}.PreSharedKey.1.KeyPassphrase", $password, 'xsd:string'];
                $params[] = ["{$base}.PreSharedKey.1.PreSharedKey",  $password, 'xsd:string'];
            }
        }

        $task      = ['name' => 'setParameterValues', 'parameterValues' => $params];
        $encodedId = rawurlencode($deviceId);
        $response  = $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=8000", $task);

        return [
            'success'   => in_array($response['status'], [200, 201, 202]),
            'status'    => $response['status'],
            'dual_band' => $dualBand,
        ];
    }

    /**
     * Queue PPPoE task tanpa connection_request (async, jalan saat ONU inform berikutnya).
     * Untuk FH: cek WAN state dulu via GET, queue addObject seperlunya sebelum setParameterValues.
     */
    public function queueProvisionPppoe(string $deviceId, string $pppoeUser, string $pppoePass, string $brand = 'default', array $extra = []): bool
    {
        $brand     = strtolower($brand);
        $paths     = self::WAN_PATHS[$brand] ?? self::WAN_PATHS['default'];
        $wcd       = $paths['wcd_index'];
        $ppp       = $paths['ppp_index'];
        $base      = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wcd}.WANPPPConnection.{$ppp}";
        $encodedId = rawurlencode($deviceId);

        if ($brand === 'fiberhome') {
            $wanDevPath = "InternetGatewayDevice.WANDevice.1";
            $query      = urlencode(json_encode(['_id' => $deviceId]));

            // Hitung WCD dari tree GenieACS — NumberOfEntries._value bisa null pada ONU fresh
            $proj    = "{$wanDevPath}.WANConnectionDevice";
            $res     = $this->request('GET', "/devices?query={$query}&projection={$proj}&limit=1");
            $wcdCount = 0;
            $wcdTree  = [];
            if ($res['status'] === 200) {
                $d = json_decode($res['body'], true)[0] ?? [];
                $wcdTree  = $d['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'] ?? [];
                $wcdCount = count(array_filter(array_keys($wcdTree), 'is_numeric'));
            }

            $wcdExists = $wcdCount >= (int)$wcd;
            $actualWcd = $wcdExists ? $wcd : (string)($wcdCount + 1);
            $wcdPath   = "{$wanDevPath}.WANConnectionDevice.{$actualWcd}";

            $pppCount  = (int)($wcdTree[$actualWcd]['WANPPPConnectionNumberOfEntries']['_value'] ?? 0);
            $pppExists = $wcdExists && $pppCount > 0;

            if (!$wcdExists) {
                $this->request('POST', "/devices/{$encodedId}/tasks", [
                    'name' => 'addObject', 'objectName' => "{$wanDevPath}.WANConnectionDevice",
                ]);
            }
            if (!$pppExists) {
                $this->request('POST', "/devices/{$encodedId}/tasks", [
                    'name' => 'addObject', 'objectName' => "{$wcdPath}.WANPPPConnection",
                ]);
            }

            // Gunakan actualWcd untuk base path setParameterValues
            $base   = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$actualWcd}.WANPPPConnection.{$ppp}";
            $vlanId = (int)($extra['vlan_internet'] ?? 0);
            $params = [
                ["{$base}.Enable",           true,        'xsd:boolean'],
                ["{$base}.ConnectionType",   'IP_Routed', 'xsd:string'],
                ["{$base}.NATEnabled",       true,        'xsd:boolean'],
                ["{$base}.X_FH_ServiceList", 'INTERNET',  'xsd:string'],
                ["{$base}.Username",         $pppoeUser,  'xsd:string'],
                ["{$base}.Password",         $pppoePass,  'xsd:string'],
            ];
            if ($vlanId > 0) {
                $params[] = ["{$base}.VLANEnable", true,    'xsd:boolean'];
                $params[] = ["{$base}.VLANID",     $vlanId, 'xsd:unsignedInt'];
            }
        } else {
            $params = [
                ["{$base}.Username", $pppoeUser, 'xsd:string'],
                ["{$base}.Password", $pppoePass, 'xsd:string'],
                ["{$base}.Enable",   true,        'xsd:boolean'],
            ];
        }

        $response = $this->request('POST', "/devices/{$encodedId}/tasks", [
            'name'            => 'setParameterValues',
            'parameterValues' => $params,
        ]);
        return in_array($response['status'], [200, 201, 202]);
    }

    /**
     * Reboot ONU via GenieACS.
     */
    public function rebootDevice(string $deviceId): bool
    {
        $encodedId = rawurlencode($deviceId);
        $response  = $this->request('POST', "/devices/{$encodedId}/tasks?connection_request", ['name' => 'reboot']);
        return in_array($response['status'], [200, 201, 202]);
    }

    /**
     * Batch query: ambil info singkat banyak device sekaligus berdasarkan daftar SN.
     * Return: [ 'SN' => ['device_id', 'last_inform', 'manufacturer', 'model', 'online'], ... ]
     *
     * Dibatasi 200 SN per request untuk hindari query terlalu besar.
     * Projection minimal: hanya _id, _deviceId, _lastInform.
     */
    /**
     * Cari username dari WANPPPConnection — iterasi semua index (1, 2, dst).
     * ZTE F609 pakai index 2, F670L pakai index 1. Ambil yang pertama ada username-nya.
     */
    private function extractPppoeUser(array $wanConnectionDevice): ?string
    {
        foreach (['1', '2'] as $wcdIdx) {
            $pppConns = $wanConnectionDevice[$wcdIdx]['WANPPPConnection'] ?? [];
            foreach ($pppConns as $pppIdx => $ppp) {
                if (!is_array($ppp)) continue;
                $user = $ppp['Username']['_value'] ?? null;
                if ($user && $user !== '') return $user;
            }
        }
        return null;
    }

    public function getDevicesBySns(array $sns): array
    {
        if (empty($sns)) return [];

        $result    = [];
        $tenMinAgo = strtotime('-20 minutes');

        // Project seluruh WANConnectionDevice (WCD.1 dan WCD.2) agar bisa iterasi semua PPP index
        $wanProj = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice';

        // Chunk 100 SN per request — hindari URL terlalu panjang
        foreach (array_chunk(array_values($sns), 100) as $chunk) {
            $query    = urlencode(json_encode(['_deviceId._SerialNumber' => ['$in' => $chunk]]));
            $proj     = "_id,_deviceId,_lastInform,{$wanProj}";
            $response = $this->request('GET', "/devices?query={$query}&projection={$proj}&limit=" . count($chunk));

            if ($response['status'] !== 200) continue;

            foreach (json_decode($response['body'], true) ?? [] as $d) {
                $sn      = $d['_deviceId']['_SerialNumber'] ?? '';
                $lastInf = $d['_lastInform'] ?? null;
                $wcd     = $d['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'] ?? [];
                $pppoeUser = $this->extractPppoeUser($wcd);

                $result[strtoupper($sn)] = [
                    'device_id'    => $d['_id'],
                    'last_inform'  => $lastInf,
                    'manufacturer' => $d['_deviceId']['_Manufacturer'] ?? '',
                    'model'        => $d['_deviceId']['_ProductClass'] ?? '',
                    'online'       => $lastInf && strtotime($lastInf) >= $tenMinAgo,
                    'pppoe_user'   => $pppoeUser,
                ];
            }
        }

        return $result;
    }

    /**
     * Daftar semua device yang online dalam 10 menit terakhir.
     * Limit default 10000 — cukup untuk deployment besar.
     */
    public function getOnlineDevices(int $limit = 10000): array
    {
        $since    = date('c', strtotime('-20 minutes'));
        $query    = urlencode(json_encode(['_lastInform' => ['$gt' => $since]]));
        $response = $this->request('GET', "/devices?query={$query}&limit={$limit}&projection=_id,_deviceId,_lastInform");

        if ($response['status'] === 200) {
            return json_decode($response['body'], true) ?? [];
        }
        return [];
    }


private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($this->username && $this->password) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $responseBody = curl_exec($ch);
        $statusCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("ACS request error: {$error}");
        }

        return ['status' => $statusCode, 'body' => $responseBody];
    }
}
