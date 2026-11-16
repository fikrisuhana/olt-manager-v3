-- Kebijakan ACS mandiri per ACS server (gpon-manager mem-push provision/preset gm-* ke GenieACS via NBI).
-- Tabel juga dibuat otomatis saat runtime oleh AcsPolicyModel::ensureTable().
CREATE TABLE IF NOT EXISTS acs_policies (
    acs_server_id  INT UNSIGNED NOT NULL PRIMARY KEY,
    settings       TEXT NOT NULL,              -- JSON, lihat AcsPolicyService::DEFAULTS
    pushed_at      DATETIME NULL,
    push_ok        TINYINT(1) NULL,
    push_message   TEXT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (acs_server_id) REFERENCES acs_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB;
