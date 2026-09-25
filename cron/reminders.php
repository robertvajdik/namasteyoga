<?php
declare(strict_types=1);

/**
 * Sends pre-class reminder e-mails.
 *
 * Trigger daily (or hourly) from cron:
 *
 *   CLI:  php /path/to/cron/reminders.php
 *   HTTP: curl "https://example.tld/cron/reminders.php?key=CRON_KEY"
 *
 * The lead-time is set in Admin → Nastavení → Připomínky lekcí. HTTP calls
 * require the `cron_key` setting to be filled in and matched exactly.
 */

require __DIR__ . '/../src/db.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $expected = trim((string)ny_setting('cron_key', ''));
    $given    = (string)($_GET['key'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$sent = ny_reminders_send_due();
echo 'Sent reminders: ' . $sent . "\n";
