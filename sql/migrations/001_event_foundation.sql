-- ============================================================
-- Silent Bid Pro Migration 001: Event Foundation
-- Adds reusable organization/event structure, optional user email,
-- categories, and item-level close-time overrides.
-- ============================================================

USE silentbidpro;

DELIMITER //

CREATE PROCEDURE sbb_add_column_if_missing(
    IN table_name_in VARCHAR(64),
    IN column_name_in VARCHAR(64),
    IN alter_sql_in TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = table_name_in
          AND COLUMN_NAME = column_name_in
    ) THEN
        SET @sbb_alter_sql = alter_sql_in;
        PREPARE sbb_stmt FROM @sbb_alter_sql;
        EXECUTE sbb_stmt;
        DEALLOCATE PREPARE sbb_stmt;
    END IF;
END//

DELIMITER ;

CREATE TABLE IF NOT EXISTS organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    brand_primary VARCHAR(20),
    brand_accent VARCHAR(20),
    logo_url VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_slug (slug),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    event_date DATE,
    auction_start_time DATETIME,
    auction_end_time DATETIME NOT NULL,
    timezone VARCHAR(64) DEFAULT 'America/Los_Angeles',
    payment_mode ENUM('combined', 'item', 'both') DEFAULT 'both',
    status ENUM('draft', 'open', 'closed', 'archived') DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY uq_org_event_slug (organization_id, slug),
    INDEX idx_organization_id (organization_id),
    INDEX idx_status (status),
    INDEX idx_auction_end_time (auction_end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    UNIQUE KEY uq_event_category_name (event_id, name),
    INDEX idx_event_id (event_id),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL sbb_add_column_if_missing(
    'users',
    'email',
    'ALTER TABLE users ADD COLUMN email VARCHAR(255) AFTER full_name'
);

CALL sbb_add_column_if_missing(
    'items',
    'event_id',
    'ALTER TABLE items ADD COLUMN event_id INT UNSIGNED NULL AFTER id'
);

CALL sbb_add_column_if_missing(
    'items',
    'category_id',
    'ALTER TABLE items ADD COLUMN category_id INT UNSIGNED NULL AFTER event_id'
);

CALL sbb_add_column_if_missing(
    'items',
    'close_time_override',
    'ALTER TABLE items ADD COLUMN close_time_override DATETIME NULL AFTER auction_end_time'
);

INSERT INTO organizations (name, slug, brand_primary, brand_accent)
SELECT 'Silent Bid Pro', 'silent-bid-pro', '#2f6f5e', '#f2b84b'
WHERE NOT EXISTS (
    SELECT 1 FROM organizations WHERE slug = 'silent-bid-pro'
);

INSERT INTO events (
    organization_id,
    name,
    slug,
    event_date,
    auction_start_time,
    auction_end_time,
    payment_mode,
    status
)
SELECT
    o.id,
    'Default Auction',
    'default-auction',
    CURDATE(),
    COALESCE(MIN(i.auction_start_time), NOW()),
    COALESCE(MAX(i.auction_end_time), DATE_ADD(NOW(), INTERVAL 2 HOUR)),
    'both',
    'open'
FROM organizations o
LEFT JOIN items i ON 1 = 1
WHERE o.slug = 'silent-bid-pro'
  AND NOT EXISTS (
      SELECT 1 FROM events e
      WHERE e.organization_id = o.id
        AND e.slug = 'default-auction'
  )
GROUP BY o.id;

UPDATE items i
JOIN organizations o ON o.slug = 'silent-bid-pro'
JOIN events e ON e.organization_id = o.id AND e.slug = 'default-auction'
SET i.event_id = e.id
WHERE i.event_id IS NULL;

DROP PROCEDURE sbb_add_column_if_missing;
