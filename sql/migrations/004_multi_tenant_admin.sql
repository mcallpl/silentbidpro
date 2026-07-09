-- ============================================================
-- MIGRATION: Multi-tenant admin & event management system
-- Created: 2026-06-15
-- Enables different organizations and role-based admin control
-- ============================================================

-- Add organization_id to admin_accounts for default org context
ALTER TABLE admin_accounts ADD COLUMN organization_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE admin_accounts ADD FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL;
ALTER TABLE admin_accounts ADD INDEX idx_organization_id (organization_id);

-- Bridge table: Admin-to-Organization mapping with roles
CREATE TABLE IF NOT EXISTS admin_organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    organization_id INT UNSIGNED NOT NULL,
    role ENUM('manager', 'viewer') NOT NULL DEFAULT 'viewer',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_admin_org_role (admin_id, organization_id),
    FOREIGN KEY (admin_id) REFERENCES admin_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,

    INDEX idx_admin_id (admin_id),
    INDEX idx_organization_id (organization_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bridge table: Admin-to-Event mapping with roles
CREATE TABLE IF NOT EXISTS admin_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    role ENUM('manager', 'viewer') NOT NULL DEFAULT 'viewer',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_admin_event_role (admin_id, event_id),
    FOREIGN KEY (admin_id) REFERENCES admin_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,

    INDEX idx_admin_id (admin_id),
    INDEX idx_event_id (event_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event settings: Per-event customization for branding, SMS, and payment
CREATE TABLE IF NOT EXISTS event_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL UNIQUE,
    logo_url VARCHAR(500),
    primary_color VARCHAR(20),
    accent_color VARCHAR(20),
    sms_enabled TINYINT(1) DEFAULT 1,
    outbid_sms_template TEXT,
    winner_sms_template TEXT,
    stripe_account_id VARCHAR(255),
    stripe_key_publishable VARCHAR(255),
    stripe_key_secret VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add multi-tenant columns to users table
-- (MySQL 8 rejects ADD/DROP COLUMN IF [NOT] EXISTS, so guard via information_schema instead)
DELIMITER $$
DROP PROCEDURE IF EXISTS _migration_004_apply $$
CREATE PROCEDURE _migration_004_apply()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'event_id'
    ) THEN
        ALTER TABLE users ADD COLUMN event_id INT UNSIGNED DEFAULT NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'user_type'
    ) THEN
        ALTER TABLE users ADD COLUMN user_type ENUM('bidder', 'admin', 'viewer') DEFAULT 'bidder';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'created_by_admin_id'
    ) THEN
        ALTER TABLE users ADD COLUMN created_by_admin_id INT UNSIGNED DEFAULT NULL;
    END IF;

    -- Add foreign key constraints (if they don't exist)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'event_id'
          AND REFERENCED_TABLE_NAME = 'events'
    ) THEN
        ALTER TABLE users ADD FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'created_by_admin_id'
          AND REFERENCED_TABLE_NAME = 'admin_accounts'
    ) THEN
        ALTER TABLE users ADD FOREIGN KEY (created_by_admin_id) REFERENCES admin_accounts(id) ON DELETE SET NULL;
    END IF;

    -- Add indexes for new columns
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_event_id'
    ) THEN
        ALTER TABLE users ADD INDEX idx_event_id (event_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_user_type'
    ) THEN
        ALTER TABLE users ADD INDEX idx_user_type (user_type);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_created_by_admin'
    ) THEN
        ALTER TABLE users ADD INDEX idx_created_by_admin (created_by_admin_id);
    END IF;
END $$
DELIMITER ;

CALL _migration_004_apply();
DROP PROCEDURE IF EXISTS _migration_004_apply;
