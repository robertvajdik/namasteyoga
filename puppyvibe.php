<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$s     = ny_settings_all();
$email = $s['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $name    = trim((string)($_POST['name'] ?? ''));
    $from    = strtolower(trim((string)($_POST['email'] ?? '')));
    $msg     = trim((string)($_POST['message'] ?? ''));
    $captcha = trim((string)($_POST['captcha'] ?? ''));
    $hp      = trim((string)($_POST['website'] ?? ''));

    if ($hp !== '') {
        // Silently accept – don't tip off the bot.
        ny_flash_set('ok', 'Děkujeme, vaše zpráva byla odeslána. Ozveme se vám co nejdříve.');
        ny_redirect('puppyvibe.php#rezervace');
    }

    if (!ny_captcha_verify('puppyvibe', $captcha)) {
        ny_flash_set('err', 'Kontrolní součet nesouhlasí. Zkuste to prosím znovu.');
    } elseif (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'puppyvibe')) {
        ny_flash_set('err', 'Ochrana proti robotům selhala, zkuste to prosím znovu.');
    } elseif ($name === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $msg === '') {
        ny_flash_set('err', 'Vyplňte prosím jméno, platný e-mail a zprávu.');
    } else {
        $subject = 'Puppy & štěněcí vibe – nová poptávka od ' . $name;
        $body    = "Jméno: $name\r\nE-mail: $from\r\n\r\nZpráva:\r\n$msg\r\n";
        ny_mail($email, $subject, $body, ['reply_to' => $from]);
        ny_flash_set('ok', 'Děkujeme, vaše zpráva byla odeslána. Ozveme se vám co nejdříve.');
    }
    ny_redirect('puppyvibe.php#rezervace');
}

$captcha = ny_captcha_generate('puppyvibe');
$user    = ny_current_user();

ny_render_header('Puppy & štěněcí vibe', 'puppyvibe');
?>
<section class="puppy-hero">
    <div class="eyebrow">Namasté yoga studio · Uherský Brod</div>
    <h1 class="puppy-hero-title">Puppy &amp; štěněcí vibe</h1>
    <p class="puppy-hero-lead">Místo, kde stres končí a skutečná radost začíná.</p>
    <p class="puppy-hero-sub">
        Lekce plné štěněcí lásky děláme v Uherském Brodě a širokém okolí.
    </p>
    <div class="puppy-hero-cta">
        <a class="btn btn-primary" href="#rezervace">Rezervovat místo</a>
        <a class="btn btn-secondary" href="#o-co-jde">Zjistit více</a>
    </div>
</section>

<section class="puppy-tagline">
    <p>
        <span class="puppy-tagline-bar">|</span>
        Nejroztomilejší lekce pilates &amp; jógy právě probíhají v Namasté
        <span class="puppy-tagline-bar">|</span>
    </p>
</section>

<section class="puppy-form-block" id="rezervace">
    <div class="puppy-form-head">
        <div class="eyebrow">Rezervace</div>
        <h2>Rezervujte si své místo včas</h2>
        <p>Napište nám jméno, e-mail a krátkou zprávu – ozveme se s termíny a podrobnostmi.</p>
    </div>
    <form method="post" class="puppy-form" data-recaptcha="puppyvibe" novalidate>
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <label>Jméno
            <input type="text" name="name" value="<?= e($user ? (string)$user['display_name'] : '') ?>" required>
        </label>
        <label>E-mailová adresa
            <input type="email" name="email" value="<?= e($user ? (string)$user['email'] : '') ?>" required>
        </label>
        <label class="puppy-form-full">Zpráva
            <textarea name="message" rows="5" placeholder="Napište, o jakou lekci máte zájem, kolik vás bude a preferovaný termín." required></textarea>
        </label>
        <div class="hp-field" aria-hidden="true">
            <label>Website (nechte prázdné)
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </label>
        </div>
        <label class="captcha-field puppy-form-full">Kontrolní otázka: kolik je <?= (int)$captcha['a'] ?> + <?= (int)$captcha['b'] ?>?
            <input type="text" name="captcha" inputmode="numeric" pattern="[0-9]+" autocomplete="off" required>
        </label>
        <button class="btn btn-primary btn-form" type="submit">Odeslat</button>
    </form>
</section>

<section class="puppy-about" id="o-co-jde">
    <div class="puppy-about-head">
        <div class="eyebrow">O co jde?</div>
        <h2>Cvičení, které pohladí tělo i duši</h2>
    </div>
    <div class="puppy-about-body">
        <p>
            Puppy &amp; štěněcí vibe jsou lekce jógy a pilates, které kombinují klidný pohyb, dech
            a nekonečnou dávku roztomilé přítomnosti čtyřnohých parťáků. Cvičíte v příjemné
            atmosféře studia, mezi sériemi se protahujete se štěňátky, koťátky nebo vlastními
            mazlíčky a odcházíte s úsměvem od ucha k uchu.
        </p>
        <p>
            Není potřeba žádná předchozí zkušenost – lekce vedeme pro začátečníky i pokročilé
            a přizpůsobujeme je náladě skupiny i zvířat. Chováme se k nim ohleduplně: pauzy,
            pití a mazlení jsou přirozenou součástí každé lekce.
        </p>
    </div>
