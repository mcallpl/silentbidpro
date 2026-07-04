<?php
// ============================================================
// CREATE "PEOPLESTAR ENTERPRISES" TEST EVENT
//
// Duplicates the existing Ryan's Reach auction (organization + items)
// into a brand-new, fully separate event called "PeopleStar Enterprises",
// styled after peoplestar.com (charcoal + teal). This gives you a second,
// isolated auction so a different group of testers can run in parallel
// without ever touching the Ryan's Reach data.
//
// Safe to run more than once: it reuses the org/event if they already
// exist and only copies items the first time.
//
// Usage:
//   php scripts/create-peoplestar-event.php [source-event-slug]
// If no source slug is given it auto-detects the Ryan's Reach event.
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';

function line($msg) { echo $msg . PHP_EOL; }

// ---- Configuration for the new event ----
$ORG_NAME     = 'PeopleStar Enterprises';
$ORG_SLUG     = 'peoplestar-enterprises';
$EVENT_NAME   = 'PeopleStar Enterprises Benefit Auction';
$EVENT_SLUG   = 'peoplestar-enterprises';
$BRAND_PRIMARY = '#141A1F'; // charcoal
$BRAND_ACCENT  = '#12B5A5'; // teal
$TAGLINE       = 'Software, Built to Be Used.';

// ---- 1. Locate the source (Ryan's Reach) event ----
$sourceSlug = $argv[1] ?? null;
if ($sourceSlug) {
    $source = dbGetRow(
        "SELECT e.id, e.slug, e.organization_id, e.name, e.timezone, e.payment_mode
         FROM events e WHERE e.slug = ?",
        [(string)$sourceSlug]
    );
} else {
    $source = dbGetRow(
        "SELECT e.id, e.slug, e.organization_id, e.name, e.timezone, e.payment_mode
         FROM events e JOIN organizations o ON o.id = e.organization_id
         WHERE o.name LIKE '%Ryan%'
         ORDER BY e.id DESC LIMIT 1"
    );
}

if (!$source) {
    fwrite(STDERR, "Could not find the Ryan's Reach source event. Pass its slug explicitly:\n  php scripts/create-peoplestar-event.php <source-event-slug>\n");
    exit(1);
}
$srcEventId = (int)$source['id'];
line("Source event: \"{$source['name']}\" (id {$srcEventId})");

$srcItems = dbGetAll(
    "SELECT item_number, title, description, image_url, fair_market_value,
            starting_bid, min_increment, buy_now_price
     FROM items WHERE event_id = ? ORDER BY item_number ASC",
    [$srcEventId]
);
if (!$srcItems) {
    fwrite(STDERR, "Source event has no items to copy.\n");
    exit(1);
}
line("Found " . count($srcItems) . " item(s) to replicate.");

// ---- 2. Create (or reuse) the PeopleStar organization ----
$org = dbGetRow("SELECT id FROM organizations WHERE slug = ?", [$ORG_SLUG]);
if ($org) {
    $orgId = (int)$org['id'];
    dbUpdate(
        "UPDATE organizations SET name = ?, brand_primary = ?, brand_accent = ? WHERE id = ?",
        [$ORG_NAME, $BRAND_PRIMARY, $BRAND_ACCENT, $orgId]
    );
    line("Reusing organization \"{$ORG_NAME}\" (id {$orgId}).");
} else {
    $orgId = (int)dbInsert(
        "INSERT INTO organizations (name, slug, brand_primary, brand_accent, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())",
        [$ORG_NAME, $ORG_SLUG, $BRAND_PRIMARY, $BRAND_ACCENT]
    );
    line("Created organization \"{$ORG_NAME}\" (id {$orgId}).");
}

// ---- 3. Create (or reuse) the PeopleStar event ----
$startTime = date('Y-m-d H:i:s');
$endTime   = date('Y-m-d H:i:s', strtotime('+14 days'));
$timezone  = $source['timezone'] ?: 'America/Los_Angeles';
$paymentMode = $source['payment_mode'] ?: 'both';

$event = dbGetRow("SELECT id FROM events WHERE slug = ?", [$EVENT_SLUG]);
if ($event) {
    $newEventId = (int)$event['id'];
    dbUpdate(
        "UPDATE events SET organization_id = ?, name = ?, status = 'open',
                organization_name = ?, event_description = ?,
                primary_color = ?, accent_color = ? WHERE id = ?",
        [$orgId, $EVENT_NAME, $ORG_NAME, $TAGLINE, $BRAND_PRIMARY, $BRAND_ACCENT, $newEventId]
    );
    line("Reusing event \"{$EVENT_NAME}\" (id {$newEventId}).");
} else {
    $newEventId = (int)dbInsert(
        "INSERT INTO events
            (organization_id, name, slug, event_date, auction_start_time, auction_end_time,
             timezone, primary_color, accent_color, organization_name, event_description,
             payment_mode, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())",
        [$orgId, $EVENT_NAME, $EVENT_SLUG, $startTime, $startTime, $endTime,
         $timezone, $BRAND_PRIMARY, $BRAND_ACCENT, $ORG_NAME, $TAGLINE, $paymentMode]
    );
    line("Created event \"{$EVENT_NAME}\" (id {$newEventId}).");
}

// ---- 4. Copy items (only if the new event has none yet) ----
$existingItems = (int)dbGetValue("SELECT COUNT(*) FROM items WHERE event_id = ?", [$newEventId]);
if ($existingItems > 0) {
    line("Event already has {$existingItems} item(s); skipping item copy (already set up).");
} else {
    // Start item numbers above any in use so they never collide.
    $nextNumber = max(600, (int)dbGetValue("SELECT COALESCE(MAX(item_number), 0) FROM items")) + 1;
    $copied = 0;
    foreach ($srcItems as $it) {
        dbInsert(
            "INSERT INTO items
                (event_id, category_id, item_number, title, description, image_url,
                 fair_market_value, starting_bid, min_increment, buy_now_price,
                 current_high_bid, current_high_bidder_id,
                 auction_start_time, auction_end_time, close_time_override, is_closed,
                 created_at, updated_at)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, ?, ?, NULL, 0, NOW(), NOW())",
            [
                $newEventId, $nextNumber, $it['title'], $it['description'], $it['image_url'],
                $it['fair_market_value'], $it['starting_bid'], $it['min_increment'], $it['buy_now_price'],
                $startTime, $endTime
            ]
        );
        $nextNumber++;
        $copied++;
    }
    line("Copied {$copied} item(s) into the new event (fresh, zero bids).");
}

// ---- 5. Summary ----
$domain = defined('APP_DOMAIN') ? rtrim(APP_DOMAIN, '/') : 'https://silentbidpro.peoplestar.com';
line('');
line('Done. Two isolated auctions are now live:');
line("  Ryan's Reach   -> {$domain}/items.php?event=" . $source['slug']);
line("  PeopleStar     -> {$domain}/items.php?event={$EVENT_SLUG}");
