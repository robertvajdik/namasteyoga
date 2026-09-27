<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

ny_render_header(t('podminky.title'), 'podminky', [
    'description' => t('podminky.meta.description'),
]);
?>
<section class="section-title-block reveal">
    <div class="eyebrow"><?= e(t('podminky.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('podminky.hero.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('podminky.hero.lead')) ?>
    </p>
</section>

<article class="legal-page reveal">
    <ol class="legal-list">
        <li><?= e(t('podminky.item.1')) ?></li>
        <li><?= t('podminky.item.2') ?></li>
        <li><?= t('podminky.item.3') ?></li>
        <li><?= t('podminky.item.4') ?></li>
        <li><?= e(t('podminky.item.5')) ?></li>
        <li><?= e(t('podminky.item.6')) ?></li>
        <li><?= e(t('podminky.item.7')) ?></li>
        <li><?= e(t('podminky.item.8')) ?></li>
        <li><?= e(t('podminky.item.9')) ?></li>
        <li><?= e(t('podminky.item.10')) ?></li>
        <li><?= e(t('podminky.item.11')) ?></li>
        <li><?= e(t('podminky.item.12')) ?></li>
        <li><?= e(t('podminky.item.13')) ?></li>
    </ol>

    <p class="hint"><?= e(t('podminky.hint')) ?></p>
</article>

<?php ny_render_footer();
