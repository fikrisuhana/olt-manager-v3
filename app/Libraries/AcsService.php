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
     * Otomatis deteksi WAN path berdasarkan brand device.
     *
     * $params: pppoe_user, pppoe_pass, [brand override]
     */
    public function provisionPppoe(string $deviceId, string $pppoeUser, string $pppoePass, string $brand = 'default'): array
    {
        $brand  = strtolower($brand);
        $paths  = self::WAN_PATHS[$brand] ?? self::WAN_PATHS['default'];
        $wcd    = $paths['wcd_index'];
        $ppp    = $paths['ppp_index'];
        $base   = "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.{$wcd}.WANPPPConnection.{$ppp}";

        $task = [
            'name'            => 'setParameterValues',
            'parameterValues' => [
                ["{$base}.Username", $pppoeUser, 'xsd:string'],
                ["{$base}.Password", $pppoePass, 'xsd:string'],
                ["{$base}.Enable",   true,        'xsd:boolean'],
            ],
        ];

        $encodedId = rawurlencode($deviceId);
        $response  = $this->request('POST', "/devices/{$encodedId}/tasks?timeout=3000", $task);

        return [
            'success'  => in_array($response['status'], [200, 201, 202]),
            'status'   => $response['status'],
            'body'     => $response['body'],
            'wan_path' => $base,
        ];
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
        $response  = $this->request('POST', "/devices/{$encodedId}/tasks?timeout=3000", $task);

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
        $response  = $this->request('POST', "/devices/{$encodedId}/tasks", ['name' => 'reboot']);
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

        // Batasi 200 SN per request
        $sns      = array_slice(array_values($sns), 0, 200);
        $query    = urlencode(json_encode(['_deviceId._SerialNumber' => ['$in' => $sns]]));
        $proj     = '_id,_deviceId,_lastInform';
        $response = $this->request('GET', "/devices?query={$query}&projection={$proj}&limit=" . count($sns));

        if ($response['status'] !== 200) return [];

        $devices = json_decode($response['body'], true) ?? [];
        $result  = [];
        $tenMinAgo = strtotime('-10 minutes');

        foreach ($devices as $d) {
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

        return $result;
    }

    /**
     * Daftar semua device yang online dalam 10 menit terakhir.
     */
    public function getOnlineDevices(int $limit = 100): array
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
