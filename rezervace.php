<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$today   = new DateTimeImmutable('today');
$weekArg = $_GET['week'] ?? null;
$monday  = ny_week_start(is_string($weekArg) ? $weekArg : null);
$sunday  = $monday->modify('+6 days');

$prevWeek = $monday->modify('-7 days')->format('Y-m-d');
$nextWeek = $monday->modify('+7 days')->format('Y-m-d');

$pdo = ny_db();
$classes = $pdo->query(
    'SELECT * FROM ny_classes WHERE active = 1 ORDER BY day_of_week, start_time'
)->fetchAll();

$weekStart = $monday->format('Y-m-d');
$weekEnd   = $sunday->format('Y-m-d');
$countsStmt = $pdo->prepare(
    "SELECT class_id, class_date, COUNT(*) AS n
       FROM ny_reservations
      WHERE status = 'booked' AND class_date BETWEEN ? AND ?
      GROUP BY class_id, class_date"
);
$countsStmt->execute([$weekStart, $weekEnd]);
$counts = [];
foreach ($countsStmt as $r) {
    $counts[$r['class_id'] . '|' . $r['class_date']] = (int)$r['n'];
}

$mine = [];
$user = ny_current_user();
if ($user) {
    $mineStmt = $pdo->prepare(
        "SELECT class_id, class_date FROM ny_reservations
          WHERE user_id = ? AND status = 'booked'
            AND class_date BETWEEN ? AND ?"
    );
    $mineStmt->execute([$user['id'], $weekStart, $weekEnd]);
    foreach ($mineStmt as $r) {
        $mine[$r['class_id'] . '|' . $r['class_date']] = true;
    }
}

// Loose categorization by class name → design's category colour set.
function ny_category(string $name): string {
    $n = mb_strtolower($name);
    if (str_contains($n, 'pilates'))      return 'pilates';
    if (str_contains($n, 'masáž'))        return 'massage';
    if (str_contains($n, 'workshop'))     return 'workshop';
    if (str_contains($n, 'individ'))      return 'individual';
    return 'yoga';
}

$daysCz = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];
$weekIsoNum = (int)$monday->format('W');

ny_render_header('Rezervace', 'schedule');
?>
<section class="section-title-block">
    <div class="eyebrow">Rozvrh lekcí</div>
    <h1 class="page-title">Rezervace</h1>
    <p class="page-lead">
        Vyberte si lekci v týdenním rozvrhu. Rezervaci můžete zrušit nejpozději 12 hodin před začátkem.
    </p>
</section>

<div class="schedule-toolbar">
    <div class="week-nav" role="navigation" aria-label="Navigace týdnem">
        <a href="?week=<?= e($prevWeek) ?>" aria-label="Předchozí týden"><?= ny_icon('chevron-left', 16) ?></a>
        <div class="range">
            <strong><?= e($monday->format('j. n.')) ?> – <?= e($sunday->format('j. n. Y')) ?></strong>
            <small>Týden <?= $weekIsoNum ?></small>
        </div>
        <a href="?week=<?= e($nextWeek) ?>" aria-label="Následující týden"><?= ny_icon('chevron-right', 16) ?></a>
        <a href="?">Dnes</a>
    </div>
    <?php if (!$user): ?>
        <span class="hint">
            Rezervace vyžaduje přihlášení – <a href="login.php">přihlaste se</a> nebo pokračujte jako <a href="login.php#guest">host</a>.
        </span>
    <?php endif; ?>
</div>

<div class="week-grid">
<?php for ($d = 1; $d <= 7; $d++):
    $date       = $monday->modify('+' . ($d - 1) . ' days');
    $dateStr    = $date->format('Y-m-d');
    $isPast     = $date < $today;
    $isToday    = $date == $today;
    $dayClasses = array_values(array_filter($classes, fn($c) => (int)$c['day_of_week'] === $d));
?>
    <section class="day <?= $isPast ? 'is-past' : '' ?> <?= $isToday ? 'is-today' : '' ?>">
        <header class="day-head">
            <div class="day-name"><?= e($daysCz[$d]) ?></div>
            <div class="day-date"><?= e($date->format('j. n.')) ?></div>
        </header>
        <div class="day-slots">
        <?php if (!$dayClasses): ?>
            <div class="day-empty">Žádné lekce</div>
        <?php else: foreach ($dayClasses as $c):
            $key      = $c['id'] . '|' . $dateStr;
            $taken    = $counts[$key] ?? 0;
            $capacity = (int)$c['capacity'];
            $left     = max(0, $capacity - $taken);
            $booked   = isset($mine[$key]);
            $cat      = ny_category((string)$c['name']);
        ?>
            <article class="class-card <?= $left === 0 ? 'is-full' : '' ?>" data-cat="<?= e($cat) ?>">
                <div class="time"><?= e(substr($c['start_time'], 0, 5)) ?> – <?= e(substr($c['end_time'], 0, 5)) ?></div>
                <div class="title"><?= e($c['name']) ?></div>
                <div class="meta">
                    <?= e($c['teacher']) ?><?php if ($c['room']): ?> · <?= e($c['room']) ?><?php endif; ?>
                </div>
                <div class="row">
                    <?php if ($booked): ?>
                        <span class="badge badge-success"><span class="dot"></span>Rezervováno</span>
                    <?php elseif ($left === 0): ?>
                        <span class="badge badge-danger">Obsazeno</span>
                    <?php else: ?>
                        <span class="badge">Volno: <?= $left ?> / <?= $capacity ?></span>
                    <?php endif; ?>

                    <?php if ($isPast): ?>
                        <span class="hint">Proběhlo</span>
                    <?php elseif ($booked): ?>
                        <form method="post" action="cancel.php" class="inline">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="class_date" value="<?= e($dateStr) ?>">
                            <button class="btn btn-ghost btn-sm" type="submit">Zrušit</button>
                        </form>
                    <?php elseif ($user && $left > 0): ?>
                        <form method="post" action="reserve.php" class="inline">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="class_id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="class_date" value="<?= e($dateStr) ?>">
                            <button class="btn btn-primary btn-sm" type="submit">Rezervovat</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (!$user && !$isPast): ?>
                    <a class="btn btn-secondary btn-sm class-card-login" href="login.php?class_date=<?= e($dateStr) ?>">Přihlásit</a>
                <?php endif; ?>
            </article>
        <?php endforeach; endif; ?>
        </div>
    </section>
<?php endfor; ?>
</div>

<?php ny_render_footer();
