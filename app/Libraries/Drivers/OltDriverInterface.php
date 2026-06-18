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

    /** Ambil info sinyal RX/TX ONU */
    public function getOnuSignal(string $board, string $slot, string $port, string $onuIndex): array;

    public function getBrand(): string;
    public function getModel(): string;
}
