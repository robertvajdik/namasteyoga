<?php
declare(strict_types=1);

require __DIR__ . '/src/sitemap.php';

header('Content-Type: application/xml; charset=utf-8');

$cached = ny_sitemap_path();
if (is_file($cached)) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int)filemtime($cached)) . ' GMT');
    readfile($cached);
    return;
}

echo ny_sitemap_build();
