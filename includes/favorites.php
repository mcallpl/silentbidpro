<?php
// ============================================================
// FAVORITES HELPERS
// Bidder watchlist support
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db-helpers.php';

function favoritesAvailable() {
    return dbTableExists('favorites');
}

function isItemFavorited($user_id, $item_id) {
    if (!$user_id || !$item_id || !favoritesAvailable()) {
        return false;
    }

    return dbExists(
        'favorites',
        'user_id = ? AND item_id = ?',
        [(int)$user_id, (int)$item_id]
    );
}

function addFavorite($user_id, $item_id) {
    if (!favoritesAvailable()) {
        return false;
    }

    if (isItemFavorited($user_id, $item_id)) {
        return true;
    }

    return (bool)dbInsert(
        "INSERT INTO favorites (user_id, item_id, created_at) VALUES (?, ?, NOW())",
        [(int)$user_id, (int)$item_id]
    );
}

function removeFavorite($user_id, $item_id) {
    if (!favoritesAvailable()) {
        return false;
    }

    return (bool)dbDelete(
        "DELETE FROM favorites WHERE user_id = ? AND item_id = ?",
        [(int)$user_id, (int)$item_id]
    );
}
?>
