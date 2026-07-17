-- ============================================================
-- Migration 011: Card on file, combined auto-charge, donations,
--                post-payment pickup instructions
--
-- Additive only. Backs the 2026-07-16 meeting fixes:
--  * Bidders save a card (Stripe SetupIntent via Checkout) before
--    their first bid; winners are auto-charged one combined total
--    when the auction fully closes.
--  * Direct "Donate Now" contributions, recorded per event.
--  * Per-event pickup/delivery instructions shown after payment.
-- All settings are per-event/organization — nothing is specific
-- to any one client.
-- ============================================================

-- --- Users: saved payment method (customer id already exists) ---
ALTER TABLE users
    ADD COLUMN stripe_payment_method_id VARCHAR(255) NULL DEFAULT NULL AFTER stripe_customer_id,
    ADD COLUMN card_brand VARCHAR(20) NULL DEFAULT NULL AFTER stripe_payment_method_id,
    ADD COLUMN card_last4 VARCHAR(8) NULL DEFAULT NULL AFTER card_brand;

-- --- Events: per-event behavior switches + fulfillment text ---
ALTER TABLE events
    ADD COLUMN require_card_on_bid TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN auto_charge_on_close TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN donations_enabled TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN pickup_instructions TEXT NULL;

-- --- Donations: direct contributions, independent of items ---
CREATE TABLE IF NOT EXISTS donations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL DEFAULT NULL,
    donor_name VARCHAR(255) NULL DEFAULT NULL,
    donor_email VARCHAR(255) NULL DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    stripe_checkout_session_id VARCHAR(255) NULL DEFAULT NULL,
    stripe_payment_intent_id VARCHAR(255) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_donations_event (event_id),
    KEY idx_donations_session (stripe_checkout_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --- Transactions: auto-charge bookkeeping ---
-- One off-session PaymentIntent may pay several won items at once; every
-- covered transaction row gets the same stripe_payment_intent_id (column
-- already exists). attempts/last_error let the cron retry transient
-- failures but stop after hard declines (then the pay link is sent).
ALTER TABLE transactions
    ADD COLUMN auto_charge_attempts TINYINT NOT NULL DEFAULT 0,
    ADD COLUMN auto_charge_last_error VARCHAR(255) NULL DEFAULT NULL;
