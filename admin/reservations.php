<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$pdo    = ny_db();
$filter = (string)($_GET['f'] ?? 'upcoming');
$search = trim((string)($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("UPDATE ny_reservations SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        ny_flash_set('ok', 'Rezervace zrušena.');
    }
    ny_redirect('reservations.php?f=' . rawurlencode($filter) . ($search !== '' ? '&q=' . rawurlencode($search) : ''));
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$where  = [];
$params = [];

if ($filter === 'upcoming') {
    $where[]  = "r.class_date >= ?";
    $params[] = $today;
    $where[]  = "r.status = 'booked'";
} elseif ($filter === 'past') {
    $where[]  = "r.class_date < ?";
    $params[] = $today;
} elseif ($filter === 'cancelled') {
    $where[] = "r.status = 'cancelled'";
} // "all" – no filter

if ($search !== '') {
    $where[]  = "(u.display_name LIKE ? OR u.email LIKE ? OR c.name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql = 'SELECT r.*, c.name AS class_name, c.teacher, c.start_time, c.end_time,
               u.display_name, u.email, u.phone
          FROM ny_reservations r
          JOIN ny_classes c ON c.id = r.class_id
          JOIN ny_users   u ON u.id = r.user_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY r.class_date DESC, c.start_time DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

ny_admin_render_header('Rezervace', 'reservations');
?>
<div class="admin-card">
    <form method="get" class="row row-wrap">
        <div class="week-nav week-nav--tight">
            <a href="?f=upcoming"  class="<?= $filter === 'upcoming'  ? 'is-active' : '' ?>">Nadcházející</a>
            <a href="?f=past"      class="<?= $filter === 'past'      ? 'is-active' : '' ?>">Minulé</a>
            <a href="?f=cancelled" class="<?= $filter === 'cancelled' ? 'is-active' : '' ?>">Zrušené</a>
            <a href="?f=all"       class="<?= $filter === 'all'       ? 'is-active' : '' ?>">Vše</a>
        </div>
        <input type="hidden" name="f" value="<?= e($filter) ?>">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Hledat jméno, e-mail, lekci…" class="filter-input">
        <button class="btn btn-secondary" type="submit">Hledat</button>
    </form>
</div>

<div class="admin-card">
    <h2>Rezervace <span class="hint count-tag">(<?= count($rows) ?> položek)</span></h2>
    <?php if (!$rows): ?>
        <p class="hint">Žádné rezervace pro zvolený filtr.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>Datum</th><th>Čas</th><th>Lekce</th><th>Lektor</th><th>Klient</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $d = new DateTimeImmutable($r['class_date']); ?>
                <tr>
                    <td><?= e($d->format('j. n. Y')) ?></td>
                    <td><?= e(substr((string)$r['start_time'], 0, 5)) ?></td>
                    <td><?= e($r['class_name']) ?></td>
                    <td><?= e($r['teacher']) ?></td>
                    <td>
                        <?= e($r['display_name']) ?><br>
                        <small class="hint"><?= e($r['email']) ?><?php if ($r['phone']): ?> · <?= e($r['phone']) ?><?php endif; ?></small>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'booked'): ?>
                            <span class="badge badge-success">rezervováno</span>
                        <?php else: ?>
                            <span class="badge badge-danger">zrušeno</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if ($r['status'] === 'booked'): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Zrušit rezervaci?');">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-danger" type="submit">Zrušit</button>
                            </form>
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
