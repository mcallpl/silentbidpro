<?php
// ============================================================
// EVENT HELPERS
// Reusable organization/event accessors with legacy fallbacks
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

/**
 * Fetch the active/default event when the event foundation has been migrated.
 * @return array|null Event data or null when unavailable
 */
function getActiveEvent() {
    if (!dbTableExists('events') || !dbTableExists('organizations')) {
        return null;
    }

    return dbGetRow(
        "SELECT
            e.id,
            e.organization_id,
            e.name,
            e.slug,
            e.event_date,
            e.auction_start_time,
            e.auction_end_time,
            e.timezone,
            e.payment_mode,
            e.status,
            o.name AS organization_name,
            o.brand_primary,
            o.brand_accent,
            o.logo_url
         FROM events e
         JOIN organizations o ON o.id = e.organization_id
         WHERE e.status IN ('open', 'draft')
         ORDER BY
            CASE WHEN e.status = 'open' THEN 0 ELSE 1 END,
            e.auction_end_time ASC
         LIMIT 1"
    );
}

/**
 * Fetch event categories for browse filters.
 * @param int $event_id Event ID
 * @return array
 */
function getEventCategories($event_id) {
    if (!$event_id || !dbTableExists('categories')) {
        return [];
    }

    return dbGetAll(
        "SELECT id, name
         FROM categories
         WHERE event_id = ?
         ORDER BY sort_order ASC, name ASC",
        [(int)$event_id]
    );
}

/**
 * Get the effective close time for an item.
 * @param array $item Item row
 * @param array|null $event Event row
 * @return string
 */
function getEffectiveItemCloseTime($item, $event = null) {
    if (!empty($item['close_time_override'])) {
        return $item['close_time_override'];
    }

    if (!empty($item['auction_end_time'])) {
        return $item['auction_end_time'];
    }

    return $event['auction_end_time'] ?? '';
}
?>
