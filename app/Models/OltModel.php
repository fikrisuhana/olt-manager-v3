<?php

namespace App\Models;

use CodeIgniter\Model;

class OltModel extends Model
{
    protected $table      = 'olts';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id', 'name', 'ip', 'brand', 'model',
        'telnet_port', 'telnet_user', 'telnet_pass',
        'snmp_community', 'snmp_port', 'tcont_profiles', 'traffic_profiles', 'description', 'acs_url',
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
