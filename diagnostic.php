<?php
// ============================================================
// DIAGNOSTIC PAGE - Real-time system health check
// Shows exactly what's happening with bids
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-helpers.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>SBB Diagnostic</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #00ff00; padding: 20px; }
        .section { margin: 20px 0; padding: 10px; border: 1px solid #00ff00; }
        .ok { color: #00ff00; }
        .error { color: #ff0000; }
        .warning { color: #ffaa00; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px; border: 1px solid #333; }
    </style>
</head>
<body>

<h1>🔍 SILENT BID PRO DIAGNOSTIC</h1>
<p>Last check: <?php echo date('Y-m-d H:i:s'); ?></p>

<div class="section">
    <h2>DATABASE INTEGRITY CHECK</h2>
    <?php
    $total_users = dbGetValue("SELECT COUNT(*) FROM users");
    $total_items = dbGetValue("SELECT COUNT(*) FROM items");
    $total_bids = dbGetValue("SELECT COUNT(*) FROM bids");

    echo "<p>Users: <span class='ok'>" . $total_users . "</span></p>";
    echo "<p>Items: <span class='ok'>" . $total_items . "</span></p>";
    echo "<p>Bids: <span class='" . ($total_bids > 0 ? 'ok' : 'error') . "'>" . $total_bids . " " . ($total_bids > 0 ? "✓" : "❌") . "</span></p>";
    ?>
</div>

<div class="section">
    <h2>ALL USERS</h2>
    <table>
        <tr><td>ID</td><td>Name</td><td>Phone</td><td>Bids Placed</td></tr>
        <?php
        $users = dbGetAll("SELECT u.id, u.full_name, u.phone_number, COUNT(b.id) as bid_count
                          FROM users u
                          LEFT JOIN bids b ON b.user_id = u.id
                          GROUP BY u.id");
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>" . $u['id'] . "</td>";
            echo "<td>" . $u['full_name'] . "</td>";
            echo "<td>" . $u['phone_number'] . "</td>";
            echo "<td class='" . ($u['bid_count'] > 0 ? 'ok' : 'warning') . "'>" . $u['bid_count'] . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
</div>

<div class="section">
    <h2>ITEMS WITH BIDS</h2>
    <table>
        <tr><td>Item #</td><td>Title</td><td>DB High Bid</td><td>Bid Count</td><td>Consistency</td></tr>
        <?php
        $items = dbGetAll(
            "SELECT i.item_number, i.title, i.current_high_bid, COUNT(b.id) as bid_count
             FROM items i
             LEFT JOIN bids b ON b.item_id = i.id
             GROUP BY i.id
             HAVING i.current_high_bid > 0 OR COUNT(b.id) > 0
             ORDER BY i.item_number ASC"
        );

        foreach ($items as $item) {
            $consistent = ($item['bid_count'] > 0) ? 'ok' : 'error';
            $consistency_text = ($item['bid_count'] > 0) ? 'OK' : 'MISMATCH!';

            echo "<tr>";
            echo "<td>" . $item['item_number'] . "</td>";
            echo "<td>" . $item['title'] . "</td>";
            echo "<td>$" . number_format($item['current_high_bid'], 2) . "</td>";
            echo "<td>" . $item['bid_count'] . "</td>";
            echo "<td class='$consistent'>$consistency_text</td>";
            echo "</tr>";
        }

        if (empty($items)) {
            echo "<tr><td colspan='5' class='warning'>⚠️ NO ITEMS WITH BIDS</td></tr>";
        }
        ?>
    </table>
</div>

<div class="section">
    <h2>ALL BIDS (DETAILED)</h2>
    <table>
        <tr><td>Bid ID</td><td>Item #</td><td>User</td><td>Amount</td><td>Time</td></tr>
        <?php
        $bids = dbGetAll(
            "SELECT b.id, i.item_number, u.full_name, b.bid_amount, b.created_at
             FROM bids b
             JOIN items i ON i.id = b.item_id
             JOIN users u ON u.id = b.user_id
             ORDER BY b.created_at DESC"
        );

        foreach ($bids as $bid) {
            echo "<tr>";
            echo "<td>" . $bid['id'] . "</td>";
            echo "<td>#" . $bid['item_number'] . "</td>";
            echo "<td>" . $bid['full_name'] . "</td>";
            echo "<td>$" . number_format($bid['bid_amount'], 2) . "</td>";
            echo "<td>" . $bid['created_at'] . "</td>";
            echo "</tr>";
        }

        if (empty($bids)) {
            echo "<tr><td colspan='5' class='error'>❌ NO BIDS IN SYSTEM</td></tr>";
        }
        ?>
    </table>
</div>

<div class="section">
    <h2>SESSIONS</h2>
    <p>Active sessions: <span class='ok'><?php echo dbGetValue("SELECT COUNT(*) FROM sessions WHERE expires_at > NOW()"); ?></span></p>
    <table>
        <tr><td>User ID</td><td>Expires At</td><td>Status</td></tr>
        <?php
        $sessions = dbGetAll("SELECT user_id, expires_at FROM sessions ORDER BY expires_at DESC");
        foreach ($sessions as $s) {
            $status = (strtotime($s['expires_at']) > time()) ? 'ACTIVE' : 'EXPIRED';
            $status_class = (strtotime($s['expires_at']) > time()) ? 'ok' : 'error';
            echo "<tr>";
            echo "<td>" . $s['user_id'] . "</td>";
            echo "<td>" . $s['expires_at'] . "</td>";
            echo "<td class='$status_class'>$status</td>";
            echo "</tr>";
        }
        ?>
    </table>
</div>

<div class="section">
    <h2>API ENDPOINTS TEST</h2>
    <p><a href="api/bidding/get-live-feed.php?id=1">Test Live Feed API (Item #1)</a></p>
    <p><a href="api/bidding/get-item.php?id=1">Test Get Item API (Item #1)</a></p>
</div>

<div class="section">
    <h2>SYSTEM STATUS</h2>
    <?php
    $issues = [];

    if ($total_bids == 0) {
        $issues[] = "❌ NO BIDS - System may not be working";
    }
    if ($total_users < 2) {
        $issues[] = "⚠️ Only " . $total_users . " user(s) - Need more users to test";
    }

    // Check for bid/item consistency
    $inconsistent = dbGetAll(
        "SELECT i.item_number, i.current_high_bid, COUNT(b.id) as bid_count
         FROM items i
         LEFT JOIN bids b ON b.item_id = i.id
         GROUP BY i.id
         HAVING (i.current_high_bid > 0 AND COUNT(b.id) = 0) OR (i.current_high_bid = 0 AND COUNT(b.id) > 0)"
    );

    if (!empty($inconsistent)) {
        $issues[] = "❌ DATABASE INCONSISTENCY: Items with bids but no bid records (or vice versa)";
    }

    if (empty($issues)) {
        echo "<p class='ok'>✓ All systems nominal</p>";
    } else {
        foreach ($issues as $issue) {
            echo "<p class='error'>$issue</p>";
        }
    }
    ?>
</div>

</body>
</html>
?>
