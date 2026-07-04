-- ============================================================
-- RESET AUCTION DATA
-- Clears bidder/auction/event records while preserving admin access.
--
-- Use before loading a fresh demo or starting a new auction.
-- ============================================================

USE silentbidpro;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE audit_log;
TRUNCATE TABLE transactions;
TRUNCATE TABLE bids;
TRUNCATE TABLE favorites;
TRUNCATE TABLE sessions;
TRUNCATE TABLE verification_codes;
TRUNCATE TABLE users;
TRUNCATE TABLE items;
TRUNCATE TABLE categories;
TRUNCATE TABLE events;
TRUNCATE TABLE organizations;

SET FOREIGN_KEY_CHECKS = 1;
