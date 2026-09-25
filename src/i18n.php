<?php
declare(strict_types=1);

/**
 * Lightweight i18n. Language is picked from ?lang=xx (persisted to a cookie),
 * or from the ny_lang cookie / session, falling back to Czech. Translations
 * are a flat map — keys are English-ish identifiers, Czech values match the
 * historic wording used on the site (so nothing regresses if a key is missing).
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

function t(string $key, ...$args): string {
    static $map = null;
    if ($map === null) {
        $map = require __DIR__ . '/i18n_strings.php';
    }
    $lang = ny_lang();
    $val  = $map[$lang][$key] ?? $map['cs'][$key] ?? $key;
    if ($args) {
        return vsprintf($val, $args);
    }
    return $val;
}
