<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$massages = ny_massages_active();

ny_render_header(t('masaze.title'), 'masaze', ['description' => t('masaze.meta.description')]);
?>
<section class="page-hero-media page-hero-media--bg" style="background-image: url('assets/banners/masaze_fyzio_namasteyoga.cz.jpg');" role="img" aria-label="<?= e(t('masaze.hero.image.alt')) ?>">
    <div class="page-hero-media-body">
        <div class="eyebrow"><?= e(t('masaze.hero.eyebrow')) ?></div>
        <h1 class="page-title"><?= e(t('masaze.hero.title')) ?></h1>
        <p class="page-lead page-lead--start">
            <?= e(t('masaze.hero.lead')) ?>
        </p>
    </div>
</section>

<?php if (!$massages): ?>
    <p class="hint"><?= e(t('masaze.empty')) ?></p>
<?php else: ?>
<div class="massage-grid">
    <?php foreach ($massages as $m): ?>
        <article class="massage-card">
            <div class="massage-head">
                <h3><?= e((string)$m['name']) ?></h3>
                <?php if (!empty($m['duration'])): ?>
                    <span class="badge"><?= e((string)$m['duration']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($m['description'])): ?>
                <p><?= e((string)$m['description']) ?></p>
            <?php endif; ?>
            <?php
                $bookQuery = ['masaz' => (string)$m['name']];
                if (!empty($m['duration'])) {
                    $bookQuery['delka'] = (string)$m['duration'];
                }
                if (!empty($m['price'])) {
                    $bookQuery['cena'] = (string)$m['price'];
                }
                $bookHref = 'kontakt.php?' . http_build_query($bookQuery, '', '&', PHP_QUERY_RFC3986) . '#kontakt-form';
            ?>
            <div class="massage-foot">
                <div class="price-amount price-sm"><?= e((string)$m['price']) ?></div>
                <a class="btn btn-secondary btn-sm" href="<?= e($bookHref) ?>"><?= e(t('masaze.card.book')) ?></a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="section-title-block section-h-gap">
    <div class="eyebrow"><?= e(t('masaze.therapist.eyebrow')) ?></div>
    <h2 class="page-title"><?= e(t('masaze.therapist.title')) ?></h2>
    <p class="page-lead"><?= e(t('masaze.therapist.lead')) ?></p>
</section>
<div class="teacher-grid teacher-grid--single">
    <article class="teacher-card">
        <div class="teacher-avatar" aria-hidden="true">P</div>
        <h3 class="teacher-name">Petr Klika, Ing. arch. et Bc.</h3>
        <div class="teacher-role"><?= e(t('masaze.therapist.role')) ?></div>
        <p class="teacher-bio">
            <?= ny_phone_obf('+420 724 943 284', ny_icon('phone', 14) . ' ') ?>
        </p>
    </article>
</div>

<section class="cta-band">
    <div class="cta-inner">
        <h2><?= e(t('masaze.cta.title')) ?></h2>
        <p><?= e(t('masaze.cta.lead')) ?></p>
        <a class="btn btn-primary btn-lg" href="kontakt.php"><?= e(t('masaze.cta.button')) ?></a>
    </div>
</section>

<?php ny_render_footer();
