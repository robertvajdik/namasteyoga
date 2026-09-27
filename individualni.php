<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';
ny_render_header(t('individ.title'), 'individ', ['description' => t('individ.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('individ.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('individ.hero.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('individ.hero.lead')) ?>
    </p>
</section>

<div class="cols cols-2">
    <section class="card">
        <h2><?= e(t('individ.when.title')) ?></h2>
        <ul class="check-list">
            <li><?= e(t('individ.when.1')) ?></li>
            <li><?= e(t('individ.when.2')) ?></li>
            <li><?= e(t('individ.when.3')) ?></li>
            <li><?= e(t('individ.when.4')) ?></li>
            <li><?= e(t('individ.when.5')) ?></li>
        </ul>
    </section>
    <section class="card muted">
        <h2><?= e(t('individ.how.title')) ?></h2>
        <ol class="steps">
            <li><?= t('individ.how.1') ?></li>
            <li><?= t('individ.how.2') ?></li>
            <li><?= t('individ.how.3') ?></li>
            <li><?= t('individ.how.4') ?></li>
        </ol>
        <p><a class="btn btn-primary btn-form" href="kontakt.php"><?= e(t('individ.how.button')) ?></a></p>
    </section>
</div>

<h2 class="section-h section-h-gap"><?= e(t('individ.prices.title')) ?></h2>
<div class="price-grid">
    <article class="price-card">
        <div class="price-eyebrow"><?= e(t('individ.prices.1.eyebrow')) ?></div>
        <h3><?= e(t('individ.prices.1.title')) ?></h3>
        <div class="price-amount"><?= e(t('individ.prices.1.amount')) ?></div>
        <p><?= e(t('individ.prices.1.desc')) ?></p>
    </article>
    <article class="price-card featured">
        <div class="price-eyebrow"><?= e(t('individ.prices.2.eyebrow')) ?></div>
        <h3><?= e(t('individ.prices.2.title')) ?></h3>
        <div class="price-amount"><?= e(t('individ.prices.2.amount')) ?></div>
        <p><?= e(t('individ.prices.2.desc')) ?></p>
    </article>
    <article class="price-card">
        <div class="price-eyebrow"><?= e(t('individ.prices.3.eyebrow')) ?></div>
        <h3><?= e(t('individ.prices.3.title')) ?></h3>
        <div class="price-amount"><?= e(t('individ.prices.3.amount')) ?></div>
        <p><?= e(t('individ.prices.3.desc')) ?></p>
    </article>
</div>

<?php ny_render_footer();
