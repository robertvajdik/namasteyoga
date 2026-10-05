<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$s      = ny_settings_all();
$fbUrl  = $s['facebook_url'];
$igUrl  = $s['instagram_url'];
$albums = ny_gallery_albums();

$albumSlug = isset($_GET['album']) ? (string)$_GET['album'] : '';
$currentAlbum = null;
$albumItems   = [];
if ($albumSlug !== '' && isset($albums[$albumSlug])) {
    $currentAlbum = $albums[$albumSlug];
    $albumItems   = ny_gallery_by_section($albumSlug);
} elseif ($albumSlug !== '') {
    http_response_code(404);
}

$albumCounts = [];
$albumCovers = [];
foreach ($albums as $slug => $_meta) {
    $items = ny_gallery_by_section($slug);
    $albumCounts[$slug] = count($items);
    $albumCovers[$slug] = $items[0] ?? null;
}

$photoLabel = static function (int $n): string {
    if ($n === 1) return t('galerie.album.photo_count_one');
    if ($n >= 2 && $n <= 4) return t('galerie.album.photo_count_few', $n);
    return t('galerie.album.photo_count', $n);
};

$pageTitle = $currentAlbum
    ? $currentAlbum['label'] . ' · ' . t('galerie.title')
    : t('galerie.title');

ny_render_header($pageTitle, 'galerie', [
    'description' => $currentAlbum ? $currentAlbum['description'] : t('galerie.meta.description'),
]);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">

<?php if ($currentAlbum): ?>
<section class="section-title-block reveal">
    <div class="eyebrow"><a href="galerie.php" class="album-back-link">&larr; <?= e(t('galerie.album.back')) ?></a></div>
    <h1 class="page-title"><?= e($currentAlbum['label']) ?></h1>
    <?php if ($currentAlbum['description'] !== ''): ?>
        <p class="page-lead"><?= e($currentAlbum['description']) ?></p>
    <?php endif; ?>
</section>

<?php if (!$albumItems): ?>
    <div class="card muted reveal card--centered">
        <p><?= e(t('galerie.album.empty')) ?></p>
    </div>
<?php else: ?>
    <section class="reveal gallery-section">
        <div class="gallery-grid">
            <?php foreach ($albumItems as $item):
                $src     = 'assets/gallery/' . rawurlencode($item['file']);
                $caption = $item['title'] !== '' ? $item['title'] : ($item['alt'] ?? '');
            ?>
                <div class="gallery-item-wrap">
                    <a class="gallery-item"
                       href="<?= e($src) ?>"
                       data-fancybox="album-<?= e($albumSlug) ?>"
                       data-caption="<?= e($caption) ?>"
                       data-download-src="<?= e($src) ?>">
                        <img src="<?= e($src) ?>" alt="<?= e($item['alt'] ?: $item['title']) ?>" loading="lazy">
                        <?php if ($item['title'] !== ''): ?>
                            <span class="gallery-caption"><?= e($item['title']) ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="gallery-download" href="<?= e($src) ?>" download title="<?= e(t('galerie.photo.download_all')) ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <span><?= e(t('galerie.photo.download')) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php else: ?>
<section class="section-title-block reveal">
    <div class="eyebrow"><?= e(t('galerie.hero.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('galerie.hero.title')) ?></h1>
    <p class="page-lead">
        <?= e(t('galerie.hero.lead')) ?>
    </p>
    <?php if ($fbUrl || $igUrl): ?>
    <p class="gallery-social">
        <?php if ($igUrl): ?><a class="btn btn-secondary btn-sm" href="<?= e($igUrl) ?>" target="_blank" rel="noopener"><?= ny_icon('instagram', 16) ?> <?= e(t('galerie.social.instagram')) ?></a><?php endif; ?>
        <?php if ($fbUrl): ?><a class="btn btn-secondary btn-sm" href="<?= e($fbUrl) ?>" target="_blank" rel="noopener"><?= ny_icon('facebook', 16) ?> <?= e(t('galerie.social.facebook')) ?></a><?php endif; ?>
    </p>
    <?php endif; ?>
</section>

<?php
$hasAny = false;
foreach ($albumCounts as $n) { if ($n > 0) { $hasAny = true; break; } }
?>
<?php if (!$hasAny): ?>
    <div class="card muted reveal card--centered">
        <p><?= e(t('galerie.empty')) ?></p>
    </div>
<?php else: ?>
    <section class="reveal">
        <div class="album-grid">
            <?php foreach ($albums as $slug => $meta):
                $cover = $albumCovers[$slug];
                $count = (int)$albumCounts[$slug];
            ?>
                <a class="album-card <?= $count === 0 ? 'is-empty' : '' ?>" href="<?= $count ? 'galerie.php?album=' . e(rawurlencode($slug)) : '#' ?>">
                    <div class="album-cover">
                        <?php if ($cover): ?>
                            <img src="assets/gallery/<?= e(rawurlencode($cover['file'])) ?>" alt="<?= e($meta['label']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="album-cover-empty"></div>
                        <?php endif; ?>
                        <?php if ($count > 0): ?>
                            <span class="album-count"><?= e($photoLabel($count)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="album-body">
                        <h2 class="album-title"><?= e($meta['label']) ?></h2>
                        <?php if ($meta['description'] !== ''): ?>
                            <p class="album-desc"><?= e($meta['description']) ?></p>
                        <?php endif; ?>
                        <?php if ($count > 0): ?>
                            <span class="album-cta"><?= e(t('galerie.album.open')) ?> &rarr;</span>
                        <?php else: ?>
                            <span class="album-cta album-cta-empty"><?= e(t('galerie.album.empty')) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php endif; ?>

<?php if ($currentAlbum && $albumItems): ?>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
(function () {
    if (typeof Fancybox === 'undefined') return;
    Fancybox.bind('[data-fancybox^="album-"]', {
        Toolbar: {
            display: {
                left: ['infobar'],
                middle: [],
                right: ['download', 'slideshow', 'thumbs', 'close']
            }
        },
        Thumbs: { type: 'classic' },
        Images: { zoom: true }
    });
})();
</script>
<?php endif; ?>

<?php ny_render_footer();
