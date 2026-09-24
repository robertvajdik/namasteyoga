<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$pdo = ny_db();
$now       = new DateTimeImmutable('today');
$today     = $now->format('Y-m-d');
$in7       = $now->modify('+7 days')->format('Y-m-d');
$weekStart = $now->modify('monday this week')->format('Y-m-d');
$weekEnd   = $now->modify('sunday this week')->format('Y-m-d');
$last30    = $now->modify('-30 days')->format('Y-m-d');
$last14    = $now->modify('-13 days')->format('Y-m-d');

$stats = [
    'users'          => (int)$pdo->query('SELECT COUNT(*) FROM ny_users WHERE is_guest = 0')->fetchColumn(),
    'guests'         => (int)$pdo->query('SELECT COUNT(*) FROM ny_users WHERE is_guest = 1')->fetchColumn(),
    'classes'        => (int)$pdo->query('SELECT COUNT(*) FROM ny_classes WHERE active = 1')->fetchColumn(),
    'reservations'   => (int)$pdo->query("SELECT COUNT(*) FROM ny_reservations WHERE status = 'booked'")->fetchColumn(),
];

$q = $pdo->prepare("SELECT COUNT(*) FROM ny_reservations WHERE status = 'booked' AND class_date = ?");
$q->execute([$today]);
$stats['today'] = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM ny_reservations WHERE status = 'booked' AND class_date BETWEEN ? AND ?");
$q->execute([$weekStart, $weekEnd]);
$stats['week'] = (int)$q->fetchColumn();

$q = $pdo->prepare('SELECT COUNT(*) FROM ny_users WHERE is_guest = 0 AND created_at >= ?');
$q->execute([$last30 . ' 00:00:00']);
$stats['new_users_30d'] = (int)$q->fetchColumn();

$q = $pdo->prepare(
    "SELECT
        SUM(status = 'booked')    AS booked,
        SUM(status = 'cancelled') AS cancelled
       FROM ny_reservations
      WHERE created_at >= ?"
);
$q->execute([$last30 . ' 00:00:00']);
$row = $q->fetch();
$booked30    = (int)($row['booked'] ?? 0);
$cancelled30 = (int)($row['cancelled'] ?? 0);
$total30     = $booked30 + $cancelled30;
$stats['cancel_rate'] = $total30 > 0 ? round(100 * $cancelled30 / $total30) : 0;

$q = $pdo->prepare(
    "SELECT COALESCE(SUM(taken), 0) AS taken_total, COALESCE(SUM(cap), 0) AS cap_total
       FROM (
         SELECT COUNT(*) AS taken, c.capacity AS cap
           FROM ny_reservations r
           JOIN ny_classes c ON c.id = r.class_id
          WHERE r.status = 'booked' AND r.class_date BETWEEN ? AND ?
          GROUP BY r.class_id, r.class_date, c.capacity
       ) t"
);
$q->execute([$weekStart, $weekEnd]);
$occ = $q->fetch();
$occupancy = ((int)$occ['cap_total']) > 0
    ? round(100 * (int)$occ['taken_total'] / (int)$occ['cap_total'])
    : 0;

$q = $pdo->prepare(
    "SELECT c.name, c.teacher, COUNT(*) AS n
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id
      WHERE r.status = 'booked' AND r.class_date >= ?
      GROUP BY c.id, c.name, c.teacher
      ORDER BY n DESC
      LIMIT 5"
);
$q->execute([$last30]);
$topClasses = $q->fetchAll();

$q = $pdo->prepare(
    "SELECT class_date AS d, COUNT(*) AS n
       FROM ny_reservations
      WHERE status = 'booked' AND class_date BETWEEN ? AND ?
      GROUP BY class_date"
);
$q->execute([$last14, $today]);
$byDay = [];
foreach ($q as $r) $byDay[$r['d']] = (int)$r['n'];

$dayBars = [];
for ($i = 0; $i < 14; $i++) {
    $d = $now->modify('-' . (13 - $i) . ' days');
    $ds = $d->format('Y-m-d');
    $dayBars[] = ['date' => $d, 'count' => $byDay[$ds] ?? 0];
}
$maxBar = max(1, max(array_column($dayBars, 'count')));

$upcomingStmt = $pdo->prepare(
    "SELECT r.class_date, c.name, c.teacher, c.start_time, c.capacity,
            COUNT(*) AS taken
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id
      WHERE r.status = 'booked' AND r.class_date BETWEEN ? AND ?
      GROUP BY r.class_date, c.id, c.name, c.teacher, c.start_time, c.capacity
      ORDER BY r.class_date, c.start_time
      LIMIT 12"
);
$upcomingStmt->execute([$today, $in7]);
$upcoming = $upcomingStmt->fetchAll();

