-- ============================================================
-- MIGRATION: Fix phone number uniqueness constraint
-- Created: 2026-06-15
-- Purpose: Allow same phone number across different events
--          Only enforce uniqueness within a single event
-- ============================================================

-- Drop the global UNIQUE constraint on phone_number (if it exists)
ALTER TABLE users DROP INDEX IF EXISTS phone_number;

-- Create a composite UNIQUE constraint on (phone_number, event_id)
-- This allows the same phone number in different events,
-- but prevents duplicate phone numbers within the same event
ALTER TABLE users ADD UNIQUE KEY IF NOT EXISTS uq_phone_event (phone_number, event_id);

-- Ensure we have an index for phone number lookups
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_phone_number (phone_number);
