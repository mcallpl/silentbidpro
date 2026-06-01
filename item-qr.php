<?php
// ============================================================
// ITEM QR CODE LANDING PAGE
// Handles QR code redirects with authentication check
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// Get item ID from URL parameter
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$item_id) {
    die("Invalid item ID");
}

// Check if user is authenticated
if (isAuthenticated()) {
    // User is logged in - redirect to item page
    $item_url = 'item.php?id=' . $item_id;
    header("Location: /silentbidbuddy/" . $item_url);
    exit;
} else {
    // User not logged in - redirect to registration with return URL
    $return_url = urlencode('item.php?id=' . $item_id);
    $register_url = 'index.php?return=' . $return_url;
    header("Location: /silentbidbuddy/" . $register_url);
    exit;
}
