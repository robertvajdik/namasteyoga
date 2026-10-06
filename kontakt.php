<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$s        = ny_settings_all();
$phone    = $s['phone'];
$email    = $s['email'];
$address  = $s['address'];
$opening  = $s['opening'];
$mapLat   = (float)($s['map_lat'] ?: 49.0255);
$mapLon   = (float)($s['map_lon'] ?: 17.6512);
$mapDelta = 0.008;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $name    = trim((string)($_POST['name'] ?? ''));
    $from    = strtolower(trim((string)($_POST['email'] ?? '')));
    $prefDate = trim((string)($_POST['pref_date'] ?? ''));
    $prefTime = trim((string)($_POST['pref_time'] ?? ''));
    $msg     = trim((string)($_POST['message'] ?? ''));
    $captcha = trim((string)($_POST['captcha'] ?? ''));
    $hp      = trim((string)($_POST['website'] ?? '')); // honeypot — bots fill this

    if ($hp !== '') {
        // Silently drop bot submissions but still confirm to the user so we
        // don't leak the honeypot's existence.
        ny_flash_set('ok', t('kontakt.flash.ok'));
        ny_redirect('kontakt.php');
    }

    if (!ny_captcha_verify('kontakt', $captcha)) {
        ny_flash_set('err', t('kontakt.flash.err.captcha'));
    } elseif (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'kontakt')) {
        ny_flash_set('err', t('kontakt.flash.err.recaptcha'));
    } elseif ($name === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $msg === '') {
        ny_flash_set('err', t('kontakt.flash.err.fields'));
    } else {
        $subject = '=?UTF-8?B?' . base64_encode('Zpráva z webu – ' . $name) . '?=';
        $prefLine = '';
        $calLine  = '';
        if ($prefDate !== '' || $prefTime !== '') {
            $parts = [];
            if ($prefDate !== '') { $parts[] = $prefDate; }
            if ($prefTime !== '') { $parts[] = $prefTime; }
            $prefLine = "Preferovaný termín: " . implode(' ', $parts) . "\r\n\r\n";
        }
        if ($prefDate !== '' && $prefTime !== ''
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefDate)
            && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $prefTime)
        ) {
            $startHms = strlen($prefTime) === 5 ? $prefTime . ':00' : $prefTime;
            $endHms   = date('H:i:s', strtotime($startHms) + 3600);
            $gcalUrl  = ny_gcal_url(
                $prefDate, $startHms, $endHms,
                'Zájem o lekci – ' . $name,
                "Od: $name <$from>\n\n" . $msg,
                $address
            );
            if ($gcalUrl !== '') {
                $calLine = "Přidat do kalendáře: $gcalUrl\r\n\r\n";
            }
        }
        $body    = "Od: $name <$from>\r\n\r\n" . $prefLine . $calLine . $msg;
        $headers = 'From: ' . $email . "\r\n"
                 . 'Reply-To: ' . $from . "\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";
        @mail($email, $subject, $body, $headers);
        ny_flash_set('ok', t('kontakt.flash.ok'));
    }
    ny_redirect('kontakt.php');
}

$captcha = ny_captcha_generate('kontakt');

$prefillMessage = '';
$prefillMassage = trim((string)($_GET['masaz'] ?? ''));
if ($prefillMassage !== '') {
    $prefillLen   = trim((string)($_GET['delka'] ?? ''));
    $prefillPrice = trim((string)($_GET['cena'] ?? ''));
    $details = [];
    if ($prefillLen   !== '') { $details[] = $prefillLen; }
    if ($prefillPrice !== '') { $details[] = $prefillPrice; }
    $suffix = $details ? ' (' . implode(', ', $details) . ')' : '';
    $prefillMessage = sprintf(
        "Dobrý den, mám zájem o masáž „%s\"%s. Prosím o navržení termínu. Děkuji.",
        $prefillMassage,
        $suffix
    );
}

