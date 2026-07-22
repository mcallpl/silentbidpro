-- ============================================================
-- MIGRATION 013: user_events — bidder <-> event membership
-- Created: 2026-07-22
--
-- Since 008 a bidder is ONE global account (unique phone); nothing in
-- `users` says which auctions they belong to (users.event_id is only the
-- registration origin, often NULL). This junction table is the durable
-- record: one row per (user, event), written at login and at first bid.
-- It powers "welcome back to YOUR auction" routing, the multi-event
-- chooser at sign-in, and per-event guest counts for organizers.
-- ============================================================

CREATE TABLE IF NOT EXISTS user_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    first_joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_active_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_event (user_id, event_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_first_joined (event_id, first_joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Backfill from every trace of real membership ----

-- Registration origin (users.event_id, where set)
INSERT IGNORE INTO user_events (user_id, event_id, first_joined_at)
SELECT u.id, u.event_id, u.created_at
FROM users u JOIN events e ON e.id = u.event_id;

-- Anyone who ever bid in an event belongs to it
INSERT IGNORE INTO user_events (user_id, event_id, first_joined_at)
SELECT b.user_id, i.event_id, MIN(b.created_at)
FROM bids b JOIN items i ON i.id = b.item_id
GROUP BY b.user_id, i.event_id;

-- Favorites imply membership
INSERT IGNORE INTO user_events (user_id, event_id, first_joined_at)
SELECT f.user_id, i.event_id, MIN(f.created_at)
FROM favorites f JOIN items i ON i.id = f.item_id
GROUP BY f.user_id, i.event_id;

-- Payments certainly imply membership
INSERT IGNORE INTO user_events (user_id, event_id, first_joined_at)
SELECT t.user_id, i.event_id, MIN(t.created_at)
FROM transactions t JOIN items i ON i.id = t.item_id
GROUP BY t.user_id, i.event_id;
