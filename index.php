<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

ny_render_header(t('index.title'), 'home', ['description' => t('index.meta.description')]);
?>
<section class="home-hero home-hero--bg" style="background-image: url('assets/namasteyoga.cz_joga2.jpg');">
    <div class="home-hero-bg-inner">
        <div class="home-hero-body">
            <div class="eyebrow"><?= e(t('index.hero.eyebrow')) ?></div>
            <h1 class="page-title home-hero-title"><?= e(t('index.hero.title')) ?></h1>
        </div>
        <div class="home-hero-video">
            <iframe
                src="https://www.youtube-nocookie.com/embed/PaXYm4M_Ivo?rel=0"
                title="<?= e(t('index.video.title')) ?>"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
    </div>
</section>

<?php
$homeClasses = ny_gallery_by_section('home_classes');
$homeEvents  = ny_gallery_by_section('home_events');
$hasPromo    = $homeClasses || $homeEvents;
?>
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
            $altText = (string)($g['alt'] ?: $g['title']);
            $src     = 'assets/gallery/' . rawurlencode((string)$g['file']);
        ?>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e($caption) ?>"
           data-zoom-src="<?= e($src) ?>"
           data-zoom-alt="<?= e($altText) ?>"
           data-zoom-caption="<?= e($caption) ?>">
            <img src="<?= e($src) ?>" alt="<?= e($altText) ?>" loading="lazy" decoding="async">
            <?php if ($caption !== ''): ?>
                <span class="promo-tile-cap"><?= e($caption) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

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
            $altText = (string)($g['alt'] ?: $g['title']);
            $src     = 'assets/gallery/' . rawurlencode((string)$g['file']);
        ?>
        <a class="promo-tile" href="rezervace.php" aria-label="<?= e($caption) ?>"
           data-zoom-src="<?= e($src) ?>"
           data-zoom-alt="<?= e($altText) ?>"
           data-zoom-caption="<?= e($caption) ?>">
            <img src="<?= e($src) ?>" alt="<?= e($altText) ?>" loading="lazy" decoding="async">
            <?php if ($caption !== ''): ?>
                <span class="promo-tile-cap"><?= e($caption) ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($hasPromo): ?>
<div id="promo-zoom" class="promo-zoom" role="dialog" aria-modal="true" aria-labelledby="promo-zoom-cap" hidden>
    <div class="promo-zoom-backdrop" data-zoom-close></div>
    <figure class="promo-zoom-inner" role="document">
        <button type="button" class="promo-zoom-close" aria-label="<?= e(t('index.zoom.close')) ?>" data-zoom-close>×</button>
        <button type="button" class="promo-zoom-nav promo-zoom-prev" aria-label="<?= e(t('index.zoom.prev')) ?>" data-zoom-nav="-1">‹</button>
        <button type="button" class="promo-zoom-nav promo-zoom-next" aria-label="<?= e(t('index.zoom.next')) ?>" data-zoom-nav="1">›</button>
        <img id="promo-zoom-img" class="promo-zoom-img" src="" alt="">
        <figcaption class="promo-zoom-foot">
            <span id="promo-zoom-cap" class="promo-zoom-cap"></span>
            <a class="btn btn-primary btn-sm promo-zoom-cta" href="rezervace.php"><?= e(t('index.zoom.book')) ?></a>
        </figcaption>
    </figure>
</div>
<script>
(function () {
    var modal   = document.getElementById('promo-zoom');
    var imgEl   = document.getElementById('promo-zoom-img');
    var capEl   = document.getElementById('promo-zoom-cap');
    var prevBtn = modal ? modal.querySelector('.promo-zoom-prev') : null;
    var nextBtn = modal ? modal.querySelector('.promo-zoom-next') : null;
    if (!modal || !imgEl) return;

    var group = [];
    var index = 0;

    function render() {
        var tile = group[index];
        if (!tile) return;
        imgEl.src = tile.getAttribute('data-zoom-src');
        imgEl.alt = tile.getAttribute('data-zoom-alt') || '';
        var caption = tile.getAttribute('data-zoom-caption') || '';
        capEl.textContent = caption;
        capEl.hidden = !caption;
        var multi = group.length > 1;
        if (prevBtn) prevBtn.hidden = !multi;
        if (nextBtn) nextBtn.hidden = !multi;
    }
    function openZoom(tile) {
        var grid = tile.closest('.home-promo-grid');
        group = grid
            ? Array.prototype.slice.call(grid.querySelectorAll('.promo-tile[data-zoom-src]'))
            : [tile];
        index = Math.max(0, group.indexOf(tile));
        render();
        modal.hidden = false;
        document.body.classList.add('has-promo-zoom-open');
    }
    function closeZoom() {
        modal.hidden = true;
        imgEl.src = '';
        document.body.classList.remove('has-promo-zoom-open');
    }
    function step(delta) {
        if (!group.length) return;
        index = (index + delta + group.length) % group.length;
        render();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-zoom-close]')) { closeZoom(); return; }
        var navBtn = e.target.closest('[data-zoom-nav]');
        if (navBtn) { step(parseInt(navBtn.getAttribute('data-zoom-nav'), 10) || 0); return; }
        if (e.target.closest('.promo-zoom-cta')) { return; }
        var tile = e.target.closest('.promo-tile[data-zoom-src]');
        if (!tile) return;
        e.preventDefault();
        openZoom(tile);
    });

    document.addEventListener('keydown', function (e) {
        if (modal.hidden) return;
        if (e.key === 'Escape')     { closeZoom(); }
        else if (e.key === 'ArrowLeft')  { step(-1); }
        else if (e.key === 'ArrowRight') { step(1); }
    });
})();
</script>
<?php endif; ?>

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


<section class="home-final-quote">
    <blockquote><?= e(t('index.final.quote')) ?></blockquote>
    <cite><?= e(t('index.final.cite')) ?></cite>
</section>

<?php ny_render_footer();
