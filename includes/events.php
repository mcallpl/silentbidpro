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

    // Public DEFAULT auction (no ?event= link) must be OPEN only. A 'draft' event
    // is one an admin is still preparing (unfinished items/prices) — it must not
    // silently become the auction every visitor sees. Drafts remain reachable via
    // an explicit ?event=<slug> preview link (see getEventBySlug).
    return dbGetRow(
        eventBaseSelect() . "
         WHERE e.status = 'open'
         ORDER BY e.auction_end_time ASC
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

    // 1) An explicit ?event=<slug> link ALWAYS wins and (re)pins the session to that
    //    auction. This lets a bidder follow a specific auction's link even if they
    //    previously landed on the default homepage (which pins them to the flagship
    //    event) — essential for running separate test groups on separate events.
    //    Bidding stays guarded to the pinned event, so this only changes which
    //    public auction the bidder is viewing, never lets them cross-bid.
    if (!empty($_GET['event'])) {
        $event = getEventBySlug((string)$_GET['event']);
        if ($event) {
            $_SESSION['bidder_event_id'] = (int)$event['id'];
            return $event;
        }
    }

    // PUBLIC FRONT DOOR (homepage): never expose a non-open event. A session
    // pinned to a draft/closed auction (e.g. a private test event) keeps its
    // pin for the bidding pages, but the landing page always wears the
    // currently OPEN event's face — display only, the pin is not modified.
    if (defined('SBB_PUBLIC_FRONT_DOOR') && SBB_PUBLIC_FRONT_DOOR) {
        if (!empty($_SESSION['bidder_event_id'])) {
            $pinned = getEventById((int)$_SESSION['bidder_event_id']);
            if ($pinned && ($pinned['status'] ?? '') === 'open') {
                return $pinned;
            }
        }
        $active = getActiveEvent();
        if ($active) {
            return $active;
        }
        // Nothing open at all — fall through to normal resolution.
    }

    // 2) No link: stay locked to the auction already pinned for this session.
    if (!empty($_SESSION['bidder_event_id'])) {
        $event = getEventById((int)$_SESSION['bidder_event_id']);
        if ($event) {
            return $event;
        }
        unset($_SESSION['bidder_event_id']); // stale/deleted event
    }

    // 3) Not pinned and no ?event= link: fall back to the single active event AND
    //    pin it. Without pinning here, a bidder who enters via a plain items.php
    //    link has bidderPinnedEventId() == 0, so item.php and place-bid.php skip
    //    the cross-auction guard entirely (could reach another auction's items by
    //    id), and the "current event" could silently flip to a sooner-ending event
    //    mid-session. Pinning locks them to one auction like the ?event= path does.
    $event = getActiveEvent();
    if ($event) {
        $_SESSION['bidder_event_id'] = (int)$event['id'];
    }
    return $event;
}

/**
 * The event id this browser session is pinned/locked to (0 if none yet).
 * Used to enforce that bidders act only within their own auction.
 * @return int
 */
function bidderPinnedEventId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($_SESSION['bidder_event_id']) ? (int)$_SESSION['bidder_event_id'] : 0;
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

/**
 * Record (or refresh) a bidder's membership in an event — the durable
 * user<->event bond (user_events junction, migration 013). Idempotent;
 * touches last_active_at on repeat calls.
 */
function touchUserEvent($user_id, $event_id) {
    $user_id = (int)$user_id; $event_id = (int)$event_id;
    if (!$user_id || !$event_id) return;
    dbQuery(
        "INSERT INTO user_events (user_id, event_id)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE last_active_at = NOW()",
        [$user_id, $event_id]
    );
}

/**
 * Every event this bidder belongs to, freshest first: open events, then
 * drafts (private, link-only events they were invited into), then closed.
 * Powers the sign-in event chooser and welcome-back routing.
 */
function getUserEvents($user_id) {
    return dbGetAll(
        "SELECT e.id, e.slug, e.name, e.status, ue.first_joined_at, ue.last_active_at
         FROM user_events ue
         JOIN events e ON e.id = ue.event_id
         WHERE ue.user_id = ?
         ORDER BY FIELD(e.status, 'open', 'draft', 'closed'), ue.last_active_at DESC",
        [(int)$user_id]
    ) ?: [];
}
