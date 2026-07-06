-- ============================================================
-- 010 — DEVICE TOKENS (APNs native iOS push)
-- One row per device; a token belongs to whichever user last registered it.
-- ============================================================
CREATE TABLE IF NOT EXISTS device_tokens (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    token        VARCHAR(255) NOT NULL,                 -- APNs hex device token
    platform     VARCHAR(20)  NOT NULL DEFAULT 'ios',
    environment  ENUM('production','sandbox') NOT NULL DEFAULT 'production',
    is_active    TINYINT(1)   NOT NULL DEFAULT 1,
    last_used_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_device_token (token),
    KEY idx_device_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
