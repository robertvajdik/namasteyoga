<?php
declare(strict_types=1);

/**
 * Lightweight i18n. Language is picked from ?lang=xx (persisted to a cookie),
 * or from the ny_lang cookie / session, falling back to Czech. Translations
 * live in Java-style .properties files under /lang/{code}.properties, so
 * non-developers can edit them without touching PHP.
 */

function ny_langs(): array {
    return ['cs' => 'Čeština', 'en' => 'English'];
}

function ny_lang(): string {
    static $lang = null;
    if ($lang !== null) return $lang;

    ny_session_start();
    $q = strtolower((string)($_GET['lang'] ?? ''));
    if (isset(ny_langs()[$q])) {
        $_SESSION['lang'] = $q;
        @setcookie('ny_lang', $q, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $lang = $q;
        return $lang;
    }
    $s = $_SESSION['lang'] ?? $_COOKIE['ny_lang'] ?? 'cs';
    $lang = isset(ny_langs()[$s]) ? $s : 'cs';
    return $lang;
}

function ny_lang_url(string $lang): string {
    $qs = $_GET;
    $qs['lang'] = $lang;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    return $path . '?' . http_build_query($qs);
}

/**
 * Parse a Java-style .properties file into a flat key => value map.
 * Supports `#` and `!` comment lines, `key=value` and `key : value` pairs,
 * and the escapes \n \t \r \\ \= \: in values.
 */
function ny_load_properties(string $path): array {
    $out = [];
    if (!is_file($path)) return $out;
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return $out;
    foreach ($lines as $raw) {
        $line = ltrim($raw);
        if ($line === '' || $line[0] === '#' || $line[0] === '!') continue;
        $sep = strcspn($line, '=:');
        if ($sep === strlen($line)) continue;
        $key = rtrim(substr($line, 0, $sep));
        $val = ltrim(substr($line, $sep + 1));
        $val = strtr($val, [
            '\\n'  => "\n",
            '\\t'  => "\t",
            '\\r'  => "\r",
            '\\='  => '=',
            '\\:'  => ':',
            '\\\\' => '\\',
        ]);
        if ($key !== '') $out[$key] = $val;
    }
    return $out;
}

function t(string $key, ...$args): string {
    static $cache = [];
    $lang = ny_lang();
    if (!isset($cache[$lang])) {
        $cache[$lang] = ny_load_properties(__DIR__ . '/../lang/' . $lang . '.properties');
    }
    if (!isset($cache['cs'])) {
        $cache['cs'] = ny_load_properties(__DIR__ . '/../lang/cs.properties');
    }
    $val = $cache[$lang][$key] ?? $cache['cs'][$key] ?? $key;
    if ($args) {
        return vsprintf($val, $args);
    }
    return $val;
}
