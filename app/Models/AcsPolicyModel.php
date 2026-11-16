<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Kebijakan ACS mandiri (1 baris per acs_servers.id). Kolom settings = JSON.
 */
class AcsPolicyModel extends Model
{
    protected $table         = 'acs_policies';
    protected $primaryKey    = 'acs_server_id';
    protected $useAutoIncrement = false;
    protected $allowedFields = ['acs_server_id', 'settings', 'pushed_at', 'push_ok', 'push_message'];
    protected $useTimestamps = true;

    /** Buat tabel kalau belum ada (DB lama tidak menjalankan migrations/010). */
    public static function ensureTable(): void
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('acs_policies')) {
            return;
        }
        $db->query("CREATE TABLE IF NOT EXISTS acs_policies (
            acs_server_id  INT UNSIGNED NOT NULL PRIMARY KEY,
            settings       TEXT NOT NULL,
            pushed_at      DATETIME NULL,
            push_ok        TINYINT(1) NULL,
            push_message   TEXT NULL,
            created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (acs_server_id) REFERENCES acs_servers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
    }

    public function getSettings(int $acsServerId): ?array
    {
        $row = $this->find($acsServerId);
        if (!$row) {
            return null;
        }
        $row['settings'] = json_decode($row['settings'] ?? '{}', true) ?: [];
        return $row;
    }

    public function saveSettings(int $acsServerId, array $settings): void
    {
        $data = ['acs_server_id' => $acsServerId, 'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES)];
        if ($this->find($acsServerId)) {
            $this->update($acsServerId, $data);
        } else {
            $this->insert($data);
        }
    }

    public function savePushResult(int $acsServerId, bool $ok, string $message): void
    {
        $this->update($acsServerId, [
            'pushed_at'    => date('Y-m-d H:i:s'),
            'push_ok'      => $ok ? 1 : 0,
            'push_message' => mb_substr($message, 0, 4000),
        ]);
    }
}
