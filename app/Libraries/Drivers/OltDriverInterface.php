<?php

namespace App\Libraries\Drivers;

interface OltDriverInterface
{
    public function connect(): void;
    public function disconnect(): void;

    /** Daftar ONU yang belum dikonfigurasi */
    public function getUnconfiguredOnus(): array;

    /** Daftar ONU yang sudah terdaftar */
    public function getRegisteredOnus(): array;

    /**
     * Daftarkan ONU ke OLT.
     * $params: board, slot, port, onu_index, onu_type, sn, name, vlan_internet, vlan_acs, tcont_profile, gpon_onu_script
     * Return: ['success' => bool, 'log' => string[]]
     */
    public function registerOnu(array $params): array;

    /** Hapus ONU dari OLT */
    public function deleteOnu(string $board, string $slot, string $port, string $onuIndex): bool;

    /** Push pon-onu-mng (PPPoE + ACS DHCP) ke ONU yang sudah terdaftar (tanpa re-register) */
    public function applyPonMng(string $board, string $slot, string $port, string $onuIndex, int $vlanAcs, string $acsUrl, int $vlanInternet = 0, string $pppoeUser = '', string $pppoePass = ''): array;

    /** Ambil SN ONU aktif di slot tertentu (null = kosong) */
    public function getSnAtIndex(string $board, string $slot, string $port, string $onuIndex): ?string;

    /** Ambil info sinyal RX/TX ONU */
    public function getOnuSignal(string $board, string $slot, string $port, string $onuIndex): array;

    public function getBrand(): string;
    public function getModel(): string;

    /** Ambil daftar nama TCONT profile dari OLT */
    public function getTcontProfiles(): array;

    /** Ambil daftar nama traffic/bandwidth profile dari OLT */
    public function getTrafficProfiles(): array;

    /**
     * Ambil konfigurasi aktif satu ONU (VLAN, TCONT, traffic-limit).
     * Return: ['tcont_profile'=>'', 'traffic_profile'=>'', 'vlan_internet'=>0, 'vlan_acs'=>0, 'service_ports'=>[sp=>vlan]]
     */
    public function getOnuConfig(string $board, string $slot, string $port, string $onuIndex): array;
}
