<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

http_response_code(404);

ny_render_header(t('notfound.title'), '', [
    'description' => t('notfound.meta.description'),
    'noindex'     => true,
]);
?>
<section class="section-title-block reveal">
    <div class="eyebrow"><?= e(t('notfound.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('notfound.hero.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('notfound.hero.lead')) ?>
    </p>
    <p style="margin-top: var(--space-5); display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="index.php" class="btn btn-primary"><?= e(t('notfound.cta.home')) ?></a>
        <a href="rezervace.php" class="btn"><?= e(t('notfound.cta.schedule')) ?></a>
    </p>
</section>

<?php ny_render_footer();
