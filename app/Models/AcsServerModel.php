<?php

namespace App\Models;

use CodeIgniter\Model;

class AcsServerModel extends Model
{
    protected $table      = 'acs_servers';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user_id', 'name', 'url', 'username', 'password', 'is_default'];
    protected $useTimestamps = true;

    public function getByUser(int $userId): array
    {
        return $this->where('user_id', $userId)->orderBy('is_default', 'DESC')->findAll();
    }

    public function getDefault(int $userId): ?array
    {
        return $this->where('user_id', $userId)->where('is_default', 1)->first();
    }

    public function getByUserAndId(int $userId, int $id): ?array
    {
        return $this->where('user_id', $userId)->where('id', $id)->first();
    }

    public function setDefault(int $userId, int $id): void
    {
        $this->where('user_id', $userId)->set('is_default', 0)->update();
        $this->where('id', $id)->set('is_default', 1)->update();
    }
}
