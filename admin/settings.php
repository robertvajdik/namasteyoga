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
    'mail' => [
        'title' => 'Odesílání e-mailů',
        'items' => [
            'mail_from'  => ['label' => 'Odesílatel (From:)',       'type' => 'email', 'hint' => 'Záložní hodnota. Přednost má nastavení v config.php (mail.from).'],
            'mail_admin' => ['label' => 'Adresa admina pro notifikace', 'type' => 'email', 'hint' => 'Záložní hodnota. Přednost má nastavení v config.php (mail.admin_notify). Sem chodí upozornění o nové registraci apod.'],
        ],
    ],
    'reminders' => [
        'title' => 'Připomínky lekcí',
        'items' => [
            'reminder_hours' => [
                'label' => 'Odeslat připomínku (hodin před lekcí)',
                'type'  => 'number',
                'min'   => 0,
                'hint'  => 'Např. 24 znamená den předem. Zadejte 0 pro vypnutí připomínek. Odesílá se pomocí cron úlohy volající cron/reminders.php.',
            ],
            'cron_key' => [
                'label' => 'Klíč pro cron (URL parametr ?key=)',
                'type'  => 'text',
                'hint'  => 'Vygenerujte si dlouhý náhodný řetězec. Cron pak volejte např. https://…/cron/reminders.php?key=váš-klíč. Nechte prázdné pro spouštění pouze z CLI.',
            ],
        ],
    ],
];

$flat = [];
foreach ($fields as $g) foreach ($g['items'] as $k => $v) $flat[$k] = $v;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'test_mail') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $to = trim((string)($_POST['test_mail_to'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        ny_flash_set('err', 'Zadejte platnou e-mailovou adresu pro test.');
    } else {
        $mailCfg  = ny_config()['mail'] ?? [];
        $siteName = ny_setting('site_name', 'Studio Namasté');
        $from     = trim((string)($mailCfg['from'] ?? '')) ?: (ny_setting('mail_from') ?: ny_setting('email'));
        $smtpHost = trim((string)($mailCfg['smtp_host'] ?? ''));
        $transport = $smtpHost !== ''
            ? 'SMTP (' . $smtpHost . ':' . (int)($mailCfg['smtp_port'] ?? 587) . ', ' . strtoupper((string)($mailCfg['smtp_secure'] ?? 'tls')) . ')'
            : 'PHP mail()';

        $body = "Toto je testovací e-mail ze systému " . $siteName . ".\n\n"
              . "Pokud jste jej dostali, konfigurace odesílání funguje správně.\n\n"
              . 'Odesláno:            ' . date('j. n. Y H:i') . "\n"
              . 'Odesílatel (From):   ' . $from . "\n"
              . 'Přenos:              ' . $transport . "\n"
              . 'Server:              ' . ($_SERVER['SERVER_NAME'] ?? gethostname() ?: 'n/a') . "\n";

        if (ny_mail($to, 'Testovací e-mail – ' . $siteName, $body)) {
            ny_flash_set('ok', 'Testovací e-mail byl odeslán na ' . $to . ' přes ' . $transport . '. Zkontrolujte prosím doručenou poštu i spam.');
        } else {
            ny_flash_set('err', 'E-mail se nepodařilo odeslat přes ' . $transport . '. Zkontrolujte „Odesílatel (From:)" a config.php (mail.smtp_*).');
        }
    }
    ny_redirect('settings.php');
}

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
                            <?= $meta['type'] === 'number' ? 'step="' . e((string)($meta['step'] ?? '1')) . '" min="' . e((string)($meta['min'] ?? '1')) . '"' : '' ?>>
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

<?php
    $mailCfg    = ny_config()['mail'] ?? [];
    $mailFrom   = trim((string)($mailCfg['from'] ?? '')) ?: (ny_setting('mail_from') ?: ny_setting('email'));
    $smtpHost   = trim((string)($mailCfg['smtp_host'] ?? ''));
    $smtpPort   = (int)($mailCfg['smtp_port'] ?? 587);
    $smtpSecure = strtoupper((string)($mailCfg['smtp_secure'] ?? 'tls'));
    $smtpUser   = trim((string)($mailCfg['smtp_user'] ?? ''));
?>
<div class="admin-card">
    <h2>Testovací e-mail</h2>
    <p class="hint">Odešle jednorázový testovací e-mail podle aktuálního nastavení. Pomůže ověřit, že server umí odesílat poštu (SMTP nebo PHP mail()).</p>
    <dl class="mail-diag">
        <dt>Přenos</dt>
        <dd>
            <?php if ($smtpHost !== ''): ?>
                <span class="badge badge-success">SMTP</span>
                <code><?= e($smtpHost) ?>:<?= (int)$smtpPort ?></code>
                <span class="hint">(<?= e($smtpSecure ?: 'TLS') ?><?= $smtpUser !== '' ? ', auth: ' . e($smtpUser) : '' ?>)</span>
            <?php else: ?>
                <span class="badge">PHP mail()</span>
                <span class="hint">SMTP není v <code>config.php</code> nakonfigurováno.</span>
            <?php endif; ?>
        </dd>
        <dt>Odesílatel</dt>
        <dd><code><?= e($mailFrom ?: '—') ?></code></dd>
        <dt>Notifikace adminovi</dt>
        <dd><code><?= e(ny_admin_notify_email() ?: '—') ?></code></dd>
    </dl>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="test_mail">
        <div class="admin-form-row">
            <label>Poslat testovací e-mail na
                <input type="email" name="test_mail_to" value="<?= e((string)ny_admin_notify_email()) ?>" required>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-secondary" type="submit">Odeslat testovací e-mail</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <h2>Záloha databáze</h2>
    <p class="hint">Stáhne kompletní SQL dump všech tabulek <code>ny_*</code> (schéma i data). Uložený soubor lze později naimportovat zpět přes phpMyAdmin.</p>
    <div class="row form-actions">
        <a class="btn btn-primary" href="backup.php"><?= ny_icon('download', 16) ?> Stáhnout SQL zálohu</a>
    </div>
</div>
<?php ny_admin_render_footer();
