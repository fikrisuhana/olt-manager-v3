<?php

namespace App\Models;

use CodeIgniter\Model;

class TemplateModel extends Model
{
    protected $table      = 'templates';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id', 'name', 'brand', 'vlan_internet', 'vlan_management',
        'wan_type', 'tcont_profile', 'gpon_onu_script', 'description',
    ];
    protected $useTimestamps = true;

    public function getByUser(int $userId): array
    {
        return $this->where('user_id', $userId)->orderBy('name', 'ASC')->findAll();
    }

    public function getByUserAndId(int $userId, int $id): ?array
    {
        return $this->where('user_id', $userId)->where('id', $id)->first();
    }
}
