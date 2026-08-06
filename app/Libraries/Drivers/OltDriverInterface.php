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

    /** Ubah nama ONU di OLT (tanpa re-register). */
    public function setOnuName(string $board, string $slot, string $port, string $onuIndex, string $name): array;

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

    /** Ambil daftar ONU vlan-profile dari OLT: [['name'=>'PPPOE','vlan'=>155], ...] */
    public function getVlanProfiles(): array;

    /** Tambah TCONT profile di OLT */
    public function addTcontProfile(string $name, int $maxBwKbps = 102400): array;
    /** Hapus TCONT profile dari OLT */
    public function deleteTcontProfile(string $name): array;

    /** Tambah Traffic Limit profile di OLT */
    public function addTrafficProfile(string $name, int $sirKbps = 102400, int $pirKbps = 102400): array;
    /** Hapus Traffic Limit profile dari OLT */
    public function deleteTrafficProfile(string $name): array;

    /** Tambah ONU VLAN profile di OLT */
    public function addVlanProfile(string $name, int $vlanId): array;
    /** Hapus ONU VLAN profile dari OLT */
    public function deleteVlanProfile(string $name): array;

    /**
     * Ambil konfigurasi aktif satu ONU (VLAN, TCONT, traffic-limit).
     * Return: ['tcont_profile'=>'', 'traffic_profile'=>'', 'vlan_internet'=>0, 'vlan_acs'=>0, 'service_ports'=>[sp=>vlan]]
     */
    public function getOnuConfig(string $board, string $slot, string $port, string $onuIndex): array;

    // ── Setup / maintenance OLT ────────────────────────────────────────────

    /**
     * Info sistem OLT: nama, model, versi, uptime, interface manajemen, daftar kartu.
     * Return: ['name'=>'','model'=>'','version'=>'','uptime'=>'','mgmt'=>[...],'cards'=>[...]]
     */
    public function getSystemInfo(): array;

    /**
     * Daftar port PON beserta statusnya.
     * Return: [['board'=>'1','slot'=>'1','port'=>'1','enabled'=>true,'description'=>'',
     *           'name'=>'','onu_configured'=>5,'onu_working'=>5], ...]
     */
    public function getPonPorts(): array;

    /** Aktifkan/matikan port PON (no shutdown / shutdown). */
    public function setPonPortState(string $board, string $slot, string $port, bool $enable): array;

    /**
     * Ubah nama (name) dan/atau deskripsi (description) port PON.
     * null = jangan sentuh field itu; string kosong = hapus (no name / no description).
     */
    public function setPonPortInfo(string $board, string $slot, string $port, ?string $name, ?string $description): array;

    /** Daftar VLAN di VLAN database OLT. Return: [1,10,100,150,...] */
    public function getVlanDatabase(): array;

    /** Tambah VLAN ke VLAN database. */
    public function addVlan(int $vlanId): array;

    /** Hapus VLAN dari VLAN database. */
    public function deleteVlan(int $vlanId): array;
}
