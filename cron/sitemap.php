<?php
declare(strict_types=1);

/**
 * Regenerates /sitemap.xml so search engines get the cached, up-to-date file.
 *
 * Trigger from cron (once a day is plenty):
 *
 *   CLI:  php /path/to/cron/sitemap.php
 *   HTTP: curl "https://example.tld/cron/sitemap.php?key=CRON_KEY"
 *
 * HTTP calls require the `cron_key` setting (Admin → Nastavení → Připomínky)
 * to be filled in and matched exactly. When called via HTTP, `site_url` from
 * settings is used first, so the generated URLs stay canonical.
 */

require __DIR__ . '/../src/sitemap.php';

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

try {
    $path = ny_sitemap_write();
    echo 'Sitemap written: ' . $path . ' (' . count(ny_sitemap_urls()) . " URLs)\n";
} catch (Throwable $e) {
    if (!$isCli) http_response_code(500);
    echo 'Sitemap generation failed: ' . $e->getMessage() . "\n";
    exit(1);
}
