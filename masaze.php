<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$massages = ny_massages_active();

ny_render_header(t('masaze.title'), 'masaze', ['description' => t('masaze.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('masaze.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('masaze.hero.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('masaze.hero.lead')) ?>
    </p>
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
            <div class="massage-foot">
                <div class="price-amount price-sm"><?= e((string)$m['price']) ?></div>
                <a class="btn btn-secondary btn-sm" href="kontakt.php"><?= e(t('masaze.card.book')) ?></a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="cta-band">
    <div class="cta-inner">
        <h2><?= e(t('masaze.cta.title')) ?></h2>
        <p><?= e(t('masaze.cta.lead')) ?></p>
        <a class="btn btn-primary btn-lg" href="kontakt.php"><?= e(t('masaze.cta.button')) ?></a>
    </div>
</section>

<?php ny_render_footer();
