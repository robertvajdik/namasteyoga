<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$email = trim((string)($_GET['e'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

ny_render_header(t('nl_thx.title'), '', ['description' => t('nl_thx.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('nl_thx.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('nl_thx.h')) ?></h1>
    <p class="page-lead"><?= e(t('nl_thx.lead')) ?></p>
</section>

<div class="card text-center empty-state newsletter-thanks">
    <div class="newsletter-thanks-icon" aria-hidden="true">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6h16v12H4z"/>
            <path d="M4 6l8 7 8-7"/>
            <path d="M9 14l2 2 4-4"/>
        </svg>
    </div>
    <div class="empty-state-title"><?= e(t('nl_thx.saved')) ?></div>
    <p class="text-muted empty-state-hint">
        <?php if ($email !== ''): ?>
            <?= sprintf(e(t('nl_thx.added_email')), '<strong>' . e($email) . '</strong>') ?>
        <?php else: ?>
            <?= e(t('nl_thx.added_generic')) ?>
        <?php endif; ?>
        <?= e(t('nl_thx.unsub_hint')) ?>
    </p>
    <div class="row row-center newsletter-thanks-actions">
        <a class="btn btn-primary" href="index.php"><?= e(t('nl_thx.back_home')) ?></a>
        <a class="btn btn-secondary" href="rezervace.php"><?= e(t('nl_thx.see_schedule')) ?></a>
    </div>
</div>
<?php ny_render_footer();
