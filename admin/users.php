<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$pdo    = ny_db();
$search = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['f'] ?? 'all');
$sort   = (string)($_GET['s'] ?? 'created');
$dir    = strtolower((string)($_GET['d'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

if (!in_array($filter, ['all', 'registered', 'guest', 'admin'], true)) {
    $filter = 'all';
}

$sortMap = [
    'name'    => 'u.display_name',
    'email'   => 'u.email',
    'phone'   => 'u.phone',
    'created' => 'u.created_at',
    'type'    => 'u.is_admin DESC, u.is_guest',
    'res'     => 'active_res',
];
if (!array_key_exists($sort, $sortMap)) {
    $sort = 'created';
}

$queryString = function (array $overrides = []) use ($search, $filter, $sort, $dir): string {
    $qs = [];
    if ($filter !== 'all')             $qs['f'] = $filter;
    if ($search !== '')                $qs['q'] = $search;
    if ($sort !== 'created')           $qs['s'] = $sort;
    if ($dir !== 'desc')               $qs['d'] = $dir;
    foreach ($overrides as $k => $v) {
        if ($v === null) unset($qs[$k]); else $qs[$k] = $v;
    }
    return $qs ? '?' . http_build_query($qs) : '';
};

$sortLink = function (string $key) use ($sort, $dir, $queryString): string {
    $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
    return htmlspecialchars($queryString([
        's' => $key === 'created' ? null : $key,
        'd' => $nextDir === 'desc' ? null : 'asc',
    ]));
};
$sortArrow = function (string $key) use ($sort, $dir): string {
    if ($sort !== $key) return '<span class="sort-arrow sort-arrow--idle">↕</span>';
    return $dir === 'asc'
        ? '<span class="sort-arrow">▲</span>'
        : '<span class="sort-arrow">▼</span>';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id     = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $me     = ny_current_user();

    if ($id && $action === 'toggle_admin') {
        if ($me && (int)$me['id'] === $id) {
            ny_flash_set('err', 'Nemůžete si odebrat vlastní admin oprávnění.');
        } else {
            $pdo->prepare('UPDATE ny_users SET is_admin = 1 - is_admin WHERE id = ?')->execute([$id]);
            ny_flash_set('ok', 'Oprávnění bylo upraveno.');
        }
    }
    ny_redirect('users.php' . $queryString());
}

$where  = [];
$params = [];

if ($filter === 'registered') {
    $where[] = 'u.is_guest = 0';
} elseif ($filter === 'guest') {
    $where[] = 'u.is_guest = 1';
} elseif ($filter === 'admin') {
    $where[] = 'u.is_admin = 1';
}

if ($search !== '') {
    $where[] = '(u.email LIKE ? OR u.display_name LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql = 'SELECT u.*,
               (SELECT COUNT(*) FROM ny_reservations r WHERE r.user_id = u.id AND r.status = "booked") AS active_res
          FROM ny_users u';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY ' . $sortMap[$sort] . ' ' . strtoupper($dir) . ', u.id DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$counts = $pdo->query(
    'SELECT
        COUNT(*)                                                  AS total,
        SUM(CASE WHEN is_guest = 0 THEN 1 ELSE 0 END)             AS registered,
        SUM(CASE WHEN is_guest = 1 THEN 1 ELSE 0 END)             AS guest,
        SUM(CASE WHEN is_admin = 1 THEN 1 ELSE 0 END)             AS admin
     FROM ny_users'
)->fetch();

ny_admin_render_header('Uživatelé', 'users');
?>
<div class="admin-card">
    <form method="get" class="row row-wrap">
        <div class="week-nav week-nav--tight">
            <a href="<?= htmlspecialchars($queryString(['f' => null])) ?>" class="<?= $filter === 'all'        ? 'is-active' : '' ?>">Vše <small>(<?= (int)$counts['total'] ?>)</small></a>
            <a href="<?= htmlspecialchars($queryString(['f' => 'registered'])) ?>" class="<?= $filter === 'registered' ? 'is-active' : '' ?>">Registrovaní <small>(<?= (int)$counts['registered'] ?>)</small></a>
            <a href="<?= htmlspecialchars($queryString(['f' => 'guest'])) ?>"      class="<?= $filter === 'guest'      ? 'is-active' : '' ?>">Hosté <small>(<?= (int)$counts['guest'] ?>)</small></a>
            <a href="<?= htmlspecialchars($queryString(['f' => 'admin'])) ?>"      class="<?= $filter === 'admin'      ? 'is-active' : '' ?>">Admini <small>(<?= (int)$counts['admin'] ?>)</small></a>
        </div>
        <input type="hidden" name="f" value="<?= e($filter) ?>">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Hledat jméno, e-mail nebo telefon…" class="filter-input" autofocus>
        <button class="btn btn-secondary" type="submit">Hledat</button>
        <?php if ($search !== '' || $filter !== 'all'): ?>
            <a class="btn btn-ghost" href="users.php">Vymazat</a>
        <?php endif; ?>
    </form>
</div>

<div class="admin-card">
    <h2>Uživatelé <span class="hint count-tag">(<?= count($users) ?>)</span></h2>
    <?php if (!$users): ?>
        <p class="hint">Nenalezeny žádné uživatele.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl admin-tbl--sortable">
            <thead><tr>
                <th><a href="<?= $sortLink('name') ?>">Jméno <?= $sortArrow('name') ?></a></th>
                <th><a href="<?= $sortLink('email') ?>">E-mail <?= $sortArrow('email') ?></a></th>
                <th><a href="<?= $sortLink('phone') ?>">Telefon <?= $sortArrow('phone') ?></a></th>
                <th><a href="<?= $sortLink('created') ?>">Registrace <?= $sortArrow('created') ?></a></th>
                <th><a href="<?= $sortLink('type') ?>">Typ <?= $sortArrow('type') ?></a></th>
                <th><a href="<?= $sortLink('res') ?>">Rezervací <?= $sortArrow('res') ?></a></th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $u):
                $created = $u['created_at'] ? new DateTimeImmutable($u['created_at']) : null; ?>
                <tr>
                    <td><?= e($u['display_name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e((string)($u['phone'] ?? '—')) ?></td>
                    <td><?= $created ? e($created->format('j. n. Y')) : '—' ?></td>
                    <td>
                        <?php if ((int)$u['is_admin'] === 1): ?>
                            <span class="badge badge-success">admin</span>
                        <?php elseif ((int)$u['is_guest'] === 1): ?>
                            <span class="badge">host</span>
                        <?php else: ?>
                            <span class="badge">uživatel</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int)$u['active_res'] ?></td>
                    <td class="actions">
                        <?php if ((int)$u['is_guest'] === 0): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="action" value="toggle_admin">
                                <button class="btn btn-secondary" type="submit">
                                    <?= (int)$u['is_admin'] === 1 ? 'Odebrat admin' : 'Nastavit adminem' ?>
                                </button>
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