</section>

<section class="puppy-features">
    <div class="puppy-features-head">
        <div class="eyebrow">Proč si lekce zamilujete?</div>
        <h2>Radost, kterou cítíte hned od první minuty</h2>
    </div>
    <div class="puppy-features-grid">
        <div class="puppy-feature">
            <div class="puppy-feature-icon" aria-hidden="true">🐾</div>
            <h3>Uvolnění stresu</h3>
            <p>Mazlení a přítomnost zvířat prokazatelně snižují hladinu kortizolu. Odejdete lehčí a v lepší náladě.</p>
        </div>
        <div class="puppy-feature">
            <div class="puppy-feature-icon" aria-hidden="true">🐾</div>
            <h3>Pohyb bez tlaku</h3>
            <p>Zapomenete na výkon – lekce jsou vedeny s citem, s prostorem pro smích i pauzy na hlazení.</p>
        </div>
        <div class="puppy-feature">
            <div class="puppy-feature-icon" aria-hidden="true">🐾</div>
            <h3>Setkání s podobně naladěnými lidmi</h3>
            <p>Sejde se parta lidí, kteří mají rádi zvířata i chvíli pro sebe. Přátelství se rodí sama.</p>
        </div>
        <div class="puppy-feature">
            <div class="puppy-feature-icon" aria-hidden="true">🐾</div>
            <h3>Zážitek, ne jen lekce</h3>
            <p>Odnesete si fotky, vzpomínky a pocit, že jste udělali něco jen pro sebe – tělo i duši.</p>
        </div>
    </div>
</section>

<section class="puppy-classes">
    <div class="puppy-classes-head">
        <div class="eyebrow">Jaké zvířecí lekce u nás najdete?</div>
        <h2>Vyberte si svoji dávku štěstí</h2>
    </div>
    <div class="puppy-classes-grid">
        <article class="puppy-class-card">
            <div class="puppy-class-tag">01</div>
            <h3>Štěněcí lekce</h3>
            <p class="puppy-class-sub">Jóga a pilates se štěňátky</p>
            <p>
                Klasika mezi zvířecími lekcemi. Cvičíme společně se štěňátky ověřeného chovatele,
                která si mezi ásanami vyžadují mazlení, pobíhají po sále a vytváří tu
                nejroztomilejší kulisu, jakou znáte.
            </p>
            <ul class="puppy-class-list">
                <li>Vhodné pro začátečníky i pokročilé</li>
                <li>Skupinky do 10 osob</li>
                <li>Cvičení 60 – 75 minut</li>
            </ul>
        </article>
        <article class="puppy-class-card">
            <div class="puppy-class-tag">02</div>
            <h3>Lekce s vlastními mazlíčky</h3>
            <p class="puppy-class-sub">Přiveďte svého psího parťáka</p>
            <p>
                Máte doma pejska, se kterým rádi trávíte čas? Přijďte si s ním zacvičit
                do studia. Ukážeme vám sestavu, do které svého mazlíčka zapojíte, a společně
                si užijete klidný a propojující pohyb.
            </p>
            <ul class="puppy-class-list">
                <li>Pes musí být zvyklý na cizí lidi a zvířata</li>
                <li>Skupinky do 8 párů člověk + pes</li>
                <li>Předchozí konzultace u přihlášení</li>
            </ul>
        </article>
        <article class="puppy-class-card">
            <div class="puppy-class-tag">03</div>
            <h3>Lekce s koťátky</h3>
            <p class="puppy-class-sub">Něžnější varianta se šelmičkami</p>
            <p>
                Pro milovníky kočiček nabízíme lekce v klidnějším tempu s malými koťátky.
                Sál se promění v hravé útočiště – ideální pro yin a jemný pilates,
                kdy si k vám mrňousek přijde přitulit.
            </p>
            <ul class="puppy-class-list">
                <li>Klidné cvičení jóga / yin / pilates</li>
                <li>Skupinky do 8 osob</li>
                <li>Cvičení 60 minut</li>
            </ul>
        </article>
    </div>
</section>

<section class="puppy-info">
    <div class="puppy-info-head">
        <div class="eyebrow">Informace k lekcím</div>
        <h2>Pro chovatele</h2>
    </div>
    <div class="puppy-info-body">
        <p>
            Rádi navážeme spolupráci s chovateli štěňat i koťat z Uherského Brodu a širokého
            okolí. Zvířátkům věnujeme naprostou pozornost – sál je klidný, čistý a mezi
            lekcemi jim zajistíme prostor pro odpočinek, pití i mazlení.
        </p>
        <p>
            Pokud jste chovatel a chtěli byste své štěňátka nebo koťátka poslat na lekci,
            ozvěte se nám přes formulář výše nebo přímo e-mailem – domluvíme podmínky
            a termíny na míru.
        </p>
        <div class="puppy-info-cta">
            <a class="btn btn-primary" href="#rezervace">Napsat nám</a>
            <a class="btn btn-ghost" href="kontakt.php">Kontakty studia</a>
        </div>
    </div>
</section>

<?php ny_render_footer();
