<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$massages = ny_massages_active();

// Bookable massage / physio slots = ny_classes rows with kind = 'therapy'
// (admin → Masáže – termíny). They reuse the class reservation
// flow (reserve.php / cancel.php).
$slotDays = 28;
$today    = new DateTimeImmutable('today');
$rangeEnd = $today->modify('+' . ($slotDays - 1) . ' days');
$nowHm    = (new DateTimeImmutable('now'))->format('H:i:s');
$pdo      = ny_db();
$user     = ny_current_user();
$userBalance = $user ? ny_user_balance_kc((int)$user['id']) : 0;

$therapyClasses = array_values(array_filter(
    $pdo->query('SELECT * FROM ny_classes WHERE active = 1 ORDER BY start_time')->fetchAll(),
    fn($c) => ny_is_therapy_class($c)
));

$counts = [];
$mine   = [];
if ($therapyClasses) {
    $countsStmt = $pdo->prepare(
        "SELECT class_id, class_date, COUNT(*) AS n
           FROM ny_reservations
          WHERE status = 'booked' AND class_date BETWEEN ? AND ?
          GROUP BY class_id, class_date"
    );
    $countsStmt->execute([$today->format('Y-m-d'), $rangeEnd->format('Y-m-d')]);
    foreach ($countsStmt as $r) {
        $counts[$r['class_id'] . '|' . $r['class_date']] = (int)$r['n'];
    }
    if ($user) {
        $mineStmt = $pdo->prepare(
            "SELECT class_id, class_date FROM ny_reservations
              WHERE user_id = ? AND status = 'booked' AND class_date BETWEEN ? AND ?"
        );
        $mineStmt->execute([$user['id'], $today->format('Y-m-d'), $rangeEnd->format('Y-m-d')]);
        foreach ($mineStmt as $r) {
            $mine[$r['class_id'] . '|' . $r['class_date']] = true;
        }
    }
}

// date => list of slots; skips slots that already started today and full
// slots the user doesn't hold (nothing to do with those).
$slotsByDate = [];
for ($i = 0; $i < $slotDays && $therapyClasses; $i++) {
    $date    = $today->modify('+' . $i . ' days');
    $dateStr = $date->format('Y-m-d');
    $dow     = (int)$date->format('N');
    foreach ($therapyClasses as $c) {
        if ((int)$c['day_of_week'] !== $dow) continue;
        if (!empty($c['starts_on']) && $dateStr < $c['starts_on']) continue;
        if (!empty($c['ends_on'])   && $dateStr > $c['ends_on'])   continue;
        if ($i === 0 && (string)$c['start_time'] <= $nowHm) continue;
        $key    = $c['id'] . '|' . $dateStr;
        $left   = max(0, (int)$c['capacity'] - ($counts[$key] ?? 0));
        $booked = isset($mine[$key]);
        if ($left === 0 && !$booked) continue;
        $slotsByDate[$dateStr][] = ['class' => $c, 'left' => $left, 'booked' => $booked];
    }
}

ny_render_header(t('masaze.title'), 'masaze', ['description' => t('masaze.meta.description')]);
?>
<section class="page-hero-media page-hero-media--bg page-hero-media--masaze" role="img" aria-label="<?= e(t('masaze.hero.image.alt')) ?>">
    <div class="page-hero-media-body">
        <div class="eyebrow"><?= e(t('masaze.hero.eyebrow')) ?></div>
        <h1 class="page-title"><?= e(t('masaze.hero.title')) ?></h1>
        <p class="page-lead page-lead--start">
            <?= e(t('masaze.hero.lead')) ?>
        </p>
    </div>
</section>

<?php if (!$massages): ?>
    <p class="hint"><?= e(t('masaze.empty')) ?></p>
<?php else: ?>
<div class="massage-grid">
    <?php foreach ($massages as $m): ?>
        <article class="massage-card">
            <div class="massage-head">
                <h3><?= e((string)$m['name']) ?></h3>
                <?php if (!empty($m['duration'])): ?>
                    <span class="badge"><?= e((string)$m['duration']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($m['description'])): ?>
                <p><?= e((string)$m['description']) ?></p>
            <?php endif; ?>
            <?php
                $bookQuery = ['masaz' => (string)$m['name']];
                if (!empty($m['duration'])) {
                    $bookQuery['delka'] = (string)$m['duration'];
                }
                if (!empty($m['price'])) {
                    $bookQuery['cena'] = (string)$m['price'];
                }
                $bookHref = $slotsByDate
                    ? '#terminy'
                    : 'kontakt.php?' . http_build_query($bookQuery, '', '&', PHP_QUERY_RFC3986) . '#kontakt-form';
            ?>
            <div class="massage-foot">
                <div class="price-amount price-sm"><?= e((string)$m['price']) ?></div>
                <a class="btn btn-secondary btn-sm" href="<?= e($bookHref) ?>"><?= e(t('masaze.card.book')) ?></a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section id="terminy" class="section-title-block section-h-gap">
    <div class="eyebrow"><?= e(t('masaze.slots.eyebrow')) ?></div>
    <h2 class="page-title"><?= e(t('masaze.slots.title')) ?></h2>
    <p class="page-lead"><?= e(t('masaze.slots.lead')) ?></p>
    <?php if (!$user): ?>
        <p class="hint"><?= t('rezervace.login.hint') ?></p>
    <?php else: ?>
        <p class="hint">
            <?= sprintf(e(t('rezervace.credits.balance')), (int)$userBalance) ?>
            · <a href="myprofile.php#credits"><?= e(t('rezervace.credits.topup')) ?></a>
        </p>
    <?php endif; ?>
