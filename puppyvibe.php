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
        ny_flash_set('ok', t('puppy.flash.ok'));
        ny_redirect('puppyvibe.php#rezervace');
    }

    if (!ny_captcha_verify('puppyvibe', $captcha)) {
        ny_flash_set('err', t('puppy.flash.err.captcha'));
    } elseif (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'puppyvibe')) {
        ny_flash_set('err', t('puppy.flash.err.recaptcha'));
    } elseif ($name === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || $msg === '') {
        ny_flash_set('err', t('puppy.flash.err.fields'));
    } else {
        $subject = 'Puppy & štěněcí vibe – nová poptávka od ' . $name;
        $body    = "Jméno: $name\r\nE-mail: $from\r\n\r\nZpráva:\r\n$msg\r\n";
        ny_mail($email, $subject, $body, ['reply_to' => $from]);
        ny_flash_set('ok', t('puppy.flash.ok'));
    }
    ny_redirect('puppyvibe.php#rezervace');
}

$captcha = ny_captcha_generate('puppyvibe');
$user    = ny_current_user();

ny_render_header(t('puppy.title'), 'puppyvibe', ['description' => t('puppy.meta.description')]);
?>
<section class="puppy-hero">
    <div class="eyebrow"><?= e(t('puppy.hero.eyebrow')) ?></div>
    <h1 class="puppy-hero-title"><?= t('puppy.hero.title') ?></h1>
    <p class="puppy-hero-lead"><?= e(t('puppy.hero.lead')) ?></p>
    <p class="puppy-hero-sub">
        <?= e(t('puppy.hero.sub')) ?>
    </p>
    <div class="puppy-hero-cta">
        <a class="btn btn-primary" href="#rezervace"><?= e(t('puppy.hero.cta.book')) ?></a>
        <a class="btn btn-secondary" href="#o-co-jde"><?= e(t('puppy.hero.cta.more')) ?></a>
    </div>
</section>

<section class="puppy-tagline">
    <p>
        <span class="puppy-tagline-bar">|</span>
        <?= t('puppy.tagline') ?>
        <span class="puppy-tagline-bar">|</span>
    </p>
</section>

<section class="puppy-form-block" id="rezervace">
    <div class="puppy-form-head">
        <div class="eyebrow"><?= e(t('puppy.form.eyebrow')) ?></div>
        <h2><?= e(t('puppy.form.title')) ?></h2>
        <p><?= e(t('puppy.form.lead')) ?></p>
    </div>
    <form method="post" class="puppy-form" data-recaptcha="puppyvibe" novalidate>
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <label><?= e(t('puppy.form.name')) ?>
            <input type="text" name="name" value="<?= e($user ? (string)$user['display_name'] : '') ?>" required>
        </label>
        <label><?= e(t('puppy.form.email')) ?>
            <input type="email" name="email" value="<?= e($user ? (string)$user['email'] : '') ?>" required>
        </label>
        <label class="puppy-form-full"><?= e(t('puppy.form.message')) ?>
            <textarea name="message" rows="5" placeholder="<?= e(t('puppy.form.message.placeholder')) ?>" required></textarea>
        </label>
        <div class="hp-field" aria-hidden="true">
            <label><?= e(t('puppy.form.hp')) ?>
                <input type="text" name="website" tabindex="-1" autocomplete="off">
            </label>
        </div>
        <label class="captcha-field puppy-form-full"><?= e(t('puppy.form.captcha')) ?> <?= (int)$captcha['a'] ?> + <?= (int)$captcha['b'] ?>?
            <input type="text" name="captcha" inputmode="numeric" pattern="[0-9]+" autocomplete="off" required>
        </label>
        <button class="btn btn-primary btn-form" type="submit"><?= e(t('puppy.form.submit')) ?></button>
    </form>
</section>

<section class="puppy-about" id="o-co-jde">
    <div class="puppy-about-head">
        <div class="eyebrow"><?= e(t('puppy.about.eyebrow')) ?></div>
        <h2><?= e(t('puppy.about.title')) ?></h2>
    </div>
    <div class="puppy-about-body">
        <p>
            <?= t('puppy.about.p1') ?>
        </p>
        <p>
            <?= e(t('puppy.about.p2')) ?>
        </p>
    </div>
</section>

