-- ============================================================
-- SILENT BID BUDDY — MySQL Database Schema
-- Auction Platform for Nonprofit Galas
-- ============================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS silentbidbuddy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE silentbidbuddy;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(255),
    stripe_customer_id VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_phone_number (phone_number),
    INDEX idx_stripe_customer_id (stripe_customer_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ITEMS TABLE (Auction items)
-- ============================================================
CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_number INT UNSIGNED NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT,
    image_url VARCHAR(500),
    fair_market_value DECIMAL(10, 2),
    starting_bid DECIMAL(10, 2) NOT NULL,
    min_increment DECIMAL(10, 2) NOT NULL DEFAULT 5.00,
    buy_now_price DECIMAL(10, 2),
    current_high_bid DECIMAL(10, 2) DEFAULT 0.00,
    current_high_bidder_id INT UNSIGNED,
    auction_start_time DATETIME,
    auction_end_time DATETIME NOT NULL,
    is_closed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (current_high_bidder_id) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_item_number (item_number),
    INDEX idx_is_closed (is_closed),
    INDEX idx_auction_end_time (auction_end_time),
    INDEX idx_current_high_bidder_id (current_high_bidder_id),
    INDEX idx_created_at (created_at),
    FULLTEXT INDEX ft_title_desc (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BIDS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS bids (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    bid_amount DECIMAL(10, 2) NOT NULL,
    max_bid_amount DECIMAL(10, 2),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_item_id (item_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_item_created (item_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRANSACTIONS TABLE (Payment tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    stripe_payment_intent_id VARCHAR(255),
    stripe_checkout_session_id VARCHAR(255),
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'paid', 'failed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,

    INDEX idx_user_id (user_id),
    INDEX idx_item_id (item_id),
    INDEX idx_status (status),
    INDEX idx_stripe_payment_intent_id (stripe_payment_intent_id),
    INDEX idx_stripe_checkout_session_id (stripe_checkout_session_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VERIFICATION CODES TABLE (SMS authentication)
-- ============================================================
CREATE TABLE IF NOT EXISTS verification_codes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    code VARCHAR(6) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    attempts INT DEFAULT 0,
    is_used TINYINT(1) DEFAULT 0,

    INDEX idx_phone_number (phone_number),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SESSIONS TABLE (User session management)
-- ============================================================
CREATE TABLE IF NOT EXISTS sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDIT LOG TABLE (Activity tracking)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    user_id INT UNSIGNED,
    item_id INT UNSIGNED,
    description VARCHAR(500),
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_item_id (item_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Cleanup Task: Remove expired verification codes (run periodically)
-- ============================================================
-- DELETE FROM verification_codes WHERE expires_at < NOW() AND is_used = 1;
-- DELETE FROM sessions WHERE expires_at < NOW();