</section>

<?php if (!$slotsByDate): ?>
    <div class="card muted card--centered">
        <p><?= e(t('masaze.slots.empty')) ?></p>
        <p><a class="btn btn-secondary btn-sm" href="kontakt.php#kontakt-form"><?= e(t('masaze.cta.button')) ?></a></p>
    </div>
<?php else: ?>
<div class="therapy-slots">
    <?php foreach ($slotsByDate as $dateStr => $slots):
        $date    = new DateTimeImmutable($dateStr);
        $isToday = $date == $today;
    ?>
    <section class="day <?= $isToday ? 'is-today' : '' ?>">
        <header class="day-head">
            <div class="day-name"><?= e(t('day.' . (int)$date->format('N'))) ?><?= $isToday ? ' · ' . e(t('masaze.slots.today')) : '' ?></div>
            <div class="day-date"><?= e($date->format('j. n.')) ?></div>
        </header>
        <div class="day-slots">
        <?php foreach ($slots as $slot):
            $c           = $slot['class'];
            $priceStr    = trim((string)($c['price'] ?? ''));
            $priceAmount = $priceStr !== '' ? ny_price_amount($priceStr) : null;
            $creditPrice = $priceAmount !== null && $priceAmount > 0 ? ny_credit_price($priceAmount) : null;
            $showMember  = $creditPrice !== null && $creditPrice < $priceAmount;
        ?>
            <article class="class-card" data-cat="massage">
                <div class="time"><?= e(substr($c['start_time'], 0, 5)) ?> – <?= e(substr($c['end_time'], 0, 5)) ?></div>
                <div class="title"><?= e($c['name']) ?></div>
                <div class="meta">
                    <?= e($c['teacher']) ?><?php if ($c['room']): ?> · <?= e($c['room']) ?><?php endif; ?>
                </div>
                <?php if ($priceStr !== ''): ?>
                    <div class="class-price">
                        <span><?= e($priceStr) ?></span>
                        <?php if ($showMember): ?>
                            <span class="class-price-member" title="<?= e(t('rezervace.price.member.title')) ?>">
                                <?= sprintf(e(t('rezervace.price.member')), $creditPrice) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <?php if ($slot['booked']): ?>
                        <span class="badge badge-success"><span class="dot"></span><?= e(t('rezervace.badge.booked')) ?></span>
                        <form method="post" action="cancel.php" class="inline">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="class_date" value="<?= e($dateStr) ?>">
                            <input type="hidden" name="return" value="masaze">
                            <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('rezervace.btn.cancel')) ?></button>
                        </form>
                    <?php else: ?>
                        <span class="badge"><?= e(t('rezervace.badge.free')) ?></span>
                        <?php if ($user): ?>
                            <form method="post" action="reserve.php" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="class_date" value="<?= e($dateStr) ?>">
                                <input type="hidden" name="return" value="masaze">
                                <button class="btn btn-primary btn-sm" type="submit"><?= e(t('rezervace.btn.reserve')) ?></button>
                            </form>
                            <?php if ($creditPrice !== null && $userBalance >= $creditPrice): ?>
                            <form method="post" action="reserve.php" class="inline"
                                  onsubmit="return confirm(<?= e(json_encode(sprintf(t('masaze.credits.confirm'), $creditPrice, $c['name']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>);">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                                <input type="hidden" name="class_date" value="<?= e($dateStr) ?>">
                                <input type="hidden" name="pay" value="credits">
                                <input type="hidden" name="return" value="masaze">
                                <button class="btn btn-sand btn-sm" type="submit" title="<?= e(sprintf(t('rezervace.credits.title'), $userBalance)) ?>">
                                    <?= sprintf(e(t('rezervace.credits.btn')), (int)$creditPrice) ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$user): ?>
                    <a class="btn btn-secondary btn-sm class-card-login" href="login.php?class_date=<?= e($dateStr) ?>&amp;class_id=<?= (int)$c['id'] ?>&amp;return=masaze"><?= e(t('rezervace.btn.login')) ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="section-title-block section-h-gap">
    <div class="eyebrow"><?= e(t('masaze.therapist.eyebrow')) ?></div>
    <h2 class="page-title"><?= e(t('masaze.therapist.title')) ?></h2>
    <p class="page-lead"><?= e(t('masaze.therapist.lead')) ?></p>
</section>
<div class="teacher-grid teacher-grid--single">
    <article class="teacher-card">
        <div class="teacher-avatar" aria-hidden="true">P</div>
        <h3 class="teacher-name">Petr Klika, Ing. arch. et Bc.</h3>
        <div class="teacher-role"><?= e(t('masaze.therapist.role')) ?></div>
        <p class="teacher-bio">
            <?= ny_phone_obf('+420 724 943 284', ny_icon('phone', 14) . ' ') ?>
        </p>
    </article>
</div>

<section class="cta-band">
    <div class="cta-inner">
        <h2><?= e(t('masaze.cta.title')) ?></h2>
        <p><?= e(t('masaze.cta.lead')) ?></p>
        <a class="btn btn-primary btn-lg" href="kontakt.php"><?= e(t('masaze.cta.button')) ?></a>
    </div>
</section>

<?php ny_render_footer();