$latestStmt = $pdo->prepare(
    "SELECT r.*, c.name, c.start_time, u.display_name, u.email
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id
       JOIN ny_users   u ON u.id = r.user_id
      ORDER BY r.created_at DESC
      LIMIT 8"
);
$latestStmt->execute();
$latest = $latestStmt->fetchAll();

ny_admin_render_header('Dashboard', 'dashboard');
?>
<div class="admin-stats">
    <div class="admin-stat"><div class="num"><?= $stats['today'] ?></div><div class="lbl">Dnes rezervací</div></div>
    <div class="admin-stat"><div class="num"><?= $stats['week'] ?></div><div class="lbl">Tento týden</div></div>
    <div class="admin-stat"><div class="num"><?= $occupancy ?>&nbsp;%</div><div class="lbl">Obsazenost týdne</div></div>
    <div class="admin-stat"><div class="num"><?= $stats['reservations'] ?></div><div class="lbl">Aktivních rezervací</div></div>
    <div class="admin-stat"><div class="num"><?= $stats['classes'] ?></div><div class="lbl">Aktivních lekcí</div></div>
    <div class="admin-stat"><div class="num"><?= $stats['users'] ?></div><div class="lbl">Registrovaných</div></div>
    <div class="admin-stat"><div class="num"><?= $stats['guests'] ?></div><div class="lbl">Hostů</div></div>
    <div class="admin-stat"><div class="num"><?= $stats['new_users_30d'] ?></div><div class="lbl">Noví za 30 dní</div></div>
    <div class="admin-stat"><div class="num"><?= $stats['cancel_rate'] ?>&nbsp;%</div><div class="lbl">Stornováno (30 d)</div></div>
</div>

<div class="admin-card">
    <h2>Rezervace za posledních 14 dní</h2>
    <div class="mini-chart">
        <?php foreach ($dayBars as $b):
            $h = (int)round(($b['count'] / $maxBar) * 100); ?>
            <div class="mini-chart-col" title="<?= e($b['date']->format('j. n.')) ?>: <?= $b['count'] ?>">
                <div class="mini-chart-bar-wrap">
                    <div class="mini-chart-bar" style="height: <?= max(2, $h) ?>%"></div>
                </div>
                <div class="mini-chart-num"><?= $b['count'] ?></div>
                <div class="mini-chart-lbl"><?= e($b['date']->format('j.n.')) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="admin-card">
    <h2>Nejoblíbenější lekce (30 dní)</h2>
    <?php if (!$topClasses): ?>
        <p class="hint">Zatím žádná data.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>#</th><th>Lekce</th><th>Lektor</th><th>Rezervací</th></tr></thead>
            <tbody>
            <?php foreach ($topClasses as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['teacher']) ?></td>
                    <td><span class="badge badge-success"><?= (int)$c['n'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card">
    <h2>Nadcházející týden</h2>
    <?php if (!$upcoming): ?>
        <p class="hint">Žádné aktivní rezervace v nadcházejících 7 dnech.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>Datum</th><th>Čas</th><th>Lekce</th><th>Lektor</th><th>Obsazenost</th></tr></thead>
            <tbody>
            <?php foreach ($upcoming as $r):
                $d = new DateTimeImmutable($r['class_date']); ?>
                <tr>
                    <td><?= e($d->format('j. n.')) ?></td>
                    <td><?= e(substr($r['start_time'], 0, 5)) ?></td>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($r['teacher']) ?></td>
                    <td><span class="badge"><?= (int)$r['taken'] ?> / <?= (int)$r['capacity'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card">
    <h2>Poslední rezervace</h2>
    <?php if (!$latest): ?>
        <p class="hint">Zatím žádné rezervace.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>Vytvořeno</th><th>Uživatel</th><th>Lekce</th><th>Datum</th><th>Stav</th></tr></thead>
            <tbody>
            <?php foreach ($latest as $r):
                $created = new DateTimeImmutable($r['created_at']);
                $d = new DateTimeImmutable($r['class_date']); ?>
                <tr>
                    <td><?= e($created->format('j. n. H:i')) ?></td>
                    <td><?= e($r['display_name']) ?><br><small class="hint"><?= e($r['email']) ?></small></td>
                    <td><?= e($r['name']) ?></td>
                    <td><?= e($d->format('j. n.')) ?> <?= e(substr($r['start_time'], 0, 5)) ?></td>
                    <td>
                        <?php if ($r['status'] === 'booked'): ?>
                            <span class="badge badge-success">rezervováno</span>
                        <?php else: ?>
                            <span class="badge badge-danger">zrušeno</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php ny_admin_render_footer();
