-- GPON Manager Database Schema
-- Single migration file — run once on first deploy

CREATE DATABASE IF NOT EXISTS gpon_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gpon_manager;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    username    VARCHAR(50) NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','user') NOT NULL DEFAULT 'user',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- OLTs (1 user dapat punya banyak OLT)
CREATE TABLE IF NOT EXISTS olts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    ip              VARCHAR(45) NOT NULL,
    brand           VARCHAR(50) NOT NULL DEFAULT 'ZTE',
    model           VARCHAR(100) NOT NULL DEFAULT 'C320',
    telnet_port     SMALLINT UNSIGNED NOT NULL DEFAULT 23,
    telnet_user     VARCHAR(50) NOT NULL,
    telnet_pass     VARCHAR(100) NOT NULL,
    snmp_community  VARCHAR(100) NOT NULL DEFAULT 'public',
    snmp_port       SMALLINT UNSIGNED NOT NULL DEFAULT 161,
    tcont_profiles  TEXT NULL,    -- daftar nama TCONT profile di OLT, satu per baris (misal: 250M\n100M)
    description     TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ONUs — semua ONU yang sudah didaftarkan ke OLT
CREATE TABLE IF NOT EXISTS onus (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    olt_id          INT UNSIGNED NOT NULL,
    sn              VARCHAR(50) NOT NULL,
    name            VARCHAR(100),
    description     TEXT,
    board           VARCHAR(10) NOT NULL,
    slot            VARCHAR(10) NOT NULL,
    port            VARCHAR(10) NOT NULL,
    onu_index       VARCHAR(10) NOT NULL,
    onu_type        VARCHAR(50),
    vlan_internet   SMALLINT UNSIGNED NULL,    -- VLAN service PPPoE/internet
    vlan_acs        SMALLINT UNSIGNED NULL,    -- VLAN management TR-069/ACS
    tcont_profile   VARCHAR(50) NULL,          -- nama DBA profile di OLT
    pppoe_user      VARCHAR(100) NULL,         -- username PPPoE (untuk referensi)
    acs_device_id   VARCHAR(200) NULL,         -- device ID GenieACS: OUI-ProductClass-SN
    status          ENUM('pending','registered','active','offline','deleted') NOT NULL DEFAULT 'registered',
    template_id     INT UNSIGNED,
    registered_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_olt_sn (olt_id, sn),
    FOREIGN KEY (olt_id) REFERENCES olts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Config Templates (per user — untuk script CLI tambahan)
CREATE TABLE IF NOT EXISTS templates (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL,
    name                VARCHAR(100) NOT NULL,
    brand               VARCHAR(50) NOT NULL DEFAULT 'ZTE',
    vlan_internet       SMALLINT UNSIGNED,
    vlan_management     SMALLINT UNSIGNED DEFAULT 100,
    wan_type            ENUM('pppoe','dhcp','static') NOT NULL DEFAULT 'pppoe',
    tcont_profile       VARCHAR(50),
    gpon_onu_script     TEXT,       -- script tambahan di interface gpon-onu (setelah VLAN/TCONT)
    description         TEXT,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ACS Servers (GenieACS, per user)
CREATE TABLE IF NOT EXISTS acs_servers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    url         VARCHAR(255) NOT NULL,     -- contoh: http://136.1.1.8:7557
    username    VARCHAR(100),
    password    VARCHAR(100),
    is_default  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Log aktivitas provisioning ONU
CREATE TABLE IF NOT EXISTS provision_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    onu_id      INT UNSIGNED,
    olt_id      INT UNSIGNED,
    user_id     INT UNSIGNED NOT NULL,
    action      VARCHAR(50) NOT NULL,
    status      ENUM('success','failed') NOT NULL,
    message     TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (onu_id) REFERENCES onus(id) ON DELETE SET NULL,
    FOREIGN KEY (olt_id) REFERENCES olts(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
