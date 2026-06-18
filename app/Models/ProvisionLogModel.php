<?php

namespace App\Models;

use CodeIgniter\Model;

class ProvisionLogModel extends Model
{
    protected $table      = 'provision_logs';
    protected $primaryKey = 'id';
    protected $allowedFields = ['onu_id', 'olt_id', 'user_id', 'action', 'status', 'message'];
    protected $useTimestamps = false;

    public function log(int $userId, string $action, string $status, string $message, ?int $onuId = null, ?int $oltId = null): void
    {
        $this->insert([
            'user_id'    => $userId,
            'onu_id'     => $onuId,
            'olt_id'     => $oltId,
            'action'     => $action,
            'status'     => $status,
            'message'    => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->select('provision_logs.*, onus.sn, onus.name as onu_name, olts.name as olt_name')
                    ->join('onus', 'onus.id = provision_logs.onu_id', 'left')
                    ->join('olts', 'olts.id = provision_logs.olt_id', 'left')
                    ->where('provision_logs.user_id', $userId)
                    ->orderBy('provision_logs.created_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }
}
