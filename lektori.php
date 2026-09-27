<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$teachers = ny_teachers_active();

ny_render_header(t('lektori.title'), 'lektori', ['description' => t('lektori.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('lektori.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('lektori.hero.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('lektori.hero.lead')) ?>
    </p>
</section>

<?php if (!$teachers): ?>
    <p class="hint"><?= e(t('lektori.empty')) ?></p>
<?php else: ?>
<div class="teacher-grid">
    <?php foreach ($teachers as $t): ?>
        <article class="teacher-card">
            <?php if (!empty($t['photo'])): ?>
                <div class="teacher-avatar has-photo">
                    <img src="assets/teachers/<?= e(rawurlencode($t['photo'])) ?>" alt="<?= e((string)$t['name']) ?>" loading="lazy">
                </div>
            <?php else: ?>
                <div class="teacher-avatar" aria-hidden="true"><?= e(mb_substr((string)$t['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <h3 class="teacher-name"><?= e((string)$t['name']) ?></h3>
            <?php if (!empty($t['role'])): ?>
                <div class="teacher-role"><?= e((string)$t['role']) ?></div>
            <?php endif; ?>
            <?php if (!empty($t['bio'])): ?>
                <p class="teacher-bio"><?= e((string)$t['bio']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="cta-band">
    <div class="cta-inner">
        <h2><?= e(t('lektori.cta.title')) ?></h2>
        <p><?= e(t('lektori.cta.lead')) ?></p>
        <a class="btn btn-primary btn-lg" href="rezervace.php"><?= e(t('lektori.cta.button')) ?></a>
    </div>
</section>

<?php ny_render_footer();
