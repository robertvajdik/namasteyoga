<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

// Admin-only, no output before download headers.
ny_require_admin();

$pdo = ny_db();

// List only ny_* tables so we don't leak sibling apps that share the database.
$tables = $pdo->query(
    "SELECT TABLE_NAME
       FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_TYPE   = 'BASE TABLE'
        AND TABLE_NAME LIKE 'ny\\_%'
      ORDER BY TABLE_NAME"
)->fetchAll(PDO::FETCH_COLUMN);

$filename = 'namaste-backup-' . date('Y-m-d-Hi') . '.sql';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('Pragma: no-cache');

// Streaming — flush as we go so a large dump doesn't fill PHP memory.
while (ob_get_level() > 0) ob_end_clean();

echo "-- Namasté Yoga – database backup\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Tables:    " . count($tables) . "\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $t) {
    echo "-- ---------------------------------------------------------\n";
    echo "-- Table: `{$t}`\n";
    echo "-- ---------------------------------------------------------\n\n";

    echo "DROP TABLE IF EXISTS `{$t}`;\n";
    $create = $pdo->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_NUM);
    if ($create && isset($create[1])) {
        echo $create[1] . ";\n\n";
    }

    // Row count first — skip the INSERT header on empty tables.
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    if ($count === 0) {
        echo "-- (no rows)\n\n";
        continue;
    }

    // Column list, quoted with backticks in the exact table order.
    $cols = $pdo->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_COLUMN);
    $colList = '`' . implode('`, `', $cols) . '`';

    $stmt = $pdo->query("SELECT * FROM `{$t}`");
    $stmt->setFetchMode(PDO::FETCH_NUM);

    $chunk    = [];
    $maxChunk = 100;
    while ($row = $stmt->fetch()) {
        $vals = [];
        foreach ($row as $v) {
            if ($v === null) {
                $vals[] = 'NULL';
            } elseif (is_int($v) || is_float($v)) {
                $vals[] = (string)$v;
            } else {
                $vals[] = $pdo->quote((string)$v);
            }
        }
        $chunk[] = '(' . implode(', ', $vals) . ')';
        if (count($chunk) >= $maxChunk) {
            echo "INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $chunk) . ";\n";
            $chunk = [];
            flush();
        }
    }
    if ($chunk) {
        echo "INSERT INTO `{$t}` ({$colList}) VALUES\n" . implode(",\n", $chunk) . ";\n";
        flush();
    }
    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "-- End of backup\n";
