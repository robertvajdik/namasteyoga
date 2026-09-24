<?php
declare(strict_types=1);

/**
 * One-shot migration: w8dEp_users (WordPress) -> ny_users.
 *
 * Prerequisites:
 *   1. Import d210718_yoga.sql into the target database.
 *   2. Apply schema.sql to create ny_users / ny_classes / ny_reservations.
 *   3. Adjust config.php with the DB credentials.
 *
 * Usage:
 *   CLI:  php migrate.php
 *   Web:  open /migrate.php in the browser (any output).
 *
 * The script is idempotent: rerunning it will not create duplicate rows
 * because ny_users.email is UNIQUE and we use INSERT ... ON DUPLICATE KEY
 * UPDATE. Passwords are stored verbatim; ny_verify_password() handles the
 * legacy WordPress $P$ and $wp$ hash formats transparently.
 */

require __DIR__ . '/src/db.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$cfg    = ny_config();
$wpTbl  = $cfg['app']['wp_users_tbl'];
$pdo    = ny_db();

$out = function (string $s) { echo $s . PHP_EOL; };

$out('== Namasté Yoga user migration ==');
$out('Source table: ' . $wpTbl);
$out('Target table: ny_users');
$out('');

// Sanity checks.
try {
    $pdo->query("SELECT 1 FROM `{$wpTbl}` LIMIT 1");
} catch (PDOException $ex) {
    $out('ERROR: source table not found. Import d210718_yoga.sql first.');
    exit(1);
}
try {
    $pdo->query('SELECT 1 FROM ny_users LIMIT 1');
} catch (PDOException $ex) {
    $out('ERROR: ny_users table not found. Run schema.sql first.');
    exit(1);
}

$total = (int)$pdo->query("SELECT COUNT(*) FROM `{$wpTbl}` WHERE user_email <> ''")->fetchColumn();
$out("Source rows with a non-empty e-mail: {$total}");
if ($total === 0) {
    $out('Nothing to migrate.');
    exit(0);
}

$select = $pdo->query(
    "SELECT ID, user_login, user_email, user_pass, display_name, user_registered
       FROM `{$wpTbl}`
      WHERE user_email <> ''"
);

$insert = $pdo->prepare(
    'INSERT INTO ny_users (email, display_name, password_hash, is_guest, legacy_wp_id, created_at)
     VALUES (:email, :name, :hash, 0, :legacy, :created)
     ON DUPLICATE KEY UPDATE
        display_name  = VALUES(display_name),
        password_hash = VALUES(password_hash),
        legacy_wp_id  = VALUES(legacy_wp_id),
        is_guest      = 0'
);

$inserted = 0;
$updated  = 0;
$skipped  = 0;
$seen     = [];

$pdo->beginTransaction();
try {
    foreach ($select as $row) {
        $email = strtolower(trim((string)$row['user_email']));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $skipped++;
            continue;
        }
        if (isset($seen[$email])) {
            // WP allows duplicate e-mails; new schema does not. Keep the first.
            $skipped++;
            continue;
        }
        $seen[$email] = true;

        $name = trim((string)$row['display_name']);
        if ($name === '') {
            $name = trim((string)$row['user_login']) ?: $email;
        }
        $created = $row['user_registered'];
        if (!$created || $created === '0000-00-00 00:00:00') {
            $created = date('Y-m-d H:i:s');
        }

        $insert->execute([
            ':email'   => $email,
            ':name'    => $name,
            ':hash'    => (string)$row['user_pass'],
            ':legacy'  => (int)$row['ID'],
            ':created' => $created,
        ]);
        // rowCount() returns 1 for insert, 2 for update on MySQL client library.
        if ($insert->rowCount() === 1) {
            $inserted++;
        } else {
            $updated++;
        }
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    $out('FAILED: ' . $ex->getMessage());
    exit(1);
}

$out("Inserted: {$inserted}");
$out("Updated:  {$updated}");
$out("Skipped:  {$skipped}");
$out('Done.');
