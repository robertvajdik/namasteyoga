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

<?php $homeClasses = ny_gallery_by_section('home_classes'); ?>
<?php if ($homeClasses): ?>
<section class="home-promo">
    <header class="home-promo-head">
        <div class="eyebrow"><?= e(t('index.promo.eyebrow')) ?></div>
        <h2 class="section-h"><?= e(t('index.promo.h')) ?></h2>
        <p class="page-lead"><?= e(t('index.promo.lead')) ?></p>
    </header>
    <div class="home-promo-grid">
        <?php foreach ($homeClasses as $g):
            $caption = (string)($g['title'] ?: $g['alt']);
        ?>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e($caption) ?>">
            <img src="assets/gallery/<?= e(rawurlencode((string)$g['file'])) ?>" alt="<?= e((string)($g['alt'] ?: $g['title'])) ?>" loading="lazy" decoding="async">
            <?php if ($caption !== ''): ?>
                <span class="promo-tile-cap"><?= e($caption) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php $homeEvents = ny_gallery_by_section('home_events'); ?>
<?php if ($homeEvents): ?>
<section class="home-promo">
    <header class="home-promo-head">
        <div class="eyebrow"><?= e(t('index.events.eyebrow')) ?></div>
        <h2 class="section-h"><?= e(t('index.events.h')) ?></h2>
        <p class="page-lead"><?= e(t('index.events.lead')) ?></p>
    </header>
    <div class="home-promo-grid">
        <?php foreach ($homeEvents as $g):
            $caption = (string)($g['title'] ?: $g['alt']);
        ?>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e($caption) ?>">
            <img src="assets/gallery/<?= e(rawurlencode((string)$g['file'])) ?>" alt="<?= e((string)($g['alt'] ?: $g['title'])) ?>" loading="lazy" decoding="async">
            <?php if ($caption !== ''): ?>
                <span class="promo-tile-cap"><?= e($caption) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

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
