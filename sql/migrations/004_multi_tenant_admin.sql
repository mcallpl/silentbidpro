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
ALTER TABLE users ADD COLUMN IF NOT EXISTS event_id INT UNSIGNED DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS user_type ENUM('bidder', 'admin', 'viewer') DEFAULT 'bidder';
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_by_admin_id INT UNSIGNED DEFAULT NULL;

-- Add foreign key constraints (if they don't exist)
ALTER TABLE users ADD FOREIGN KEY IF NOT EXISTS (event_id) REFERENCES events(id) ON DELETE SET NULL;
ALTER TABLE users ADD FOREIGN KEY IF NOT EXISTS (created_by_admin_id) REFERENCES admin_accounts(id) ON DELETE SET NULL;

-- Add indexes for new columns
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_event_id (event_id);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_user_type (user_type);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_created_by_admin (created_by_admin_id);
