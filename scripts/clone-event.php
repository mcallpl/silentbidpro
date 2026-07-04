<?php
// ============================================================
// CLONE EVENT
//
// Copies any event's items into a brand-new, fully separate event.
// Use it to keep a pristine "Test Event" master and spin up fresh,
// disposable copies to test with as often as you like — the source
// is never modified.
//
// Usage:
//   php scripts/clone-event.php --source=<slug> --name="New Name" [options]
//
// Options:
//   --source=<slug>     Slug of the event to copy items FROM (required)
//   --name="..."        Display name of the new event (required)
//   --slug=<slug>       Slug for the new event (default: derived from name,
//                       auto-numbered if it already exists)
//   --org="..."         Create/reuse an organization with this name for the
//                       new event (default: reuse the source event's org)
//   --primary=#RRGGBB   Brand primary color (only when --org creates a new org)
//   --accent=#RRGGBB    Brand accent color (only when --org creates a new org)
//   --status=open|draft Status of the new event (default: open)
//   --minutes=<n>       Auction length in minutes from now (default: 20160 = 14 days)
//
// Examples:
//   php scripts/clone-event.php --source=ryans-reach-benefit-gala \
//       --name="Test Event (Master)" --slug=test-event \
//       --org="Test Kitchen" --status=draft
//   php scripts/clone-event.php --source=test-event --name="Test Run"
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-helpers.php';

function line($m) { echo $m . PHP_EOL; }
function fail($m) { fwrite(STDERR, $m . PHP_EOL); exit(1); }

// ---- Parse --key=value args ----
$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z]+)=(.*)$/i', $arg, $m)) {
        $opts[strtolower($m[1])] = $m[2];
    }
}

$sourceSlug = $opts['source'] ?? null;
$newName    = $opts['name'] ?? null;
if (!$sourceSlug || !$newName) {
    fail("Required: --source=<slug> and --name=\"New Name\".\nSee the header of this file for usage.");
}

$status  = ($opts['status'] ?? 'open') === 'draft' ? 'draft' : 'open';
$minutes = isset($opts['minutes']) ? max(1, (int)$opts['minutes']) : 20160; // 14 days default

function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'event';
}

// ---- Locate the source event + its items ----
$source = dbGetRow(
    "SELECT id, organization_id, timezone FROM events WHERE slug = ?",
    [(string)$sourceSlug]
);
if (!$source) {
    fail("Source event '{$sourceSlug}' not found.");
}
$srcItems = dbGetAll(
    "SELECT item_number, title, description, image_url, fair_market_value,
            starting_bid, min_increment, buy_now_price
     FROM items WHERE event_id = ? ORDER BY item_number ASC",
    [(int)$source['id']]
);
if (!$srcItems) {
    fail("Source event '{$sourceSlug}' has no items to copy.");
}
line("Source: '{$sourceSlug}' (" . count($srcItems) . " items).");

// ---- Resolve the organization ----
if (!empty($opts['org'])) {
    $orgName = $opts['org'];
    $orgSlug = slugify($orgName);
    $primary = $opts['primary'] ?? '#245C4F';
    $accent  = $opts['accent'] ?? '#F2B84B';
    $org = dbGetRow("SELECT id FROM organizations WHERE slug = ?", [$orgSlug]);
    if ($org) {
        $orgId = (int)$org['id'];
        line("Reusing organization '{$orgName}' (id {$orgId}).");
    } else {
        $orgId = (int)dbInsert(
            "INSERT INTO organizations (name, slug, brand_primary, brand_accent, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())",
            [$orgName, $orgSlug, $primary, $accent]
        );
        line("Created organization '{$orgName}' (id {$orgId}).");
    }
} else {
    $orgId = (int)$source['organization_id'];
    line("Reusing source event's organization (id {$orgId}).");
}

// ---- Pick a unique slug for the new event ----
$baseSlug = !empty($opts['slug']) ? slugify($opts['slug']) : slugify($newName);
$newSlug = $baseSlug;
$n = 1;
while (dbGetValue("SELECT id FROM events WHERE slug = ?", [$newSlug])) {
    $n++;
    $newSlug = $baseSlug . '-' . $n;
}

// ---- Create the event ----
$startTime = date('Y-m-d H:i:s');
$endTime   = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
$timezone  = $source['timezone'] ?: 'America/Los_Angeles';

$newEventId = (int)dbInsert(
    "INSERT INTO events
        (organization_id, name, slug, event_date, auction_start_time, auction_end_time,
         timezone, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
    [$orgId, $newName, $newSlug, $startTime, $startTime, $endTime, $timezone, $status]
);
line("Created event '{$newName}' (id {$newEventId}, slug '{$newSlug}', status {$status}).");

// ---- Copy items fresh (zero bids) ----
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
line("Copied {$copied} item(s) into the new event.");

$domain = defined('APP_DOMAIN') ? rtrim(APP_DOMAIN, '/') : 'https://silentbidpro.peoplestar.com';
line('');
line("New auction link: {$domain}/items.php?event={$newSlug}");
