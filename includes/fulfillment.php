<?php
// ============================================================
// FULFILLMENT / PICKUP INSTRUCTIONS
// Per-event "what happens after you pay" text with a generic
// default (including the shipping-responsibility disclaimer).
// ============================================================

require_once __DIR__ . '/db-helpers.php';

/**
 * The event's pickup/delivery instructions, falling back to a
 * sensible generic default. Plain text; render with nl2br().
 * @param int $event_id
 * @return string
 */
function getPickupInstructions($event_id) {
    $custom = $event_id ? dbGetValue(
        "SELECT pickup_instructions FROM events WHERE id = ?",
        [(int)$event_id]
    ) : null;

    if (is_string($custom) && trim($custom) !== '') {
        return trim($custom);
    }

    return "• The organizer will contact you to arrange pickup or delivery of your item(s).\n"
        . "• Smaller items are usually available for pickup at the event or the organizer's office.\n"
        . "• Winning bidders are responsible for shipping or transport of large items unless the item description says otherwise.\n"
        . "• Questions? Reply to any text from us or contact the event organizer directly.";
}

?>