ny_render_header(t('kontakt.title'), 'kontakt', ['description' => t('kontakt.meta.description')]);
?>
<section class="page-hero-media page-hero-media--bg reveal" style="background-image: url('assets/banners/kontakt_namasteyoga.cz.jpg');" role="img" aria-label="<?= e(t('kontakt.hero.title')) ?>">
    <div class="page-hero-media-body">
        <div class="eyebrow"><?= e(t('kontakt.hero.eyebrow')) ?></div>
        <h1 class="page-title"><?= e(t('kontakt.hero.title')) ?></h1>
        <p class="page-lead page-lead--start"><?= e(t('kontakt.hero.lead')) ?></p>
    </div>
</section>

<div class="cols cols-2">
    <section class="card reveal">
        <h2><?= e(t('kontakt.where.title')) ?></h2>
        <figure class="studio-outside">
            <img src="assets/studio-outside.jpg" alt="<?= e(t('kontakt.where.image.alt')) ?>" loading="lazy">
            <figcaption><?= e(t('kontakt.where.image.caption')) ?></figcaption>
        </figure>
        <p><?= e(t('kontakt.where.p1')) ?></p>
        <p><?= t('kontakt.where.p2') ?></p>
        <p><?= e(t('kontakt.where.p3')) ?></p>
        <p class="contact-line"><?= ny_icon('calendar', 16) ?> <?= e($opening) ?></p>
        <p class="contact-line"><?= ny_phone_obf($phone, ny_icon('phone', 16) . ' ') ?></p>
        <p class="contact-line"><?= ny_email_obf($email, ny_icon('mail', 16) . ' ') ?></p>
        <p class="contact-line"><?= e(t('kontakt.where.address')) ?> <?= e($address) ?></p>
        <div class="map-embed">
            <iframe
                title="<?= e(t('kontakt.where.map.title')) ?> – <?= e($s['site_name']) ?>"
                src="https://www.openstreetmap.org/export/embed.html?bbox=<?= e((string)($mapLon - $mapDelta)) ?>%2C<?= e((string)($mapLat - $mapDelta / 2)) ?>%2C<?= e((string)($mapLon + $mapDelta)) ?>%2C<?= e((string)($mapLat + $mapDelta / 2)) ?>&amp;layer=mapnik&amp;marker=<?= e((string)$mapLat) ?>%2C<?= e((string)$mapLon) ?>"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </section>
    <section id="kontakt-form" class="card muted reveal">
        <h2><?= e(t('kontakt.form.title')) ?></h2>
        <p class="card-lead"><?= t('kontakt.form.lead') ?></p>
        <form method="post" novalidate data-recaptcha="kontakt">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <label><?= e(t('kontakt.form.name')) ?>
                <input type="text" name="name" required>
            </label>
            <label><?= e(t('kontakt.form.email')) ?>
                <input type="email" name="email" required>
            </label>
            <div class="form-row">
                <label><?= e(t('kontakt.form.date')) ?>
                    <input type="date" name="pref_date" min="<?= e(date('Y-m-d')) ?>">
                </label>
                <label><?= e(t('kontakt.form.time')) ?>
                    <input type="time" name="pref_time" step="900">
                </label>
            </div>
            <label><?= e(t('kontakt.form.message')) ?>
                <textarea name="message" rows="4" required><?= e($prefillMessage) ?></textarea>
            </label>
            <div class="hp-field" aria-hidden="true">
                <label><?= e(t('kontakt.form.hp')) ?>
                    <input type="text" name="website" tabindex="-1" autocomplete="off">
                </label>
            </div>
            <label class="captcha-field"><?= e(t('kontakt.form.captcha')) ?> <?= (int)$captcha['a'] ?> + <?= (int)$captcha['b'] ?>?
                <input type="text" name="captcha" inputmode="numeric" pattern="[0-9]+" autocomplete="off" required>
            </label>
            <button class="btn btn-primary btn-form" type="submit"><?= e(t('kontakt.form.submit')) ?></button>
        </form>
    </section>
</div>

<?php ny_render_footer();
