<?php
declare(strict_types=1);

require __DIR__ . '/src/db.php';

header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    ? 'https' : 'http';
$origin = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'namasteyoga.cz');

$today = date('Y-m-d');

$pages = [
    ['index.php',        '1.0', 'weekly'],
    ['rezervace.php',    '0.9', 'daily'],
    ['lekce.php',        '0.9', 'weekly'],
    ['individualni.php', '0.7', 'monthly'],
    ['masaze.php',       '0.7', 'monthly'],
    ['lektori.php',      '0.7', 'monthly'],
    ['galerie.php',      '0.6', 'monthly'],
    ['cenik.php',        '0.7', 'monthly'],
    ['kontakt.php',      '0.6', 'yearly'],
    ['podminky.php',     '0.3', 'yearly'],
    ['gdpr.php',         '0.3', 'yearly'],
    ['register.php',     '0.4', 'yearly'],
    ['login.php',        '0.3', 'yearly'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as [$path, $priority, $freq]) {
    $loc = $origin . '/' . $path;
    $loc = str_replace('/index.php', '/', $loc);
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($loc, ENT_QUOTES | ENT_XML1) . "</loc>\n";
    echo '    <lastmod>' . $today . "</lastmod>\n";
    echo '    <changefreq>' . $freq . "</changefreq>\n";
    echo '    <priority>' . $priority . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
