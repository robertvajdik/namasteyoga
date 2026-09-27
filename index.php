<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

ny_render_header(t('index.title'), 'home', ['description' => t('index.meta.description')]);
?>
<section class="home-hero home-hero--media">
    <div class="home-hero-body">
        <div class="eyebrow"><?= e(t('index.hero.eyebrow')) ?></div>
        <h1 class="page-title home-hero-title"><?= e(t('index.hero.title')) ?></h1>
    </div>
    <div class="home-hero-media">
        <img src="assets/namasteyoga.cz_joga2.jpg" alt="<?= e(t('index.hero.image.alt')) ?>" loading="eager" decoding="async">
    </div>
</section>

<section class="home-video">
    <div class="home-video-frame">
        <iframe
            src="https://www.youtube-nocookie.com/embed/PaXYm4M_Ivo?rel=0"
            title="<?= e(t('index.video.title')) ?>"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
</section>

<section class="home-promo">
    <header class="home-promo-head">
        <div class="eyebrow"><?= e(t('index.promo.eyebrow')) ?></div>
        <h2 class="section-h"><?= e(t('index.promo.h')) ?></h2>
        <p class="page-lead"><?= e(t('index.promo.lead')) ?></p>
    </header>
    <div class="home-promo-grid">
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.promo.pilatesopen.title')) ?>">
            <img src="assets/namasteyoga.cz_pilates_open_zari26_2.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.promo.pilatesopen.title')) ?></span>
        </a>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.promo.power.title')) ?>">
            <img src="assets/namasteyoga.cz_power_zari26.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.promo.power.title')) ?></span>
        </a>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.promo.restorativni.title')) ?>">
            <img src="assets/namasteyoga.cz_restorativni_zari26.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.promo.restorativni.title')) ?></span>
        </a>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.promo.tehotenska.title')) ?>">
            <img src="assets/namasteyoga.cz_tehotenska_zari26_OK.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.promo.tehotenska.title')) ?></span>
        </a>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.promo.yin.title')) ?>">
            <img src="assets/namasteyoga.cz_yin_zari26_streda_2.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.promo.yin.title')) ?></span>
        </a>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.promo.core.title')) ?>">
            <img src="assets/namasteyoga.cz_core_zari26_streda_2.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.promo.core.title')) ?></span>
        </a>
    </div>
</section>

<section class="home-promo">
    <header class="home-promo-head">
        <div class="eyebrow"><?= e(t('index.events.eyebrow')) ?></div>
        <h2 class="section-h"><?= e(t('index.events.h')) ?></h2>
        <p class="page-lead"><?= e(t('index.events.lead')) ?></p>
    </header>
    <div class="home-promo-grid">
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.events.blacklight.title')) ?>">
            <img src="assets/namasteyoga.cz_blacklight_pilates-a-yoga_rijen2026_368.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.events.blacklight.title')) ?></span>
        </a>
        <a class="promo-tile" href="puppyvibe.php" aria-label="<?= e(t('index.events.puppyvibe.title')) ?>">
            <img src="assets/namasteyoga.cz_puppyvibe_zari.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.events.puppyvibe.title')) ?></span>
        </a>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.events.brunchsep.title')) ?>">
            <img src="assets/namasteyoga.cz_pilates_yoga_brunch_zari2026_368.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.events.brunchsep.title')) ?></span>
        </a>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.events.brunchoct.title')) ?>">
            <img src="assets/namasteyoga.cz_pilates_yoga_brunch_rijen2026_369.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.events.brunchoct.title')) ?></span>
        </a>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e(t('index.events.brunchnov.title')) ?>">
            <img src="assets/namasteyoga.cz_pilates_yoga_brunch_listopad2026_369.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.events.brunchnov.title')) ?></span>
        </a>
        <a class="promo-tile" href="poukaz.php" aria-label="<?= e(t('index.events.voucher.title')) ?>">
            <img src="assets/namasteyoga.cz_darkovypoukaz_2026.jpg" alt="" loading="lazy" decoding="async">
            <span class="promo-tile-cap"><?= e(t('index.events.voucher.title')) ?></span>
        </a>
    </div>
</section>

<section class="home-features">
    <div class="home-feature">
        <div class="home-feature-num">01</div>
        <h3><?= t('index.feat.1') ?></h3>
    </div>
    <div class="home-feature">
        <div class="home-feature-num">02</div>
        <h3><?= e(t('index.feat.2')) ?></h3>
    </div>
    <div class="home-feature">
        <div class="home-feature-num">03</div>
        <h3><?= e(t('index.feat.3')) ?></h3>
    </div>
    <div class="home-feature">
        <div class="home-feature-num">04</div>
        <h3><?= e(t('index.feat.4')) ?></h3>
    </div>
</section>

<section class="home-about">
    <div class="home-about-head">
        <div class="eyebrow"><?= e(t('index.about.eyebrow')) ?></div>
        <h2 class="section-h"><?= e(t('index.about.h')) ?></h2>
    </div>
    <div class="home-about-body">
        <p>
            <?= e(t('index.about.p1')) ?>
        </p>
        <p>
            <?= e(t('index.about.p2')) ?>
        </p>
        <figure class="home-quote">
            <blockquote>
                <?= e(t('index.quote.text')) ?>
            </blockquote>
            <figcaption><?= e(t('index.quote.author')) ?></figcaption>
        </figure>
        <div class="home-about-cta">
            <a class="btn btn-primary" href="rezervace.php"><?= e(t('index.cta.book')) ?></a>
        </div>
    </div>
</section>

<section class="home-stats">
    <div class="home-stat">
        <div class="home-stat-num">10</div>
        <div class="home-stat-lbl"><?= e(t('index.stat.1.lbl')) ?></div>
    </div>
    <div class="home-stat">
        <div class="home-stat-num">16&nbsp;000</div>
        <div class="home-stat-lbl"><?= e(t('index.stat.2.lbl')) ?></div>
    </div>
    <div class="home-stat">
        <div class="home-stat-num">98&nbsp;%</div>
        <div class="home-stat-lbl"><?= e(t('index.stat.3.lbl')) ?></div>
    </div>
</section>

<section class="home-tagline">
    <p class="home-tagline-lead"><?= e(t('index.tagline.lead')) ?></p>
    <p class="home-tagline-main">
        <span class="home-tagline-bar">|</span>
        <?= e(t('index.tagline.main')) ?>
        <span class="home-tagline-bar">|</span>
    </p>
</section>

<section class="home-final-quote">
    <blockquote><?= e(t('index.final.quote')) ?></blockquote>
    <cite><?= e(t('index.final.cite')) ?></cite>
</section>

<?php ny_render_footer();
