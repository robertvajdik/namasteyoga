<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$pdo   = ny_db();
$today = new DateTimeImmutable('today');
$dow   = (int)$today->format('N');

// A little dynamic content: next 6 upcoming class slots (today + next 6 days).
$soonStmt = $pdo->prepare(
    'SELECT id, name, teacher, room, day_of_week, start_time, end_time
       FROM ny_classes
      WHERE active = 1
      ORDER BY FIELD(day_of_week, ?, ?, ?, ?, ?, ?, ?), start_time
      LIMIT 6'
);
$order = [];
for ($i = 0; $i < 7; $i++) {
    $order[] = (($dow - 1 + $i) % 7) + 1;
}
$soonStmt->execute($order);
$soon = $soonStmt->fetchAll();

$daysCz = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];

$settings = ny_settings_all();
$siteName = $settings['site_name'] ?: 'Studio Namasté';
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443) ? 'https' : 'http';
$origin   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'namasteyoga.cz');

$ldOrganization = [
    '@context' => 'https://schema.org',
    '@type'    => 'LocalBusiness',
    '@id'      => $origin . '/#studio',
    'name'     => $siteName,
    'url'      => $origin . '/',
    'image'    => $origin . '/assets/logoCream.png',
    'telephone'=> $settings['phone'] ?? '',
    'email'    => $settings['email'] ?? '',
    'address'  => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => $settings['address'] ?? '',
        'addressLocality' => 'Uherský Brod',
        'addressCountry'  => 'CZ',
    ],
    'geo' => [
        '@type'    => 'GeoCoordinates',
        'latitude' => (float)($settings['map_lat']  ?: 49.0255),
        'longitude'=> (float)($settings['map_lon']  ?: 17.6512),
    ],
    'sameAs' => array_values(array_filter([
        $settings['facebook_url']  ?? '',
        $settings['instagram_url'] ?? '',
        $settings['youtube_url']   ?? '',
    ])),
    'priceRange' => '$$',
    'openingHoursSpecification' => [[
        '@type'     => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
        'opens'     => '07:00',
        'closes'    => '21:00',
    ]],
];

$ldWebsite = [
    '@context' => 'https://schema.org',
    '@type'    => 'WebSite',
    '@id'      => $origin . '/#website',
    'name'     => $siteName,
    'url'      => $origin . '/',
    'inLanguage' => 'cs-CZ',
    'publisher'  => ['@id' => $origin . '/#studio'],
];

$ldOffers = [
    '@context' => 'https://schema.org',
    '@type'    => 'ItemList',
    'name'     => 'Nabídka studia',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Otevřené lekce jógy',   'url' => $origin . '/lekce.php'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Pilates a Core',        'url' => $origin . '/lekce.php#pilates'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => 'Uzavřené kurzy',        'url' => $origin . '/lekce.php#kurzy'],
        ['@type' => 'ListItem', 'position' => 4, 'name' => 'Individuální lekce',    'url' => $origin . '/individualni.php'],
        ['@type' => 'ListItem', 'position' => 5, 'name' => 'Regenerační masáže',    'url' => $origin . '/masaze.php'],
        ['@type' => 'ListItem', 'position' => 6, 'name' => 'Workshopy a akce',      'url' => $origin . '/lekce.php#akce'],
    ],
];

$ldFaq = [
    '@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => [
        [
            '@type' => 'Question',
            'name'  => 'Musím být pokročilý, abych mohl přijít?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Vůbec ne. Máme lekce pro úplné začátečníky i pro pokročilé. Když si nevíte rady, napište nám – pomůžeme vybrat.'],
        ],
        [
            '@type' => 'Question',
            'name'  => 'Jak to funguje s rezervací?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Rezervovat se dá online v kalendáři. Zrušit můžete nejpozději 12 hodin před začátkem.'],
        ],
        [
            '@type' => 'Question',
            'name'  => 'Co si mám vzít s sebou?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Pohodlné oblečení. Podložky a pomůcky máme na místě. Přijďte 10 minut předem, ať se v klidu rozkoukáte.'],
        ],
        [
            '@type' => 'Question',
            'name'  => 'Nabízíte permanentky?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Ano – v ceníku najdete varianty na 10 a 20 vstupů se zvýhodněnou cenou.'],
        ],
        [
            '@type' => 'Question',
            'name'  => 'Můžu přijít s dítětem?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Máme prenatal lekce a specializované kurzy pro maminky. Pro obecné lekce prosíme o hlídání jinde.'],
        ],
    ],
];

$jsonLdBlocks = [$ldOrganization, $ldWebsite, $ldOffers, $ldFaq];
$jsonFlags    = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

