<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ny_flash_set(string $type, string $msg): void {
    ny_session_start();
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function ny_flash_get(): array {
    ny_session_start();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function ny_redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

/**
 * Bot-resistant email rendering. Emits <a class="email-obf" data-e="{base64}"> whose href
 * gets rewritten to mailto: by JS on load. The no-JS fallback shows "user (at) domain (dot) tld".
 */
function ny_email_obf(?string $email, string $iconHtml = '', array $attrs = []): string {
    $email = trim((string)$email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
    [$user, $domain] = explode('@', $email, 2);
    $parts    = explode('.', $domain);
    $tld      = array_pop($parts);
    $mid      = implode('.', $parts);
    $fallback = e($user) . ' <span class="email-at" aria-hidden="true">(at)</span> ' . e($mid) . ' <span class="email-dot" aria-hidden="true">(dot)</span> ' . e($tld);
    $data      = base64_encode($email);
    $extraClass = '';
    $extra      = '';
    foreach ($attrs as $k => $v) {
        if ($k === 'class') { $extraClass = ' ' . (string)$v; continue; }
        $extra .= ' ' . e((string)$k) . '="' . e((string)$v) . '"';
    }
    return '<a href="#" class="email-obf' . e($extraClass) . '" data-e="' . e($data) . '" rel="nofollow"' . $extra . '>'
         . $iconHtml
         . '<span class="email-text">' . $fallback . '</span>'
         . '</a>';
}

/**
 * reCAPTCHA v3 – returns true when disabled (no keys), or verified with a good score.
 * Fails closed (returns false) if the API cannot be reached and keys ARE configured.
 */
function ny_recaptcha_verify(?string $token, string $action, float $minScore = 0.5): bool {
    $secret = trim((string)ny_setting('recaptcha_secret'));
    if ($secret === '') return true; // not configured — treat as disabled
    if (!$token) return false;

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query([
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]),
            'timeout'       => 5,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if ($raw === false) return false;
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['success'])) return false;
    if (isset($data['action']) && $data['action'] !== $action) return false;
    $score = isset($data['score']) ? (float)$data['score'] : 0.0;
    return $score >= $minScore;
}

/**
 * Lightweight offline "human check" — a math question stored in the session.
 * Runs independently of reCAPTCHA so the form is still protected on installs
 * where the reCAPTCHA keys are not filled in.
 */
function ny_captcha_generate(string $scope): array {
    ny_session_start();
    $a = random_int(2, 9);
    $b = random_int(2, 9);
    $_SESSION['captcha'][$scope] = $a + $b;
    return ['a' => $a, 'b' => $b];
}

function ny_captcha_verify(string $scope, string $answer): bool {
    ny_session_start();
    $expected = $_SESSION['captcha'][$scope] ?? null;
    unset($_SESSION['captcha'][$scope]);
    if ($expected === null || $answer === '') return false;
    return (int)$answer === (int)$expected;
}

function ny_week_start(?string $iso): DateTimeImmutable {
    $ref = $iso ? new DateTimeImmutable($iso) : new DateTimeImmutable('today');
    $dow = (int)$ref->format('N');
    return $ref->modify('-' . ($dow - 1) . ' days')->setTime(0, 0, 0);
}

/**
 * Minimal inline-SVG helper for Lucide-style icons (matches the design's stroke look).
 */
function ny_icon(string $name, int $size = 18): string {
    $paths = [
        'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.8a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.28-1.28a2 2 0 0 1 2.11-.45c.9.34 1.84.57 2.8.7A2 2 0 0 1 22 16.92z"/>',
        'mail'      => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'facebook'  => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>',
        'youtube'   => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'chevron-left'  => '<polyline points="15 18 9 12 15 6"/>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
        'chevron-down'  => '<polyline points="6 9 12 15 18 9"/>',
        'image'     => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'log-out'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'menu'      => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'close'     => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}

/**
 * Emit HTTP security headers. Must be called before any output.
 * CSP is tuned for our own inline scripts + Google Fonts + optional GA + OSM map iframe.
 */
function ny_send_security_headers(): void {
    if (PHP_SAPI === 'cli' || headers_sent()) return;

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "img-src 'self' data:",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
        "font-src 'self' https://fonts.gstatic.com",
        "script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://www.google-analytics.com https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net",
        "connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com https://www.google.com",
        "frame-src 'self' https://www.openstreetmap.org https://www.google.com",
        "object-src 'none'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

function ny_render_header(string $title, string $active = '', array $opts = []): void {
    ny_send_security_headers();
    $user       = ny_current_user();
    $bare       = !empty($opts['bare']);
    $overlayHdr = !empty($opts['overlay']);
    $desc       = $opts['description'] ?? 'Studio Namasté Yoga v Uherském Brodě – jóga, pilates, masáže a individuální lekce pro začátečníky i pokročilé.';

    $s        = ny_settings_all();
    $siteName = $s['site_name'] ?: 'Studio Namasté';
    $phone    = $s['phone'];
    $email    = $s['email'];
    $address  = $s['address'];
    $fbUrl    = $s['facebook_url'];
    $igUrl    = $s['instagram_url'];
    $ytUrl    = $s['youtube_url'];
    $gaId     = trim($s['ga_id']);
    $mapLat   = $s['map_lat'];
    $mapLon   = $s['map_lon'];
    $lang = ny_lang();
    ?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($siteName) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#5F9187">

<meta property="og:title"       content="<?= e($title) ?> · <?= e($siteName) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type"        content="website">
<meta property="og:site_name"   content="<?= e($siteName) ?>">
<meta property="og:image"       content="assets/logoCream.png">
<meta name="twitter:card"       content="summary_large_image">

<link rel="icon" href="assets/logoCream.png" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "HealthAndBeautyBusiness",
  "name": "<?= e($siteName) ?>",
  "telephone": "<?= e($phone) ?>",
  "email": "<?= e($email) ?>",
  "address": "<?= e($address) ?>",
  "geo": { "@type": "GeoCoordinates", "latitude": "<?= e($mapLat) ?>", "longitude": "<?= e($mapLon) ?>" },
  "openingHours": "Mo-Su"
}
</script>

<?php if ($gaId !== '' && preg_match('/^G-[A-Z0-9]+$/i', $gaId)): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?= json_encode($gaId) ?>, { anonymize_ip: true });
</script>
<?php endif; ?>
<?php $rcSite = trim((string)($s['recaptcha_site'] ?? '')); if ($rcSite !== ''): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= e($rcSite) ?>" async defer></script>
<script>
(function () {
    var siteKey = <?= json_encode($rcSite) ?>;
    function inject(form) {
        if (form.dataset.recaptchaBound) return;
        form.dataset.recaptchaBound = '1';
        var action = form.getAttribute('data-recaptcha');
        form.addEventListener('submit', function (e) {
            if (form.dataset.recaptchaReady === '1') return;
            e.preventDefault();
            if (typeof grecaptcha === 'undefined') { form.dataset.recaptchaReady = '1'; form.submit(); return; }
            grecaptcha.ready(function () {
                grecaptcha.execute(siteKey, { action: action }).then(function (token) {
                    var input = form.querySelector('input[name="g-recaptcha-response"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'g-recaptcha-response';
                        form.appendChild(input);
                    }
                    input.value = token;
                    form.dataset.recaptchaReady = '1';
                    form.submit();
                });
            });
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-recaptcha]').forEach(inject);
    });
})();
</script>
<?php endif; ?>
</head>
<body<?= $overlayHdr ? ' class="has-overlay-header"' : '' ?>>
<header class="site-header<?= $overlayHdr ? ' overlay' : '' ?>">
    <div class="topbar">
        <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= ny_icon('phone', 14) ?> <?= e($phone) ?></a>
        <?= ny_email_obf($email, ny_icon('mail', 14) . ' ', ['class' => 'topbar-mail']) ?>
        <span class="spacer"></span>
        <?php if ($fbUrl): ?><a href="<?= e($fbUrl) ?>" aria-label="Facebook" target="_blank" rel="noopener"><?= ny_icon('facebook', 15) ?></a><?php endif; ?>
        <?php if ($igUrl): ?><a href="<?= e($igUrl) ?>" aria-label="Instagram" target="_blank" rel="noopener"><?= ny_icon('instagram', 15) ?></a><?php endif; ?>
        <?php if ($ytUrl): ?><a href="<?= e($ytUrl) ?>" aria-label="YouTube" target="_blank" rel="noopener"><?= ny_icon('youtube', 15) ?></a><?php endif; ?>
        <div class="lang-switch" role="group" aria-label="Language">
            <?php foreach (ny_langs() as $lc => $lLabel): ?>
                <a href="<?= e(ny_lang_url($lc)) ?>" class="<?= $lang === $lc ? 'is-active' : '' ?>" hreflang="<?= e($lc) ?>"><?= e(strtoupper($lc)) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="header-user">
            <?php if ($user): ?>
                <a class="header-user-link" href="my.php" title="Moje rezervace">
                    <?php if (!empty($user['avatar'])): ?>
                        <span class="user-avatar"><img src="assets/avatars/<?= e(rawurlencode($user['avatar'])) ?>" alt=""></span>
                    <?php else: ?>
                        <span class="user-avatar"><?= e(mb_strtoupper(mb_substr((string)$user['display_name'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <span class="header-user-name"><?= e($user['display_name']) ?><?php if ((int)$user['is_guest'] === 1): ?> <em><?= e(t('user.guest')) ?></em><?php endif; ?></span>
                </a>
                <?php if (ny_is_admin($user)): ?>
                    <a class="header-user-icon" href="admin/index.php" title="<?= e(t('user.admin')) ?>"><?= ny_icon('settings', 16) ?></a>
                <?php endif; ?>
                <a class="header-user-icon" href="logout.php" title="<?= e(t('user.logout')) ?>"><?= ny_icon('log-out', 16) ?></a>
            <?php else: ?>
                <a class="header-user-icon" href="login.php" title="<?= e(t('user.login')) ?>"><?= ny_icon('user', 15) ?> <span><?= e(t('user.login')) ?></span></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="band">
        <a class="logo" href="index.php"><img src="assets/logoCream.png" alt="<?= e($siteName) ?>"></a>
        <input type="checkbox" id="nav-toggle" class="nav-toggle-input" aria-hidden="true" autocomplete="off">
        <label for="nav-toggle" class="nav-toggle" aria-label="Menu" role="button" tabindex="0">
            <span class="nav-toggle-open"><?= ny_icon('menu', 22) ?></span>
            <span class="nav-toggle-close"><?= ny_icon('close', 22) ?></span>
        </label>
        <nav id="site-nav">
            <a href="index.php"        class="<?= $active === 'home'       ? 'is-active' : '' ?>"><?= e(t('nav.home')) ?></a>
            <a href="rezervace.php"    class="<?= $active === 'schedule'   ? 'is-active' : '' ?>"><?= e(t('nav.schedule')) ?></a>
            <a href="lekce.php"        class="<?= $active === 'lekce'      ? 'is-active' : '' ?>"><?= e(t('nav.lekce')) ?></a>
            <a href="individualni.php" class="<?= $active === 'individ'    ? 'is-active' : '' ?>"><?= e(t('nav.individ')) ?></a>
            <a href="masaze.php"       class="<?= $active === 'masaze'     ? 'is-active' : '' ?>"><?= e(t('nav.masaze')) ?></a>
            <a href="lektori.php"      class="<?= $active === 'lektori'    ? 'is-active' : '' ?>"><?= e(t('nav.lektori')) ?></a>
            <a href="galerie.php"      class="<?= $active === 'galerie'    ? 'is-active' : '' ?>"><?= e(t('nav.galerie')) ?></a>
            <a href="cenik.php"        class="<?= $active === 'cenik'      ? 'is-active' : '' ?>"><?= e(t('nav.cenik')) ?></a>
            <a href="kontakt.php"      class="<?= $active === 'kontakt'    ? 'is-active' : '' ?>"><?= e(t('nav.kontakt')) ?></a>
            <?php if ($user): ?>
                <a href="my.php" class="<?= $active === 'my' ? 'is-active' : '' ?>"><?= e(t('nav.my')) ?></a>
            <?php else: ?>
                <a href="register.php" class="<?= $active === 'register' ? 'is-active' : '' ?>"><?= e(t('nav.register')) ?></a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
<?php if (!$bare): ?>
<div class="container page">
<?php foreach (ny_flash_get() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php endif;
}

function ny_render_footer(bool $bare = false): void {
    $s        = ny_settings_all();
    $siteName = $s['site_name'] ?: 'Studio Namasté';
    ?>
<?php if (!$bare): ?>
</div>
<?php endif; ?>
</main>
<a href="rezervace.php" class="mobile-cta"><?= ny_icon('calendar', 16) ?> <?= e(t('mobile.reserve')) ?></a>
<footer class="site-footer">
    <div class="inner">
        <div class="foot-brand">
            <img src="assets/logoCream.png" alt="<?= e($siteName) ?>">
            <p class="foot-tag"><?= e(t('footer.tag')) ?></p>
        </div>
        <div class="foot-col">
            <h4><?= e(t('footer.studio')) ?></h4>
            <a href="lekce.php"><?= e(t('nav.lekce')) ?></a>
            <a href="individualni.php"><?= e(t('nav.individ')) ?></a>
            <a href="masaze.php"><?= e(t('nav.masaze')) ?></a>
            <a href="lektori.php"><?= e(t('nav.lektori')) ?></a>
            <a href="galerie.php"><?= e(t('nav.galerie')) ?></a>
        </div>
        <div class="foot-col">
            <h4><?= e(t('footer.info')) ?></h4>
            <a href="cenik.php"><?= e(t('nav.cenik')) ?></a>
            <a href="rezervace.php"><?= e(t('nav.schedule')) ?></a>
            <a href="kontakt.php"><?= e(t('nav.kontakt')) ?></a>
            <a href="podminky.php"><?= e(t('footer.terms')) ?></a>
            <a href="gdpr.php"><?= e(t('footer.gdpr')) ?></a>
        </div>
        <div class="foot-col">
            <h4><?= e(t('footer.contact')) ?></h4>
            <a href="tel:<?= e(preg_replace('/\s+/', '', $s['phone'])) ?>"><?= ny_icon('phone', 14) ?> <?= e($s['phone']) ?></a>
            <?= ny_email_obf($s['email'], ny_icon('mail', 14) . ' ') ?>
            <div class="social">
                <?php if ($s['instagram_url']): ?><a href="<?= e($s['instagram_url']) ?>" aria-label="Instagram" target="_blank" rel="noopener"><?= ny_icon('instagram', 22) ?></a><?php endif; ?>
                <?php if ($s['facebook_url']): ?><a href="<?= e($s['facebook_url']) ?>" aria-label="Facebook" target="_blank" rel="noopener"><?= ny_icon('facebook', 22) ?></a><?php endif; ?>
                <?php if ($s['youtube_url']): ?><a href="<?= e($s['youtube_url']) ?>" aria-label="YouTube" target="_blank" rel="noopener"><?= ny_icon('youtube', 22) ?></a><?php endif; ?>
            </div>
        </div>
        <div class="foot-col foot-col--newsletter">
            <h4><?= e(t('footer.newsletter')) ?></h4>
            <p class="foot-tag"><?= e(t('footer.newsletter.desc')) ?></p>
            <form class="newsletter-form" method="post" action="newsletter.php" data-recaptcha="newsletter">
                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                <input type="hidden" name="source" value="footer">
                <label class="visually-hidden" for="nl-email">E-mail</label>
                <input id="nl-email" type="email" name="email" placeholder="<?= e(t('footer.newsletter.email')) ?>" required>
                <button class="btn btn-primary btn-sm" type="submit"><?= e(t('footer.newsletter.submit')) ?></button>
            </form>
            <small class="foot-tag"><?= e(t('footer.newsletter.small')) ?></small>
        </div>
    </div>
    <div class="foot-legal">
        <div><?= e(t('footer.legal', date('Y'))) ?></div>
        <div class="foot-ico"><?= e(t('footer.ico')) ?></div>
    </div>
</footer>

<div id="cookie-banner" class="cookie-banner" hidden>
    <div class="cookie-inner">
        <p><?= e(ny_setting('ga_id') !== '' ? t('cookie.text.full') : t('cookie.text.min')) ?></p>
        <button type="button" class="btn btn-primary btn-sm" id="cookie-accept"><?= e(t('cookie.accept')) ?></button>
    </div>
</div>

<script>
(function () {
    // Sticky header shrink on scroll.
    var header = document.querySelector('.site-header');
    var scrollHandler = function () {
        if (!header) return;
        header.classList.toggle('is-scrolled', window.scrollY > 40);
    };
    window.addEventListener('scroll', scrollHandler, { passive: true });
    scrollHandler();

    // Scroll reveal (opt-in via .reveal); skipped if user prefers reduced motion.
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduce && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) {
                    en.target.classList.add('is-in');
                    io.unobserve(en.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('.reveal').forEach(function (el) { io.observe(el); });
    } else {
        document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('is-in'); });
    }

    // Deobfuscate email links – runs client-side so plaintext addresses never appear in HTML.
    document.querySelectorAll('a.email-obf[data-e]').forEach(function (a) {
        try {
            var addr = atob(a.getAttribute('data-e'));
            a.setAttribute('href', 'mailto:' + addr);
            var t = a.querySelector('.email-text');
            if (t) t.textContent = addr;
        } catch (e) {}
    });

    // Cookie banner (localStorage-based).
    var banner = document.getElementById('cookie-banner');
    var accept = document.getElementById('cookie-accept');
    try {
        if (banner && !localStorage.getItem('ny_cookie_ok')) {
            banner.hidden = false;
        }
        if (accept) accept.addEventListener('click', function () {
            try { localStorage.setItem('ny_cookie_ok', '1'); } catch (e) {}
            if (banner) banner.hidden = true;
        });
    } catch (e) { /* private mode: just hide */ if (banner) banner.hidden = true; }
})();
</script>
</body>
</html><?php
}
