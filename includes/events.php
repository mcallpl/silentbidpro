<?php
// ============================================================
// EVENT HELPERS
// Reusable organization/event accessors with legacy fallbacks
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

/**
 * Shared SELECT for an event joined to its organization's branding.
 * Callers append their own WHERE/ORDER/LIMIT and params.
 * @return string SQL prefix
 */
function eventBaseSelect() {
    return "SELECT
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
         JOIN organizations o ON o.id = e.organization_id";
}

/**
 * Fetch the active/default event when the event foundation has been migrated.
 * Used as the fallback when a bidder has not been pinned to a specific event.
 * @return array|null Event data or null when unavailable
 */
function getActiveEvent() {
    if (!dbTableExists('events') || !dbTableExists('organizations')) {
        return null;
    }

    return dbGetRow(
        eventBaseSelect() . "
         WHERE e.status IN ('open', 'draft')
         ORDER BY
            CASE WHEN e.status = 'open' THEN 0 ELSE 1 END,
            e.auction_end_time ASC
         LIMIT 1"
    );
}

/**
 * Fetch a biddable event by its public slug (open or draft only).
 * @param string $slug Event slug from an ?event= link
 * @return array|null
 */
function getEventBySlug($slug) {
    if (empty($slug) || !dbTableExists('events') || !dbTableExists('organizations')) {
        return null;
    }

    return dbGetRow(
        eventBaseSelect() . " WHERE e.slug = ? AND e.status IN ('open', 'draft') LIMIT 1",
        [(string)$slug]
    );
}

/**
 * Fetch any event by id (used to re-resolve a session-pinned event).
 * @param int $id
 * @return array|null
 */
function getEventById($id) {
    if (empty($id) || !dbTableExists('events') || !dbTableExists('organizations')) {
        return null;
    }

    return dbGetRow(eventBaseSelect() . " WHERE e.id = ? LIMIT 1", [(int)$id]);
}

/**
 * Resolve the event a bidder is currently working in.
 *
 * This is what lets two separate groups test two separate auctions at the same
 * time. Resolution order:
 *   1. An explicit ?event=<slug> link pins that auction to the browser session.
 *   2. Otherwise, a previously pinned event for this session.
 *   3. Otherwise, fall back to the single active event (legacy behavior).
 *
 * config.php has already started the session before any output, so reading and
 * writing $_SESSION here is safe.
 *
 * @return array|null
 */
function getCurrentEvent() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1) Explicit link pins the session to that auction.
    if (!empty($_GET['event'])) {
        $event = getEventBySlug((string)$_GET['event']);
        if ($event) {
            $_SESSION['bidder_event_id'] = (int)$event['id'];
            return $event;
        }
    }

    // 2) Previously pinned event for this browser session.
    if (!empty($_SESSION['bidder_event_id'])) {
        $event = getEventById((int)$_SESSION['bidder_event_id']);
        if ($event) {
            return $event;
        }
        unset($_SESSION['bidder_event_id']); // stale/deleted event
    }

    // 3) Legacy fallback: the single active event.
    return getActiveEvent();
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
