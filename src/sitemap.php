<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Path of the on-disk cached sitemap file. sitemap.php serves this when
 * it exists; the cron regenerates it.
 */
function ny_sitemap_path(): string {
    return dirname(__DIR__) . '/sitemap.xml';
}

/**
 * Detect the public origin. Prefers the persisted `site_url` setting;
 * falls back to the request host, then to a sensible default.
 */
function ny_sitemap_origin(): string {
    $configured = trim((string)ny_setting('site_url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'namasteyoga.cz';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . $host;
}

/**
 * The list of public, indexable URLs. Kept in one place so both the admin
 * "regenerate" button and the cron produce identical output.
 *
 * @return array<int, array{path:string, priority:string, changefreq:string}>
 */
function ny_sitemap_urls(): array {
    return [
        ['index.php',        '1.0', 'weekly'],
        ['rezervace.php',    '0.9', 'daily'],
        ['lekce.php',        '0.9', 'weekly'],
        ['individualni.php', '0.7', 'monthly'],
        ['masaze.php',       '0.7', 'monthly'],
        ['puppyvibe.php',    '0.7', 'monthly'],
        ['lektori.php',      '0.7', 'monthly'],
        ['galerie.php',      '0.6', 'monthly'],
        ['cenik.php',        '0.7', 'monthly'],
        ['poukaz.php',       '0.7', 'monthly'],
        ['kontakt.php',      '0.6', 'yearly'],
        ['newsletter.php',   '0.4', 'yearly'],
        ['podminky.php',     '0.3', 'yearly'],
        ['gdpr.php',         '0.3', 'yearly'],
        ['register.php',     '0.4', 'yearly'],
        ['login.php',        '0.3', 'yearly'],
    ];
}

/**
 * Build the sitemap XML as a string.
 */
function ny_sitemap_build(?string $origin = null): string {
    $origin = $origin ?? ny_sitemap_origin();
    $today  = date('Y-m-d');
    $langs  = function_exists('ny_langs') ? array_keys(ny_langs()) : ['cs'];

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
          . ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

    foreach (ny_sitemap_urls() as [$path, $priority, $freq]) {
        $loc = $origin . '/' . $path;
        $loc = str_replace('/index.php', '/', $loc);
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_QUOTES | ENT_XML1) . "</loc>\n";
        $xml .= '    <lastmod>' . $today . "</lastmod>\n";
        $xml .= '    <changefreq>' . $freq . "</changefreq>\n";
        $xml .= '    <priority>' . $priority . "</priority>\n";
        if (count($langs) > 1) {
            foreach ($langs as $lc) {
                $altUrl = $loc . (str_contains($loc, '?') ? '&' : '?') . 'lang=' . $lc;
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lc, ENT_QUOTES | ENT_XML1)
                      . '" href="' . htmlspecialchars($altUrl, ENT_QUOTES | ENT_XML1) . '"/>' . "\n";
            }
        }
        $xml .= "  </url>\n";
    }
    $xml .= '</urlset>' . "\n";
    return $xml;
}

/**
 * Write the sitemap to disk atomically. Returns the destination path on success.
 * Throws RuntimeException on any I/O failure so callers can surface it in the UI.
 */
function ny_sitemap_write(?string $origin = null): string {
    $dest = ny_sitemap_path();
    $tmp  = $dest . '.tmp';
    $xml  = ny_sitemap_build($origin);
    if (@file_put_contents($tmp, $xml, LOCK_EX) === false) {
        throw new RuntimeException('Nelze zapsat do ' . $tmp . ' (zkontrolujte oprávnění).');
    }
    if (!@rename($tmp, $dest)) {
        @unlink($tmp);
        throw new RuntimeException('Nelze přepsat ' . $dest . ' (zkontrolujte oprávnění).');
    }
    return $dest;
}

/**
 * @return array{exists:bool, path:string, url_count:int, size:int, mtime:?int}
 */
function ny_sitemap_status(): array {
    $path = ny_sitemap_path();
    if (!is_file($path)) {
        return ['exists' => false, 'path' => $path, 'url_count' => 0, 'size' => 0, 'mtime' => null];
    }
    return [
        'exists'    => true,
        'path'      => $path,
        'url_count' => count(ny_sitemap_urls()),
        'size'      => (int)@filesize($path),
        'mtime'     => (int)@filemtime($path),
    ];
}
