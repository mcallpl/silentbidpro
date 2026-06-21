-- ============================================================
-- MIGRATION: Add notification delivery tracking and retry support
-- Created: 2026-06-21
-- ============================================================

-- Add delivery_status column to existing notifications table if it doesn't exist
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS delivery_status ENUM('pending', 'sent', 'failed') DEFAULT 'pending';

-- Create notifications_log table for tracking delivery attempts and retries
CREATE TABLE IF NOT EXISTS notifications_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED,
    notification_type VARCHAR(50) NOT NULL,
    delivery_channel ENUM('push', 'sms', 'both', 'in_app') DEFAULT 'push',
    delivery_status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempt_number INT DEFAULT 1,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    response_data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    next_retry_at DATETIME,

    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,

    INDEX idx_notification_id (notification_id),
    INDEX idx_user_id (user_id),
    INDEX idx_delivery_status (delivery_status),
    INDEX idx_attempt_number (attempt_number),
    INDEX idx_next_retry_at (next_retry_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for querying notifications that need retry
CREATE INDEX IF NOT EXISTS idx_pending_retries ON notifications_log (delivery_status, next_retry_at)
WHERE next_retry_at <= NOW() AND attempt_number < max_attempts;

-- Add composite index for efficient delivery tracking per user
CREATE INDEX IF NOT EXISTS idx_user_delivery_status ON notifications_log (user_id, delivery_status, created_at);
