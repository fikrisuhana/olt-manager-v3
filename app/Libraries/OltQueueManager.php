<?php

namespace App\Libraries;

use App\Models\ProvisionLogModel;

/**
 * Queue Manager untuk memproses tugas OLT secara Asynchronous / Background.
 */
class OltQueueManager
{
    private OltLockingService $locker;

    public function __construct()
    {
        $this->locker = new OltLockingService();
    }

    /**
     * Tambahkan Job ke antrean
     */
    public function enqueue(int $oltId, string $action, array $payload, int $userId): int
    {
        $logModel = new ProvisionLogModel();
        $id = $logModel->insert([
            'olt_id'  => $oltId,
            'user_id' => $userId,
            'action'  => $action,
            'status'  => 'queued',
            'details' => json_encode($payload),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$id;
    }

    /**
     * Jalankan Job langsung dengan Mutex Lock
     */
    public function dispatchNow(int $oltId, callable $job): mixed
    {
        return $this->locker->runWithLock($oltId, $job);
    }
}
