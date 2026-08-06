<?php

namespace App\Libraries;

/**
 * Service untuk mengelola Mutex Lock per OLT.
 * Mencegah tabrakan sesi CLI (Telnet/SSH VTY lines) ketika multiple request/worker berjalan bersamaan.
 */
class OltLockingService
{
    private string $lockDir;

    public function __construct()
    {
        $this->lockDir = WRITEPATH . 'locks/';
        if (!is_dir($this->lockDir)) {
            @mkdir($this->lockDir, 0755, true);
        }
    }

    /**
     * Jalankan callback dengan Mutex Lock per OLT ID
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function runWithLock(int $oltId, callable $callback, int $timeoutSeconds = 30)
    {
        $lockFile = $this->lockDir . "olt_{$oltId}.lock";
        $fp = @fopen($lockFile, 'c+');

        if (!$fp) {
            throw new \Exception("Gagal membuka file lock OLT #{$oltId}");
        }

        $startTime = time();
        $acquired = false;

        while ((time() - $startTime) < $timeoutSeconds) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                $acquired = true;
                break;
            }
            usleep(250000); // 250ms
        }

        if (!$acquired) {
            fclose($fp);
            throw new \Exception("OLT #{$oltId} sedang sibuk melayani proses lain. Silakan coba lagi.");
        }

        try {
            $result = $callback();
            return $result;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
