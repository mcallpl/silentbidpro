-- ============================================================
-- MIGRATION 012: Per-event fundraising goal
-- Created: 2026-07-22
-- The Command Center dashboard shows progress toward a goal;
-- until now no goal was stored anywhere. NULL = no goal set
-- (the dashboard then shows raised/collected without a % bar).
-- ============================================================

ALTER TABLE events ADD COLUMN fundraising_goal DECIMAL(12,2) NULL DEFAULT NULL AFTER payment_mode;
