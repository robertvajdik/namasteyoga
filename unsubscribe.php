<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$token = (string)($_GET['t'] ?? '');
$done  = $token !== '' && ny_newsletter_unsubscribe_by_token($token);

ny_render_header(t('unsub.title'), '', ['description' => t('unsub.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('unsub.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('unsub.h')) ?></h1>
</section>

<div class="card text-center empty-state">
    <?php if ($done): ?>
        <div class="empty-state-title"><?= e(t('unsub.done.title')) ?></div>
        <p class="text-muted empty-state-hint"><?= e(t('unsub.done.hint')) ?></p>
    <?php elseif ($token === ''): ?>
        <div class="empty-state-title"><?= e(t('unsub.missing.title')) ?></div>
        <p class="text-muted empty-state-hint"><?= e(t('unsub.missing.hint')) ?></p>
    <?php else: ?>
        <div class="empty-state-title"><?= e(t('unsub.bad.title')) ?></div>
        <p class="text-muted empty-state-hint"><?= e(t('unsub.bad.hint')) ?></p>
    <?php endif; ?>
    <a class="btn btn-primary" href="index.php"><?= e(t('unsub.back_home')) ?></a>
</div>
<?php ny_render_footer();
