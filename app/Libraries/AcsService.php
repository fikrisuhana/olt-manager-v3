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
            // Pastikan WAN PPP slot ada — buat jika belum
            $this->ensureFiberhomePppWan($encodedId, $wcd);

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
     * Pastikan WANPPPConnection.{ppp} ada di bawah WANConnectionDevice.{wcd}.
     * Jika belum ada, kirim addObject task ke GenieACS.
     */
    private function ensureFiberhomePppWan(string $encodedId, string $wcd): void
    {
        $wcdPath = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wcd}";

        // Cek jumlah WANPPPConnection yang ada
        $query    = urlencode(json_encode(['_id' => rawurldecode($encodedId)]));
        $proj     = "{$wcdPath}.WANPPPConnectionNumberOfEntries";
        $response = $this->request('GET', "/devices?query={$query}&projection={$proj}&limit=1");

        if ($response['status'] === 200) {
            $devices = json_decode($response['body'], true);
            $count   = $devices[0]['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'][$wcd]['WANPPPConnectionNumberOfEntries']['_value'] ?? null;
            if ($count !== null && (int)$count > 0) {
                return; // WAN PPP sudah ada
            }
        }

        // Buat WANPPPConnection baru via addObject
        $task = ['name' => 'addObject', 'objectName' => "{$wcdPath}.WANPPPConnection."];
        $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=10000", $task);
        // Beri jeda agar device sempat buat object sebelum setParameterValues dikirim
        sleep(3);
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
     * Ambil info WiFi + WAN + status dari GenieACS untuk satu device.
     */
    public function getDeviceInfo(string $deviceId, string $brand = 'default'): ?array
    {
        $brand  = strtolower($brand);
        $paths  = self::WAN_PATHS[$brand] ?? self::WAN_PATHS['default'];
        $wcd    = $paths['wcd_index'];
        $ppp    = $paths['ppp_index'];
        $wanBase = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wcd}.WANPPPConnection.{$ppp}";
        $wifiBase = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1';

        $proj = implode(',', [
            '_id', '_deviceId', '_lastInform',
            $wanBase . '.Username',
            $wanBase . '.ConnectionStatus',
            $wanBase . '.ExternalIPAddress',
            $wanBase . '.Uptime',
            $wifiBase . '.SSID',
            $wifiBase . '.Enable',
        ]);

        $query    = urlencode(json_encode(['_id' => $deviceId]));
        $response = $this->request('GET', "/devices?query={$query}&projection={$proj}&limit=1");

        if ($response['status'] !== 200) return null;
        $devices = json_decode($response['body'], true);
        if (empty($devices)) return null;

        $d       = $devices[0];
        $wanData = $d['InternetGatewayDevice']['WANDevice']['1']['WANConnectionDevice'][$wcd]['WANPPPConnection'][$ppp] ?? [];
        $wifi    = $d['InternetGatewayDevice']['LANDevice']['1']['WLANConfiguration']['1'] ?? [];
        $tenMinAgo = strtotime('-10 minutes');
        $lastInf   = $d['_lastInform'] ?? null;

        return [
            'device_id'   => $d['_id'],
            'last_inform' => $lastInf,
            'online'      => $lastInf && strtotime($lastInf) >= $tenMinAgo,
            'manufacturer'=> $d['_deviceId']['_Manufacturer'] ?? '',
            'model'       => $d['_deviceId']['_ProductClass'] ?? '',
            'wan' => [
                'pppoe_user' => $wanData['Username']['_value'] ?? null,
                'status'     => $wanData['ConnectionStatus']['_value'] ?? null,
                'ip'         => $wanData['ExternalIPAddress']['_value'] ?? null,
                'uptime'     => $wanData['Uptime']['_value'] ?? null,
            ],
            'wifi' => [
                'ssid'    => $wifi['SSID']['_value'] ?? null,
                'enabled' => $wifi['Enable']['_value'] ?? null,
            ],
        ];
    }

    /**
     * Set WiFi SSID dan password (PreSharedKey) via GenieACS.
     */
    public function setWifi(string $deviceId, string $ssid, string $password): array
    {
        $wifiBase = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1';
        $task = [
            'name'            => 'setParameterValues',
            'parameterValues' => [
                ["{$wifiBase}.SSID",                                 $ssid,     'xsd:string'],
                ["{$wifiBase}.PreSharedKey.1.PreSharedKey",          $password, 'xsd:string'],
                ["{$wifiBase}.PreSharedKey.1.KeyPassphrase",         $password, 'xsd:string'],
            ],
        ];

        $encodedId = rawurlencode($deviceId);
        $response  = $this->request('POST', "/devices/{$encodedId}/tasks?connection_request&timeout=8000", $task);

        return [
            'success' => in_array($response['status'], [200, 201, 202]),
            'status'  => $response['status'],
        ];
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
    public function getDevicesBySns(array $sns): array
    {
        if (empty($sns)) return [];

        $result    = [];
        $tenMinAgo = strtotime('-10 minutes');

        // Chunk 100 SN per request — hindari URL terlalu panjang
        foreach (array_chunk(array_values($sns), 100) as $chunk) {
            $query    = urlencode(json_encode(['_deviceId._SerialNumber' => ['$in' => $chunk]]));
            $proj     = '_id,_deviceId,_lastInform';
            $response = $this->request('GET', "/devices?query={$query}&projection={$proj}&limit=" . count($chunk));

            if ($response['status'] !== 200) continue;

            foreach (json_decode($response['body'], true) ?? [] as $d) {
                $sn      = $d['_deviceId']['_SerialNumber'] ?? '';
                $lastInf = $d['_lastInform'] ?? null;
                $result[strtoupper($sn)] = [
                    'device_id'    => $d['_id'],
                    'last_inform'  => $lastInf,
                    'manufacturer' => $d['_deviceId']['_Manufacturer'] ?? '',
                    'model'        => $d['_deviceId']['_ProductClass'] ?? '',
                    'online'       => $lastInf && strtotime($lastInf) >= $tenMinAgo,
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
        $since    = date('c', strtotime('-10 minutes'));
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
