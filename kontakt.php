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
    $name  = trim((string)($_POST['name'] ?? ''));
    $from  = strtolower(trim((string)($_POST['email'] ?? '')));
    $msg   = trim((string)($_POST['message'] ?? ''));

    if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'kontakt')) {
        ny_flash_set('err', 'Ochrana proti robotům selhala, zkuste to prosím znovu.');
    } elseif ($name === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $msg === '') {
        ny_flash_set('err', 'Vyplňte prosím jméno, platný e-mail a zprávu.');
    } else {
        $subject = '=?UTF-8?B?' . base64_encode('Zpráva z webu – ' . $name) . '?=';
        $body    = "Od: $name <$from>\r\n\r\n" . $msg;
        $headers = 'From: ' . $email . "\r\n"
                 . 'Reply-To: ' . $from . "\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n";
        @mail($email, $subject, $body, $headers);
        ny_flash_set('ok', 'Děkujeme, zpráva byla odeslána.');
    }
    ny_redirect('kontakt.php');
}

ny_render_header('Kontakt', 'kontakt');
?>
<section class="section-title-block reveal">
    <div class="eyebrow">Studio Namasté</div>
    <h1 class="page-title">Kontakt</h1>
    <p class="page-lead">Najdete nás v centru Uherského Brodu. Ozvěte se – rádi vám pomůžeme vybrat lekci.</p>
</section>

<div class="cols cols-2">
    <section class="card reveal">
        <h2>Kde nás najdete</h2>
        <figure class="studio-outside">
            <img src="assets/studio-outside.jpg" alt="Provozovna Yoga studio Namasté – pohled z ulice" loading="lazy">
            <figcaption>Provozovna Yoga studio Namasté – pohled z ulice.</figcaption>
        </figure>
        <p>
            Namasté yoga studio se nachází přímo v centru a srdci Uherského Brodu.
            Přesněji se nacházíme mezi farou a oční optikou.
        </p>
        <p>
            Od hlavního vlakového i autobusového nádraží je studio jen 6 minut pěší chůzí
            směrem do centra (přímo za nosem nahoru, cca 350&nbsp;m). Další autobusová
            zastávka se nachází nad studiem na ulici Mariánské náměstí (asi 1 minutu
            pěší chůzí směrem dolů, cca 170&nbsp;m).
        </p>
        <p>
            Parkování je možné přímo před studiem. V odpoledních hodinách je parkování
            v klidném centru Uherského Brodu zdarma.
        </p>
        <p class="contact-line"><?= ny_icon('calendar', 16) ?> <?= e($opening) ?></p>
        <p class="contact-line"><?= ny_icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></p>
        <p class="contact-line"><?= ny_email_obf($email, ny_icon('mail', 16) . ' ') ?></p>
        <p class="contact-line">Adresa: <?= e($address) ?></p>
        <div class="map-embed">
            <iframe
                title="Mapa – <?= e($s['site_name']) ?>"
                src="https://www.openstreetmap.org/export/embed.html?bbox=<?= e((string)($mapLon - $mapDelta)) ?>%2C<?= e((string)($mapLat - $mapDelta / 2)) ?>%2C<?= e((string)($mapLon + $mapDelta)) ?>%2C<?= e((string)($mapLat + $mapDelta / 2)) ?>&amp;layer=mapnik&amp;marker=<?= e((string)$mapLat) ?>%2C<?= e((string)$mapLon) ?>"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </section>
    <section class="card muted reveal">
        <h2>Napište nám</h2>
        <p class="card-lead">Rezervaci na lekci prosím zadejte v <a href="rezervace.php">kalendáři</a>. Formulář slouží pro obecné dotazy.</p>
        <form method="post" novalidate data-recaptcha="kontakt">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <label>Jméno
                <input type="text" name="name" required>
            </label>
            <label>E-mail
                <input type="email" name="email" required>
            </label>
            <label>Zpráva
                <textarea name="message" rows="4" required></textarea>
            </label>
            <button class="btn btn-primary btn-form" type="submit">Odeslat</button>
        </form>
    </section>
</div>

<?php ny_render_footer();
