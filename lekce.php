<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$pdo = ny_db();
$classes = $pdo->query(
    'SELECT * FROM ny_classes WHERE active = 1 ORDER BY day_of_week, start_time'
)->fetchAll();

$daysCz = [
    1 => t('lekce.days.1'),
    2 => t('lekce.days.2'),
    3 => t('lekce.days.3'),
    4 => t('lekce.days.4'),
    5 => t('lekce.days.5'),
    6 => t('lekce.days.6'),
    7 => t('lekce.days.7'),
];

$categories = ny_categories_active();

function ny_category_page(string $name): string {
    $n = mb_strtolower($name);
    if (str_contains($n, 'pilates'))  return 'pilates';
    if (str_contains($n, 'masáž'))    return 'massage';
    if (str_contains($n, 'workshop')) return 'workshop';
    if (str_contains($n, 'individ'))  return 'individual';
    return 'yoga';
}

ny_render_header(t('lekce.title'), 'lekce', ['description' => t('lekce.meta.description')]);
?>
<section class="page-hero-media page-hero-media--bg" style="background-image: url('assets/banners/lekceakurzy_namasteyoga.cz.jpg');" role="img" aria-label="<?= e(t('lekce.hero.title')) ?>">
    <div class="page-hero-media-body">
        <div class="eyebrow"><?= e(t('lekce.hero.eyebrow')) ?></div>
        <h1 class="page-title"><?= e(t('lekce.hero.title')) ?></h1>
        <p class="page-lead page-lead--start">
            <?= e(t('lekce.hero.lead')) ?>
        </p>
    </div>
</section>

<?php if ($categories): ?>
<section class="cat-grid">
    <?php foreach ($categories as $c):
        $slug = (string)$c['slug'];
    ?>
        <article class="cat-card" id="<?= e($slug) ?>" data-cat="<?= e($slug) ?>">
            <div class="cat-icon"></div>
            <h3><?= e((string)$c['label']) ?></h3>
            <?php if (!empty($c['description'])): ?>
                <p><?= e((string)$c['description']) ?></p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<h2 class="section-h section-h-gap" id="rozvrh"><?= e(t('lekce.schedule.title')) ?></h2>
<p class="hint"><?= t('lekce.schedule.hint') ?></p>

<div class="week-list">
    <?php for ($d = 1; $d <= 7; $d++):
        $dayClasses = array_values(array_filter($classes, fn($c) => (int)$c['day_of_week'] === $d));
        if (!$dayClasses) continue;
    ?>
        <section class="week-day">
            <h3 class="week-day-head"><?= e($daysCz[$d]) ?></h3>
            <ul class="week-day-list">
                <?php foreach ($dayClasses as $c):
                    $cat = ny_category_page((string)$c['name']);
                ?>
                    <li class="week-day-item" data-cat="<?= e($cat) ?>">
                        <div class="wdi-time"><?= e(substr($c['start_time'], 0, 5)) ?> – <?= e(substr($c['end_time'], 0, 5)) ?></div>
                        <div class="wdi-name"><?= e($c['name']) ?></div>
                        <div class="wdi-meta"><?= e($c['teacher']) ?><?php if ($c['room']): ?> · <?= e($c['room']) ?><?php endif; ?></div>
                        <a class="btn btn-secondary btn-sm wdi-btn" href="rezervace.php"><?= e(t('lekce.schedule.book')) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endfor; ?>
</div>

<section class="cta-band">
    <div class="cta-inner">
        <h2><?= e(t('lekce.cta.title')) ?></h2>
        <p><?= e(t('lekce.cta.lead')) ?></p>
        <a class="btn btn-primary btn-lg" href="rezervace.php"><?= e(t('lekce.cta.button')) ?></a>
    </div>
</section>

<?php ny_render_footer();
