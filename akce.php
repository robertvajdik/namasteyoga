<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$slug  = trim((string)($_GET['slug'] ?? ''));
$event = $slug !== '' ? ny_event_by_slug($slug) : null;

if ($slug !== '' && !$event) {
    http_response_code(404);
}

$events = ny_events_published();

ny_render_header(
    $event ? ($event['title'] . ' · ' . t('akce.title')) : t('akce.title'),
    'akce',
    ['description' => $event
        ? ((string)($event['summary'] ?? '') ?: (string)$event['title'])
        : t('akce.meta.description')]
);

$formatDate = static function (?string $d): string {
    if (!$d) return '';
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('j. n. Y') : $d;
};
$isPast = static function (?string $d): bool {
    if (!$d) return false;
    return $d < date('Y-m-d');
};
?>

<?php if ($event): ?>
<section class="event-detail">
    <a class="event-back" href="akce.php"><?= ny_icon('chevron-left', 16) ?> <?= e(t('akce.back')) ?></a>
    <?php if (!empty($event['image'])): ?>
        <figure class="event-detail-cover">
            <img src="assets/events/<?= e(rawurlencode((string)$event['image'])) ?>" alt="<?= e((string)$event['title']) ?>">
        </figure>
    <?php endif; ?>
    <div class="event-detail-body">
        <div class="eyebrow"><?= e(t('akce.eyebrow')) ?></div>
        <h1 class="event-detail-title"><?= e((string)$event['title']) ?></h1>
        <?php if (!empty($event['subtitle'])): ?>
            <p class="event-detail-lead"><?= e((string)$event['subtitle']) ?></p>
        <?php endif; ?>
        <ul class="event-meta">
            <?php if (!empty($event['event_date'])): ?>
                <li><?= ny_icon('calendar', 16) ?> <?= e($formatDate($event['event_date'])) ?></li>
            <?php endif; ?>
            <?php if (!empty($event['event_time'])): ?>
                <li><?= ny_icon('clock', 16) ?> <?= e((string)$event['event_time']) ?></li>
            <?php endif; ?>
            <?php if (!empty($event['location'])): ?>
                <li><?= ny_icon('map-pin', 16) ?> <?= e((string)$event['location']) ?></li>
            <?php endif; ?>
            <?php if (!empty($event['price'])): ?>
                <li><?= ny_icon('tag', 16) ?> <?= e((string)$event['price']) ?></li>
            <?php endif; ?>
        </ul>
        <?php if (!empty($event['body'])): ?>
            <div class="event-detail-content"><?= $event['body'] ?></div>
        <?php endif; ?>
        <?php if (!empty($event['cta_url'])): ?>
            <div class="event-detail-cta">
                <a class="btn btn-primary" href="<?= e((string)$event['cta_url']) ?>">
                    <?= e((string)($event['cta_label'] ?: t('akce.cta.default'))) ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php else: ?>
<section class="akce-hero">
    <div class="eyebrow"><?= e(t('akce.hero.eyebrow')) ?></div>
    <h1><?= e(t('akce.hero.title')) ?></h1>
    <p class="akce-hero-lead"><?= e(t('akce.hero.lead')) ?></p>
</section>

<?php if (!$events): ?>
    <p class="hint"><?= e(t('akce.empty')) ?></p>
<?php else: ?>
    <section class="event-grid">
        <?php foreach ($events as $ev):
            $past = $isPast($ev['event_date'] ?? null);
            $href = 'akce.php?slug=' . rawurlencode((string)$ev['slug']);
        ?>
            <article class="event-card <?= $past ? 'event-card--past' : '' ?>">
                <a class="event-card-link" href="<?= e($href) ?>">
                    <?php if (!empty($ev['image'])): ?>
                        <div class="event-card-cover">
                            <img src="assets/events/<?= e(rawurlencode((string)$ev['image'])) ?>" alt="<?= e((string)$ev['title']) ?>" loading="lazy">
                        </div>
                    <?php else: ?>
                        <div class="event-card-cover event-card-cover--placeholder" aria-hidden="true">
                            <?= ny_icon('calendar', 32) ?>
                        </div>
                    <?php endif; ?>
                    <div class="event-card-body">
                        <?php if (!empty($ev['event_date'])): ?>
                            <div class="event-card-date">
                                <?= e($formatDate($ev['event_date'])) ?>
                                <?php if ($past): ?><span class="event-card-badge"><?= e(t('akce.past')) ?></span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <h2 class="event-card-title"><?= e((string)$ev['title']) ?></h2>
                        <?php if (!empty($ev['subtitle'])): ?>
                            <p class="event-card-subtitle"><?= e((string)$ev['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($ev['summary'])): ?>
                            <p class="event-card-summary"><?= e((string)$ev['summary']) ?></p>
                        <?php endif; ?>
                        <span class="event-card-more"><?= e(t('akce.more')) ?> →</span>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php endif; ?>

<?php ny_render_footer();
