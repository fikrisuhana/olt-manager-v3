<?php

namespace App\Models;

use CodeIgniter\Model;

class OnuModel extends Model
{
    protected $table      = 'onus';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'olt_id', 'sn', 'name', 'description',
        'board', 'slot', 'port', 'onu_index',
        'onu_type', 'vlan_internet', 'vlan_acs', 'tcont_profile',
        'pppoe_user', 'acs_device_id',
        'status', 'template_id', 'registered_at',
    ];
    protected $useTimestamps = true;

    public function getByOlt(int $oltId): array
    {
        return $this->where('olt_id', $oltId)
                    ->where('status !=', 'deleted')
                    ->orderBy('board,slot,port,onu_index', '')
                    ->findAll();
    }

    public function getByUser(int $userId): array
    {
        return $this->select('onus.*, olts.name as olt_name, olts.brand, olts.ip as olt_ip')
                    ->join('olts', 'olts.id = onus.olt_id')
                    ->where('olts.user_id', $userId)
                    ->where('onus.status !=', 'deleted')
                    ->orderBy('onus.registered_at', 'DESC')
                    ->findAll();
    }

    public function snExists(int $oltId, string $sn): bool
    {
        return $this->where('olt_id', $oltId)->where('sn', $sn)->where('status !=', 'deleted')->countAllResults() > 0;
    }

    public function getByOltAndSn(int $oltId, string $sn): ?array
    {
        return $this->where('olt_id', $oltId)->where('sn', $sn)->where('status !=', 'deleted')->first();
    }

    public function getAnyByOltAndSn(int $oltId, string $sn): ?array
    {
        return $this->where('olt_id', $oltId)->where('sn', $sn)->first();
    }

    public function getWithOlt(int $id): ?array
    {
        return $this->select('onus.*, olts.name as olt_name, olts.ip as olt_ip, olts.brand, olts.model, olts.user_id')
                    ->join('olts', 'olts.id = onus.olt_id')
                    ->where('onus.id', $id)
                    ->first();
    }
}
