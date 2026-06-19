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

    public function getByUserPaginated(int $userId, int $perPage = 50, int $page = 1, string $q = ''): array
    {
        $builder = $this->select('onus.*, olts.name as olt_name, olts.brand, olts.ip as olt_ip')
                        ->join('olts', 'olts.id = onus.olt_id')
                        ->where('olts.user_id', $userId)
                        ->where('onus.status !=', 'deleted');

        if ($q !== '') {
            $builder->groupStart()
                        ->like('onus.sn', $q)
                        ->orLike('onus.name', $q)
                        ->orLike('olts.name', $q)
                    ->groupEnd();
        }

        return $builder->orderBy('onus.registered_at', 'DESC')
                       ->limit($perPage, ($page - 1) * $perPage)
                       ->findAll();
    }

    public function countByUser(int $userId, string $q = ''): int
    {
        $builder = $this->select('onus.id')
                        ->join('olts', 'olts.id = onus.olt_id')
                        ->where('olts.user_id', $userId)
                        ->where('onus.status !=', 'deleted');

        if ($q !== '') {
            $builder->groupStart()
                        ->like('onus.sn', $q)
                        ->orLike('onus.name', $q)
                        ->orLike('olts.name', $q)
                    ->groupEnd();
        }

        return $builder->countAllResults();
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
