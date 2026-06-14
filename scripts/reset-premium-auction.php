<?php
// ============================================================
// RESET PREMIUM AUCTION
// Applies current auction migrations, clears auction data, and
// loads the premium generated-image demo auction.
// ============================================================

require_once __DIR__ . '/../config.php';

$files = [
    __DIR__ . '/../sql/migrations/001_event_foundation.sql',
    __DIR__ . '/../sql/migrations/002_favorites.sql',
    __DIR__ . '/../sql/seeds/000_reset_auction_data.sql',
    __DIR__ . '/../sql/seeds/001_premium_test_items.sql',
];

$db = getDB();

foreach ($files as $file) {
    if (!is_readable($file)) {
        fwrite(STDERR, "Missing SQL file: {$file}\n");
        exit(1);
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Unable to read SQL file: {$file}\n");
        exit(1);
    }

    $statements = splitSqlStatements($sql);
    foreach ($statements as $statement) {
        if (!$db->query($statement)) {
            fwrite(STDERR, "Failed running {$file}: {$db->error}\nStatement: {$statement}\n");
            exit(1);
        }
    }

    echo "Applied: " . basename($file) . PHP_EOL;
}

$counts = [
    'organizations' => 'organizations',
    'events' => 'events',
    'categories' => 'categories',
    'items' => 'items',
    'users' => 'users',
    'bids' => 'bids',
    'transactions' => 'transactions',
];

echo PHP_EOL . "Database now contains:" . PHP_EOL;
foreach ($counts as $label => $table) {
    $result = $db->query("SELECT COUNT(*) AS total FROM {$table}");
    $row = $result ? $result->fetch_assoc() : ['total' => 'unknown'];
    echo "- {$label}: {$row['total']}" . PHP_EOL;
    if ($result) {
        $result->free();
    }
}

function splitSqlStatements($sql) {
    $delimiter = ';';
    $buffer = '';
    $statements = [];
    $lines = preg_split('/\R/', $sql);

    foreach ($lines as $line) {
        if (preg_match('/^\s*DELIMITER\s+(.+)\s*$/i', $line, $matches)) {
            $delimiter = $matches[1];
            continue;
        }

        $buffer .= $line . PHP_EOL;
        if (substr(rtrim($buffer), -strlen($delimiter)) === $delimiter) {
            $statement = substr(rtrim($buffer), 0, -strlen($delimiter));
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}