ny_render_header('Úvod', 'home', ['bare' => true, 'overlay' => true]);
?>
<?php foreach ($jsonLdBlocks as $ld): ?>
<script type="application/ld+json"><?= json_encode($ld, $jsonFlags) ?></script>
<?php endforeach; ?>
<section class="hero hero-home">
    <div class="hero-inner">
        <div class="hero-eyebrow">Studio Namasté · Uherský Brod</div>
        <h1>Vítejte v Namasté</h1>
        <p class="kicker">Jóga · Pilates · Masáže · Individuální lekce · Akce</p>
        <p class="kicker kicker--sub">Pro začátečníky i pokročilé v centru Uherského Brodu.</p>
        <div class="hero-cta">
            <a class="btn btn-primary btn-lg" href="rezervace.php">Rezervovat lekci</a>
            <a class="btn btn-secondary btn-lg" href="lekce.php">Prohlédnout rozvrh</a>
        </div>
    </div>
</section>

<div class="container page">
<?php foreach (ny_flash_get() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>

<section class="section reveal">
    <div class="section-title-block">
        <div class="eyebrow">Co u nás najdete</div>
        <h2 class="section-title">Prostor pro tělo, dech a klid</h2>
        <p class="page-lead">
            Otevřené lekce, dlouhodobé kurzy, individuální praxe i regenerační masáže.
            Přijďte se nadechnout – bez ohledu na to, jestli začínáte, nebo se vracíte.
        </p>
    </div>

    <div class="feature-grid">
        <article class="feature-card" data-cat="yoga">
            <div class="feature-badge">Jóga</div>
            <h3>Otevřené lekce</h3>
            <p>Hatha, Vinyasa, Yin, Ashtanga a další. Přijďte kdykoliv v týdnu.</p>
            <a class="feature-link" href="lekce.php">Zobrazit rozvrh →</a>
        </article>
        <article class="feature-card" data-cat="pilates">
            <div class="feature-badge">Pilates</div>
            <h3>Pilates a Core</h3>
            <p>Práce s hlubokým středem těla, posílení a mobilita.</p>
            <a class="feature-link" href="lekce.php#pilates">Více o lekcích →</a>
        </article>
        <article class="feature-card" data-cat="workshop">
            <div class="feature-badge">Kurzy 2026</div>
            <h3>Uzavřené kurzy</h3>
            <p>Dlouhodobá práce v malé skupině – pánevní dno, prenatální jóga.</p>
            <a class="feature-link" href="lekce.php#kurzy">Vybrat kurz →</a>
        </article>
        <article class="feature-card" data-cat="individual">
            <div class="feature-badge">1 : 1</div>
            <h3>Individuální lekce</h3>
            <p>Praxe šitá přímo pro vás – tělo, dech i cíle.</p>
            <a class="feature-link" href="individualni.php">Domluvit lekci →</a>
        </article>
        <article class="feature-card" data-cat="massage">
            <div class="feature-badge">Masáže</div>
            <h3>Regenerační masáže</h3>
            <p>Relaxační, sportovní i thajské masáže od zkušených terapeutů.</p>
            <a class="feature-link" href="masaze.php">Vybrat masáž →</a>
        </article>
        <article class="feature-card" data-cat="events">
            <div class="feature-badge">Akce</div>
            <h3>Workshopy a akce</h3>
            <p>Víkendové workshopy, retreaty a hostující lektoři.</p>
            <a class="feature-link" href="lekce.php#akce">Nadcházející akce →</a>
        </article>
    </div>
</section>

<section class="section reveal band-cream">
    <div class="cols cols-2 cols-lead">
        <div>
            <div class="eyebrow">Náš přístup</div>
            <h2 class="section-title">Klidný prostor, vědomý pohyb</h2>
            <p>
                Studio Namasté je místo, kde má tělo a mysl čas se sladit. Vedeme lekce
                v malých skupinách, abychom se každému mohli věnovat individuálně.
            </p>
            <p>
                Kombinujeme moderní přístup k pohybu s tradicí jógy a pilates.
                Ať už chcete začít, protáhnout se po práci, nebo prohloubit svou praxi –
                najdete u nás lekci, která vám bude sedět.
            </p>
            <a class="btn btn-primary" href="lektori.php">Poznejte naše lektory</a>
        </div>
        <div class="stats-grid">
            <div class="stat"><div class="stat-num">10+</div><div class="stat-label">let praxe</div></div>
            <div class="stat"><div class="stat-num">25+</div><div class="stat-label">lekcí týdně</div></div>
            <div class="stat"><div class="stat-num">6</div><div class="stat-label">lektorů</div></div>
            <div class="stat"><div class="stat-num">2</div><div class="stat-label">sály</div></div>
        </div>
    </div>
</section>

<?php if ($soon): ?>
<section class="section reveal">
    <div class="section-title-block">
        <div class="eyebrow">Nejbližší lekce</div>
        <h2 class="section-title">Přidejte se tento týden</h2>
    </div>
    <div class="soon-grid">
        <?php foreach ($soon as $c): ?>
            <article class="soon-card">
                <div class="soon-day"><?= e($daysCz[(int)$c['day_of_week']]) ?></div>
                <div class="soon-time"><?= e(substr($c['start_time'], 0, 5)) ?> – <?= e(substr($c['end_time'], 0, 5)) ?></div>
                <h3 class="soon-name"><?= e($c['name']) ?></h3>
                <div class="soon-meta"><?= e($c['teacher']) ?><?php if ($c['room']): ?> · <?= e($c['room']) ?><?php endif; ?></div>
                <a class="btn btn-secondary btn-sm" href="rezervace.php">Rezervovat</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section reveal">
    <div class="section-title-block">
        <div class="eyebrow">Ze studia</div>
        <h2 class="section-title">Co říkají naši klienti</h2>
    </div>
    <div class="testimonial-grid">
        <figure class="testimonial">
            <blockquote>„Chodím sem dva roky a pořád mě to baví. Lektoři vědí, co dělají, a atmosféra je klidná a přátelská."</blockquote>
            <figcaption><strong>Petra</strong> · Vinyasa flow</figcaption>
        </figure>
        <figure class="testimonial">
            <blockquote>„Po zranění zad jsem si k józe hledala cestu dlouho. Individuální lekce mi pomohly víc, než jsem čekala."</blockquote>
            <figcaption><strong>Martin</strong> · Individuální lekce</figcaption>
        </figure>
        <figure class="testimonial">
            <blockquote>„Yin s Jitkou je pro mě povinná pátek – po týdnu v práci úplný restart."</blockquote>
            <figcaption><strong>Andrea</strong> · Yin yoga</figcaption>
        </figure>
    </div>
</section>

<section class="section reveal">
    <div class="section-title-block">
        <div class="eyebrow">Časté dotazy</div>
        <h2 class="section-title">Co často řešíme</h2>
    </div>
    <div class="faq">
        <details>
            <summary>Musím být pokročilý, abych mohl přijít?<span class="faq-caret"><?= ny_icon('chevron-down', 16) ?></span></summary>
            <p>Vůbec ne. Máme lekce pro úplné začátečníky i pro pokročilé. Když si nevíte rady, napište nám – pomůžeme vybrat.</p>
        </details>
        <details>
            <summary>Jak to funguje s rezervací?<span class="faq-caret"><?= ny_icon('chevron-down', 16) ?></span></summary>
            <p>Rezervovat se dá online v <a href="rezervace.php">kalendáři</a>. Zrušit můžete nejpozději 12 hodin před začátkem.</p>
        </details>
        <details>
            <summary>Co si mám vzít s sebou?<span class="faq-caret"><?= ny_icon('chevron-down', 16) ?></span></summary>
            <p>Pohodlné oblečení. Podložky a pomůcky máme na místě. Přijďte 10 minut předem, ať se v klidu rozkoukáte.</p>
        </details>
        <details>
            <summary>Nabízíte permanentky?<span class="faq-caret"><?= ny_icon('chevron-down', 16) ?></span></summary>
            <p>Ano – v <a href="cenik.php">ceníku</a> najdete varianty na 10 a 20 vstupů se zvýhodněnou cenou.</p>
        </details>
        <details>
            <summary>Můžu přijít s dítětem?<span class="faq-caret"><?= ny_icon('chevron-down', 16) ?></span></summary>
            <p>Máme prenatal lekce a specializované kurzy pro maminky. Pro obecné lekce prosíme o hlídání jinde.</p>
        </details>
    </div>
</section>

<section class="section reveal">
    <div class="newsletter card">
        <div class="newsletter-copy">
            <div class="eyebrow">Newsletter</div>
            <h3>Zprávy ze studia jednou měsíčně</h3>
            <p>Nové kurzy, akce a inspirace do praxe. Žádný spam, kdykoliv se dá odhlásit.</p>
        </div>
        <form class="newsletter-form" method="post" action="newsletter.php" data-recaptcha="newsletter">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="source" value="landing">
            <label class="visually-hidden" for="nl-email-home">E-mail</label>
            <input id="nl-email-home" type="email" name="email" placeholder="vas@email.cz" required>
            <button class="btn btn-primary" type="submit">Přihlásit se k odběru</button>
        </form>
    </div>
</section>

<section class="cta-band reveal">
    <div class="cta-inner">
        <h2>Připraveni se nadechnout?</h2>
        <p>Vyberte si lekci v rozvrhu a rezervujte si místo online.</p>
        <a class="btn btn-primary btn-lg" href="rezervace.php">Otevřít rozvrh</a>
    </div>
</section>
</div>

<?php ny_render_footer();
