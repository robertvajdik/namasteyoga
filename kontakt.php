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
        <p class="contact-line"><?= ny_icon('calendar', 16) ?> <?= e($opening) ?></p>
        <p class="contact-line"><?= ny_icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= e($phone) ?></a></p>
        <p class="contact-line"><?= ny_icon('mail', 16) ?> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></p>
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
        <form method="post" novalidate>
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
