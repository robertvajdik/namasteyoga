<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$homeClasses = ny_gallery_by_section('home_classes');

ny_render_header(t('index.title'), 'home', ['description' => t('index.meta.description')]);
?>
<section class="landing-hero">
    <div class="landing-hero-inner">
        <div class="landing-hero-copy">
            <div class="landing-eyebrow"><?= e(t('index.hero.eyebrow')) ?></div>
            <h1 class="landing-hero-title"><?= t('index.hero.title') ?></h1>
            <p class="landing-hero-lead"><?= e(t('index.hero.lead')) ?></p>
            <div class="landing-hero-ctas">
                <a href="rezervace.php" class="landing-btn landing-btn-primary"><?= e(t('index.hero.cta')) ?></a>
                <a href="#lekce" class="landing-btn landing-btn-outline"><?= e(t('index.hero.cta2')) ?></a>
            </div>
            <div class="landing-hero-stats">
                <div class="landing-stat">
                    <span class="landing-stat-num">10</span>
                    <span class="landing-stat-lbl"><?= e(t('index.stat.1.lbl')) ?></span>
                </div>
                <div class="landing-stat">
                    <span class="landing-stat-num">16&nbsp;000</span>
                    <span class="landing-stat-lbl"><?= e(t('index.stat.2.lbl')) ?></span>
                </div>
                <div class="landing-stat">
                    <span class="landing-stat-num">98&nbsp;%</span>
                    <span class="landing-stat-lbl"><?= e(t('index.stat.3.lbl')) ?></span>
                </div>
            </div>
        </div>
        <div class="landing-hero-media">
            <div class="landing-hero-video">
                <iframe
                    src="https://www.youtube-nocookie.com/embed/PaXYm4M_Ivo?rel=0"
                    title="<?= e(t('index.video.title')) ?>"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
            <div class="landing-hero-caption">
                <strong><?= e(t('index.hero.sub.eyebrow')) ?></strong>
                <span><?= e(t('index.hero.sub.title')) ?></span>
            </div>
        </div>
    </div>
</section>

<?php if ($homeClasses): ?>
<section id="lekce" class="landing-lekce">
    <div class="landing-lekce-inner">
        <div class="landing-lekce-head">
            <div class="landing-lekce-copy">
                <div class="landing-eyebrow landing-eyebrow-sage"><?= e(t('index.promo.eyebrow')) ?></div>
                <h2 class="landing-h2"><?= e(t('index.promo.h')) ?></h2>
                <p class="landing-h2-lead"><?= e(t('index.promo.lead')) ?></p>
            </div>
            <a class="landing-lekce-all" href="rezervace.php"><?= e(t('index.lekce.all')) ?></a>
        </div>
        <div class="landing-lekce-grid">
            <?php foreach ($homeClasses as $g):
                $caption = (string)($g['title'] ?: $g['alt']);
                $altText = (string)($g['alt'] ?: $g['title']);
                $src     = 'assets/gallery/' . rawurlencode((string)$g['file']);
            ?>
            <a class="landing-lekce-card" href="rezervace.php">
                <div class="landing-lekce-card-img">
                    <img src="<?= e($src) ?>" alt="<?= e($altText) ?>" loading="lazy" decoding="async">
                </div>
                <div class="landing-lekce-card-foot">
                    <span class="landing-lekce-card-name"><?= e($caption) ?></span>
                    <span class="landing-lekce-card-cta"><?= e(t('index.lekce.book')) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="landing-pick" style="--pick-img: url('assets/masaz.png');">
    <div class="landing-pick-inner">
        <div class="landing-pick-head">
            <div class="landing-eyebrow landing-eyebrow-sage"><?= e(t('index.pick.eyebrow')) ?></div>
        </div>
        <div class="landing-pick-grid">
            <a class="landing-pick-card landing-pick-card--massage" href="masaze.php">
                <div class="landing-pick-card-body">
                    <h3 class="landing-pick-card-title"><?= e(t('index.pick.1.title')) ?></h3>
                    <p class="landing-pick-card-desc"><?= e(t('index.pick.1.desc')) ?></p>
                </div>
                <span class="landing-pick-card-cta"><?= e(t('index.pick.1.cta')) ?> →</span>
            </a>
            <a class="landing-pick-card landing-pick-card--individ" href="individualni.php">
                <div class="landing-pick-card-body">
                    <h3 class="landing-pick-card-title"><?= e(t('index.pick.2.title')) ?></h3>
                    <p class="landing-pick-card-desc"><?= e(t('index.pick.2.desc')) ?></p>
                </div>
                <span class="landing-pick-card-cta"><?= e(t('index.pick.2.cta')) ?> →</span>
            </a>
            <a class="landing-pick-card landing-pick-card--akce" href="akce.php">
                <div class="landing-pick-card-body">
                    <h3 class="landing-pick-card-title"><?= e(t('index.pick.3.title')) ?></h3>
                    <p class="landing-pick-card-desc"><?= e(t('index.pick.3.desc')) ?></p>
                </div>
                <span class="landing-pick-card-cta"><?= e(t('index.pick.3.cta')) ?> →</span>
            </a>
        </div>
    </div>
</section>

<section class="landing-reasons">
    <div class="landing-reasons-inner">
        <div class="landing-reasons-copy">
            <div class="landing-eyebrow landing-eyebrow-forest"><?= e(t('index.reasons.eyebrow')) ?></div>
            <h2 class="landing-h2"><?= e(t('index.reasons.h')) ?></h2>
            <p class="landing-h2-lead"><?= e(t('index.reasons.lead')) ?></p>
        </div>
        <div class="landing-reasons-list">
            <div class="landing-reason">
                <span class="landing-reason-n">01</span>
                <span class="landing-reason-t"><?= e(t('index.reasons.1')) ?></span>
            </div>
            <div class="landing-reason">
                <span class="landing-reason-n">02</span>
                <span class="landing-reason-t"><?= e(t('index.reasons.2')) ?></span>
            </div>
            <div class="landing-reason">
                <span class="landing-reason-n">03</span>
                <span class="landing-reason-t"><?= e(t('index.reasons.3')) ?></span>
            </div>
            <div class="landing-reason">
                <span class="landing-reason-n">04</span>
                <span class="landing-reason-t"><?= e(t('index.reasons.4')) ?></span>
            </div>
        </div>
    </div>
</section>

<section class="landing-cta-band">
    <div class="landing-cta-band-inner">
        <p class="landing-cta-band-quote">„<?= e(t('index.final.quote')) ?>"</p>
        <span class="landing-cta-band-cite"><?= e(t('index.final.cite')) ?></span>
        <a class="landing-btn landing-btn-dark" href="rezervace.php"><?= e(t('index.hero.cta')) ?></a>
    </div>
</section>

<?php ny_render_footer();