<section class="puppy-features">
    <div class="puppy-features-head">
        <div class="eyebrow"><?= e(t('puppy.features.eyebrow')) ?></div>
        <h2><?= e(t('puppy.features.title')) ?></h2>
    </div>
    <div class="puppy-features-grid">
        <div class="puppy-feature">
            <div class="puppy-feature-icon" aria-hidden="true">🐾</div>
            <h3><?= e(t('puppy.feature.1.title')) ?></h3>
            <p><?= e(t('puppy.feature.1.desc')) ?></p>
        </div>
        <div class="puppy-feature">
            <div class="puppy-feature-icon" aria-hidden="true">🐾</div>
            <h3><?= e(t('puppy.feature.2.title')) ?></h3>
            <p><?= e(t('puppy.feature.2.desc')) ?></p>
        </div>
        <div class="puppy-feature">
            <div class="puppy-feature-icon" aria-hidden="true">🐾</div>
            <h3><?= e(t('puppy.feature.3.title')) ?></h3>
            <p><?= e(t('puppy.feature.3.desc')) ?></p>
        </div>
        <div class="puppy-feature">
            <div class="puppy-feature-icon" aria-hidden="true">🐾</div>
            <h3><?= e(t('puppy.feature.4.title')) ?></h3>
            <p><?= e(t('puppy.feature.4.desc')) ?></p>
        </div>
    </div>
</section>

<section class="puppy-classes">
    <div class="puppy-classes-head">
        <div class="eyebrow"><?= e(t('puppy.classes.eyebrow')) ?></div>
        <h2><?= e(t('puppy.classes.title')) ?></h2>
    </div>
    <div class="puppy-classes-grid">
        <article class="puppy-class-card">
            <div class="puppy-class-tag">01</div>
            <h3><?= e(t('puppy.class.1.title')) ?></h3>
            <p class="puppy-class-sub"><?= e(t('puppy.class.1.sub')) ?></p>
            <p>
                <?= e(t('puppy.class.1.desc')) ?>
            </p>
            <ul class="puppy-class-list">
                <li><?= e(t('puppy.class.1.li.1')) ?></li>
                <li><?= e(t('puppy.class.1.li.2')) ?></li>
                <li><?= e(t('puppy.class.1.li.3')) ?></li>
            </ul>
        </article>
        <article class="puppy-class-card">
            <div class="puppy-class-tag">02</div>
            <h3><?= e(t('puppy.class.2.title')) ?></h3>
            <p class="puppy-class-sub"><?= e(t('puppy.class.2.sub')) ?></p>
            <p>
                <?= e(t('puppy.class.2.desc')) ?>
            </p>
            <ul class="puppy-class-list">
                <li><?= e(t('puppy.class.2.li.1')) ?></li>
                <li><?= e(t('puppy.class.2.li.2')) ?></li>
                <li><?= e(t('puppy.class.2.li.3')) ?></li>
            </ul>
        </article>
        <article class="puppy-class-card">
            <div class="puppy-class-tag">03</div>
            <h3><?= e(t('puppy.class.3.title')) ?></h3>
            <p class="puppy-class-sub"><?= e(t('puppy.class.3.sub')) ?></p>
            <p>
                <?= e(t('puppy.class.3.desc')) ?>
            </p>
            <ul class="puppy-class-list">
                <li><?= e(t('puppy.class.3.li.1')) ?></li>
                <li><?= e(t('puppy.class.3.li.2')) ?></li>
                <li><?= e(t('puppy.class.3.li.3')) ?></li>
            </ul>
        </article>
    </div>
</section>

<section class="puppy-info">
    <div class="puppy-info-head">
        <div class="eyebrow"><?= e(t('puppy.info.eyebrow')) ?></div>
        <h2><?= e(t('puppy.info.title')) ?></h2>
    </div>
    <div class="puppy-info-body">
        <p>
            <?= e(t('puppy.info.p1')) ?>
        </p>
        <p>
            <?= e(t('puppy.info.p2')) ?>
        </p>
        <div class="puppy-info-cta">
            <a class="btn btn-primary" href="#rezervace"><?= e(t('puppy.info.cta.write')) ?></a>
            <a class="btn btn-ghost" href="kontakt.php"><?= e(t('puppy.info.cta.contact')) ?></a>
        </div>
    </div>
</section>

<?php ny_render_footer();
