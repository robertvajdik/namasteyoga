<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$s      = ny_settings_all();
$fbUrl  = $s['facebook_url'];
$igUrl  = $s['instagram_url'];
$groups = ny_gallery_active_grouped();

ny_render_header('Galerie', 'galerie', [
    'description' => 'Fotografie ze studia Namasté, z lekcí, akcí a jóga festivalů.',
]);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">
<section class="section-title-block reveal">
    <div class="eyebrow">Ze života studia</div>
    <h1 class="page-title">Galerie</h1>
    <p class="page-lead">
        Studio, lekce, akce i festivaly – přinášíme kousek atmosféry, kterou u nás
        můžete zažít. Další fotky najdete také na našich sociálních sítích.
    </p>
    <?php if ($fbUrl || $igUrl): ?>
    <p class="gallery-social">
        <?php if ($igUrl): ?><a class="btn btn-secondary btn-sm" href="<?= e($igUrl) ?>" target="_blank" rel="noopener"><?= ny_icon('instagram', 16) ?> Instagram</a><?php endif; ?>
        <?php if ($fbUrl): ?><a class="btn btn-secondary btn-sm" href="<?= e($fbUrl) ?>" target="_blank" rel="noopener"><?= ny_icon('facebook', 16) ?> Facebook</a><?php endif; ?>
    </p>
    <?php endif; ?>
</section>

<?php
$hasAny = false;
foreach ($groups as $g) { if ($g['items']) { $hasAny = true; break; } }
?>

<?php if (!$hasAny): ?>
    <div class="card muted reveal" style="text-align:center">
        <p>Galerie se právě připravuje. Zatím se můžete podívat na naše sociální sítě.</p>
    </div>
<?php else: ?>
    <?php foreach ($groups as $slug => $g): if (!$g['items']) continue; ?>
        <section class="reveal gallery-section">
            <h2 class="section-h section-h-gap"><?= e($g['label']) ?></h2>
            <div class="gallery-grid">
                <?php foreach ($g['items'] as $item):
                    $src     = 'assets/gallery/' . rawurlencode($item['file']);
                    $caption = $item['title'] !== '' ? $item['title'] : ($item['alt'] ?? '');
                ?>
                    <a class="gallery-item"
                       href="<?= e($src) ?>"
                       data-fancybox="gallery-<?= e($slug) ?>"
                       data-caption="<?= e($caption) ?>">
                        <img src="<?= e($src) ?>" alt="<?= e($item['alt'] ?: $item['title']) ?>" loading="lazy">
                        <?php if ($item['title'] !== ''): ?>
                            <span class="gallery-caption"><?= e($item['title']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
(function () {
    if (typeof Fancybox === 'undefined') return;
    Fancybox.bind('[data-fancybox^="gallery-"]', {
        Toolbar: { display: { left: ['infobar'], middle: [], right: ['slideshow', 'thumbs', 'close'] } },
        Thumbs: { type: 'classic' },
        Images: { zoom: true },
    });
})();
</script>

<?php ny_render_footer();
