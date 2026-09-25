<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$fields = [
    'branding' => [
        'title' => 'Značka',
        'items' => [
            'site_name' => ['label' => 'Název studia', 'type' => 'text'],
        ],
    ],
    'contacts' => [
        'title' => 'Kontakty',
        'items' => [
            'phone'   => ['label' => 'Telefon',              'type' => 'text',     'hint' => 'Např. +420 775 607 710'],
            'email'   => ['label' => 'E-mail',               'type' => 'email'],
            'address' => ['label' => 'Adresa (jeden řádek)', 'type' => 'text'],
            'opening' => ['label' => 'Otvírací doba (text)', 'type' => 'text'],
        ],
    ],
    'social' => [
        'title' => 'Sociální sítě',
        'items' => [
            'facebook_url'  => ['label' => 'Facebook URL',  'type' => 'url'],
            'instagram_url' => ['label' => 'Instagram URL', 'type' => 'url'],
            'youtube_url'   => ['label' => 'YouTube URL',   'type' => 'url'],
        ],
    ],
    'map' => [
        'title' => 'Mapa',
        'items' => [
            'map_lat'  => ['label' => 'Zeměpisná šířka (lat)',  'type' => 'text', 'hint' => 'Např. 49.0255'],
            'map_lon'  => ['label' => 'Zeměpisná délka (lon)',  'type' => 'text', 'hint' => 'Např. 17.6512'],
            'map_zoom' => ['label' => 'Zoom (10–19)',            'type' => 'number'],
        ],
    ],
    'analytics' => [
        'title' => 'Analytika',
        'items' => [
            'ga_id' => ['label' => 'Google Analytics – Measurement ID', 'type' => 'text', 'hint' => 'Formát G-XXXXXXX. Nechte prázdné pro vypnutí.'],
        ],
    ],
    'recaptcha' => [
        'title' => 'reCAPTCHA v3',
        'items' => [
            'recaptcha_site'   => ['label' => 'Site key',   'type' => 'text', 'hint' => 'Vygenerujte na https://www.google.com/recaptcha/admin (v3).'],
            'recaptcha_secret' => ['label' => 'Secret key', 'type' => 'text', 'hint' => 'Ověření probíhá serverově. Nechte prázdné pro vypnutí.'],
        ],
    ],
];

$flat = [];
foreach ($fields as $g) foreach ($g['items'] as $k => $v) $flat[$k] = $v;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $values = [];
    foreach ($flat as $key => $meta) {
        $val = trim((string)($_POST[$key] ?? ''));
        if ($meta['type'] === 'email' && $val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            ny_flash_set('err', 'Neplatný e-mail: ' . $meta['label']);
            ny_redirect('settings.php');
        }
        if ($meta['type'] === 'url' && $val !== '' && !filter_var($val, FILTER_VALIDATE_URL)) {
            ny_flash_set('err', 'Neplatný odkaz: ' . $meta['label']);
            ny_redirect('settings.php');
        }
        if ($key === 'ga_id' && $val !== '' && !preg_match('/^G-[A-Z0-9]+$/i', $val)) {
            ny_flash_set('err', 'GA Measurement ID musí být ve tvaru G-XXXXXXX.');
            ny_redirect('settings.php');
        }
        $values[$key] = $val;
    }
    ny_settings_save($values);
    ny_flash_set('ok', 'Nastavení uloženo.');
    ny_redirect('settings.php');
}

$current = ny_settings_all(true);
$mapLat  = (float)($current['map_lat'] ?: 49.0255);
$mapLon  = (float)($current['map_lon'] ?: 17.6512);
$mapDelta = 0.008;

ny_admin_render_header('Nastavení webu', 'settings');
?>
<form method="post" class="admin-form">
    <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">

    <?php foreach ($fields as $g): ?>
        <div class="admin-card">
            <h2><?= e($g['title']) ?></h2>
            <div class="admin-form-row">
                <?php foreach ($g['items'] as $key => $meta): ?>
                    <label>
                        <?= e($meta['label']) ?>
                        <input
                            type="<?= e($meta['type']) ?>"
                            name="<?= e($key) ?>"
                            value="<?= e((string)($current[$key] ?? '')) ?>"
                            <?= $meta['type'] === 'number' ? 'step="1" min="1"' : '' ?>>
                        <?php if (!empty($meta['hint'])): ?>
                            <small class="hint hint-inline">
                                <?= e($meta['hint']) ?>
                            </small>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if ($g['title'] === 'Mapa'): ?>
                <div class="map-embed map-embed--spaced">
                    <iframe
                        title="Náhled mapy"
                        src="https://www.openstreetmap.org/export/embed.html?bbox=<?= e((string)($mapLon - $mapDelta)) ?>%2C<?= e((string)($mapLat - $mapDelta / 2)) ?>%2C<?= e((string)($mapLon + $mapDelta)) ?>%2C<?= e((string)($mapLat + $mapDelta / 2)) ?>&amp;layer=mapnik&amp;marker=<?= e((string)$mapLat) ?>%2C<?= e((string)$mapLon) ?>"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <p class="hint hint-top">
                    Souřadnice najdete např. na <a href="https://www.openstreetmap.org/" target="_blank" rel="noopener">openstreetmap.org</a>
                    – klikněte pravým tlačítkem na místo → „Show address here" → čísla jsou ve tvaru <em>lat, lon</em>.
                </p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <div class="row row-end">
        <button class="btn btn-primary" type="submit">Uložit nastavení</button>
    </div>
</form>
<?php ny_admin_render_footer();
