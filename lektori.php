<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$teachers = ny_teachers_active();

ny_render_header('Lektoři', 'lektori');
?>
<section class="section-title-block">
    <div class="eyebrow">Kdo vás povede</div>
    <h1 class="page-title">Naši lektoři</h1>
    <p class="page-lead">
        Zkušený tým, který učí s péčí a citem. Každý lektor má svůj styl – vyberte si, co vám bude sedět.
    </p>
</section>

<?php if (!$teachers): ?>
    <p class="hint">Seznam lektorů zatím není k dispozici.</p>
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
        <h2>Přijďte na lekci</h2>
        <p>Rezervujte si termín v týdenním rozvrhu.</p>
        <a class="btn btn-primary btn-lg" href="rezervace.php">Zobrazit rozvrh</a>
    </div>
</section>

<?php ny_render_footer();
