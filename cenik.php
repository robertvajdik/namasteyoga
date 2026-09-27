<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';
ny_render_header(t('cenik.title'), 'cenik', ['description' => t('cenik.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('cenik.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('cenik.hero.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('cenik.hero.lead')) ?>
    </p>
</section>

<h2 class="section-h"><?= e(t('cenik.open.title')) ?></h2>
<div class="price-grid">
    <article class="price-card">
        <div class="price-eyebrow"><?= e(t('cenik.open.1.eyebrow')) ?></div>
        <h3><?= e(t('cenik.open.1.title')) ?></h3>
        <div class="price-amount"><?= e(t('cenik.open.1.amount')) ?></div>
        <p><?= e(t('cenik.open.1.desc')) ?></p>
    </article>
    <article class="price-card featured">
        <div class="price-eyebrow"><?= e(t('cenik.open.2.eyebrow')) ?></div>
        <h3><?= e(t('cenik.open.2.title')) ?></h3>
        <div class="price-amount"><?= e(t('cenik.open.2.amount')) ?></div>
        <p><?= e(t('cenik.open.2.desc')) ?></p>
    </article>
    <article class="price-card">
        <div class="price-eyebrow"><?= e(t('cenik.open.3.eyebrow')) ?></div>
        <h3><?= e(t('cenik.open.3.title')) ?></h3>
        <div class="price-amount"><?= e(t('cenik.open.3.amount')) ?></div>
        <p><?= e(t('cenik.open.3.desc')) ?></p>
    </article>
</div>

<h2 class="section-h section-h-gap"><?= e(t('cenik.individ.title')) ?></h2>
<div class="price-grid">
    <article class="price-card">
        <h3><?= e(t('cenik.individ.1.title')) ?></h3>
        <div class="price-amount"><?= e(t('cenik.individ.1.amount')) ?></div>
    </article>
    <article class="price-card">
        <h3><?= e(t('cenik.individ.2.title')) ?></h3>
        <div class="price-amount"><?= e(t('cenik.individ.2.amount')) ?></div>
    </article>
    <article class="price-card">
        <h3><?= e(t('cenik.individ.3.title')) ?></h3>
        <div class="price-amount"><?= e(t('cenik.individ.3.amount')) ?></div>
    </article>
</div>

<h2 class="section-h section-h-gap"><?= e(t('cenik.massage.title')) ?></h2>
<div class="tbl-wrap">
    <table class="tbl">
        <thead><tr><th><?= e(t('cenik.massage.th.name')) ?></th><th><?= e(t('cenik.massage.th.duration')) ?></th><th><?= e(t('cenik.massage.th.price')) ?></th></tr></thead>
        <tbody>
            <tr><td data-label="<?= e(t('cenik.massage.th.name')) ?>"><?= e(t('cenik.massage.1.name')) ?></td><td data-label="<?= e(t('cenik.massage.th.duration')) ?>"><?= e(t('cenik.massage.1.duration')) ?></td><td data-label="<?= e(t('cenik.massage.th.price')) ?>"><?= e(t('cenik.massage.1.price')) ?></td></tr>
            <tr><td data-label="<?= e(t('cenik.massage.th.name')) ?>"><?= e(t('cenik.massage.2.name')) ?></td><td data-label="<?= e(t('cenik.massage.th.duration')) ?>"><?= e(t('cenik.massage.2.duration')) ?></td><td data-label="<?= e(t('cenik.massage.th.price')) ?>"><?= e(t('cenik.massage.2.price')) ?></td></tr>
            <tr><td data-label="<?= e(t('cenik.massage.th.name')) ?>"><?= e(t('cenik.massage.3.name')) ?></td><td data-label="<?= e(t('cenik.massage.th.duration')) ?>"><?= e(t('cenik.massage.3.duration')) ?></td><td data-label="<?= e(t('cenik.massage.th.price')) ?>"><?= e(t('cenik.massage.3.price')) ?></td></tr>
            <tr><td data-label="<?= e(t('cenik.massage.th.name')) ?>"><?= e(t('cenik.massage.4.name')) ?></td><td data-label="<?= e(t('cenik.massage.th.duration')) ?>"><?= e(t('cenik.massage.4.duration')) ?></td><td data-label="<?= e(t('cenik.massage.th.price')) ?>"><?= e(t('cenik.massage.4.price')) ?></td></tr>
            <tr><td data-label="<?= e(t('cenik.massage.th.name')) ?>"><?= e(t('cenik.massage.5.name')) ?></td><td data-label="<?= e(t('cenik.massage.th.duration')) ?>"><?= e(t('cenik.massage.5.duration')) ?></td><td data-label="<?= e(t('cenik.massage.th.price')) ?>"><?= e(t('cenik.massage.5.price')) ?></td></tr>
        </tbody>
    </table>
</div>

<p class="hint hint-form">
    <?= e(t('cenik.discount.note')) ?>
</p>

<?php ny_render_footer();
