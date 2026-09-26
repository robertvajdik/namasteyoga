<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

$avatarDir = __DIR__ . '/../assets/avatars';
if (!is_dir($avatarDir)) {
    @mkdir($avatarDir, 0755, true);
}

function ny_admin_store_avatar(array $file, string $existing = ''): string {
    global $avatarDir;
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return $existing;
    }
    $mime = @mime_content_type($file['tmp_name']) ?: '';
    $exts = ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($exts[$mime])) {
        throw new RuntimeException('Nepodporovaný typ obrázku (JPG/PNG/WEBP).');
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException('Obrázek je příliš velký (max 4 MB).');
    }
    $name = bin2hex(random_bytes(6)) . '.' . $exts[$mime];
    $dest = $avatarDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Obrázek se nepodařilo uložit.');
    }
    if ($existing !== '') {
        @unlink($avatarDir . '/' . basename($existing));
    }
    return $name;
}

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
    $id = (int)($_POST['id'] ?? 0);
    $me = ny_current_user();

    if ($action === 'toggle_admin' && $id) {
        if ($me && (int)$me['id'] === $id) {
            ny_flash_set('err', 'Nemůžete si odebrat vlastní admin oprávnění.');
        } else {
            $pdo->prepare('UPDATE ny_users SET is_admin = 1 - is_admin WHERE id = ?')->execute([$id]);
            ny_flash_set('ok', 'Oprávnění bylo upraveno.');
        }
        ny_redirect('users.php' . $queryString());
    }

    if ($action === 'delete' && $id) {
        if ($me && (int)$me['id'] === $id) {
            ny_flash_set('err', 'Nemůžete smazat vlastní účet.');
        } else {
            $row = $pdo->prepare('SELECT avatar FROM ny_users WHERE id = ?');
            $row->execute([$id]);
            if ($av = $row->fetchColumn()) {
                @unlink($avatarDir . '/' . basename((string)$av));
            }
            $pdo->prepare('DELETE FROM ny_users WHERE id = ?')->execute([$id]);
            ny_flash_set('ok', 'Uživatel byl smazán.');
        }
        ny_redirect('users.php' . $queryString());
    }

    if ($action === 'remove_avatar' && $id) {
        $row = $pdo->prepare('SELECT avatar FROM ny_users WHERE id = ?');
        $row->execute([$id]);
        if ($av = $row->fetchColumn()) {
            @unlink($avatarDir . '/' . basename((string)$av));
        }
        $pdo->prepare('UPDATE ny_users SET avatar = "" WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Profilový obrázek byl odstraněn.');
        ny_redirect('users.php?action=edit&id=' . $id);
    }

    // Create / update from form
    $name     = trim((string)($_POST['name'] ?? ''));
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone    = trim((string)($_POST['phone'] ?? '')) ?: null;
    $isAdmin  = isset($_POST['is_admin']) ? 1 : 0;
    $password = (string)($_POST['password'] ?? '');

    $existingAvatar = '';
    if ($id) {
        $row = $pdo->prepare('SELECT avatar FROM ny_users WHERE id = ?');
        $row->execute([$id]);
        $existingAvatar = (string)($row->fetchColumn() ?: '');
    }
    try {
        $avatar = ny_admin_store_avatar($_FILES['avatar'] ?? [], $existingAvatar);
    } catch (Throwable $e) {
        ny_flash_set('err', $e->getMessage());
        ny_redirect('users.php?action=' . ($id ? 'edit&id=' . $id : 'new'));
    }

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ny_flash_set('err', 'Vyplňte jméno a platný e-mail.');
    } else {
        $dupe = $pdo->prepare('SELECT id FROM ny_users WHERE email = ? AND id <> ? LIMIT 1');
        $dupe->execute([$email, $id]);
        if ($dupe->fetchColumn()) {
            ny_flash_set('err', 'Uživatel s tímto e-mailem už existuje.');
        } elseif ($id) {
            if ($password !== '' && strlen($password) < 8) {
                ny_flash_set('err', 'Heslo musí mít alespoň 8 znaků.');
            } else {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare(
                        'UPDATE ny_users SET display_name = ?, email = ?, phone = ?, avatar = ?, is_admin = ?, is_guest = 0, password_hash = ? WHERE id = ?'
                    )->execute([$name, $email, $phone, $avatar, $isAdmin, $hash, $id]);
                } else {
                    $pdo->prepare(
                        'UPDATE ny_users SET display_name = ?, email = ?, phone = ?, avatar = ?, is_admin = ? WHERE id = ?'
                    )->execute([$name, $email, $phone, $avatar, $isAdmin, $id]);
                }
                ny_flash_set('ok', 'Uživatel byl uložen.');
                ny_redirect('users.php' . $queryString());
            }
        } else {
            if (strlen($password) < 8) {
                ny_flash_set('err', 'Zadejte heslo (alespoň 8 znaků).');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare(
                    'INSERT INTO ny_users (email, display_name, phone, avatar, password_hash, is_guest, is_admin)
                     VALUES (?, ?, ?, ?, ?, 0, ?)'
                )->execute([$email, $name, $phone, $avatar, $hash, $isAdmin]);
                ny_flash_set('ok', 'Uživatel byl vytvořen.');
                ny_redirect('users.php' . $queryString());
            }
        }
    }
}

$editing = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare('SELECT * FROM ny_users WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

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

$sqlBase = 'SELECT u.*,
               (SELECT COUNT(*) FROM ny_reservations r WHERE r.user_id = u.id AND r.status = "booked") AS active_res
          FROM ny_users u';
if ($where) $sqlBase .= ' WHERE ' . implode(' AND ', $where);
$sqlBase .= ' ORDER BY ' . $sortMap[$sort] . ' ' . strtoupper($dir) . ', u.id DESC';

if ($action === 'export_csv') {
    $stmt = $pdo->prepare($sqlBase);
    $stmt->execute($params);

    $filenameParts = ['uzivatele'];
    if ($filter !== 'all') $filenameParts[] = $filter;
    if ($search !== '')    $filenameParts[] = preg_replace('/[^A-Za-z0-9_-]+/', '_', $search);
    $filenameParts[] = date('Y-m-d');
    $filename = implode('-', array_filter($filenameParts)) . '.csv';

    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Jméno', 'E-mail', 'Telefon', 'Typ', 'Admin', 'Registrace', 'Aktivní rezervace'], ';');
    while ($u = $stmt->fetch()) {
        $type = (int)$u['is_admin'] === 1 ? 'admin' : ((int)$u['is_guest'] === 1 ? 'host' : 'uživatel');
        fputcsv($out, [
            (int)$u['id'],
            (string)$u['display_name'],
            (string)$u['email'],
            (string)($u['phone'] ?? ''),
            $type,
            (int)$u['is_admin'] === 1 ? 'ano' : 'ne',
            $u['created_at'] ? (new DateTimeImmutable((string)$u['created_at']))->format('Y-m-d H:i') : '',
            (int)$u['active_res'],
        ], ';');
    }
    fclose($out);
    exit;
}

$stmt = $pdo->prepare($sqlBase . ' LIMIT 300');
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

$me = ny_current_user();

ny_admin_render_header('Uživatelé', 'users');
?>

<?php if ($editing || $isNew):
    $u = $editing ?: ['id' => 0, 'display_name' => '', 'email' => '', 'phone' => '', 'avatar' => '', 'is_admin' => 0, 'is_guest' => 0];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit uživatele' : 'Nový uživatel' ?></h2>
    <form method="post" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
        <div class="admin-form-row">
            <label>Jméno
                <input type="text" name="name" value="<?= e((string)$u['display_name']) ?>" required>
            </label>
            <label>E-mail
                <input type="email" name="email" value="<?= e((string)$u['email']) ?>" required>
            </label>
            <label>Telefon
                <input type="tel" name="phone" value="<?= e((string)($u['phone'] ?? '')) ?>">
            </label>
            <label>Heslo <?= $editing ? '(nechte prázdné pro zachování)' : '(min. 8 znaků)' ?>
                <input type="password" name="password" autocomplete="new-password" <?= $editing ? '' : 'required minlength="8"' ?>>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Profilový obrázek<?= $editing && !empty($u['avatar']) ? ' (nechte prázdné pro zachování)' : '' ?>
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                <small class="hint">JPG/PNG/WEBP, max 4 MB.</small>
            </label>
            <?php if (!empty($u['avatar'])): ?>
                <div class="teacher-photo-preview">
                    <img src="../assets/avatars/<?= e(rawurlencode($u['avatar'])) ?>" alt="">
                    <form method="post" class="inline" onsubmit="return confirm('Odstranit obrázek?');">
                        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="remove_avatar">
                        <button class="btn btn-ghost btn-sm" type="submit">Odstranit obrázek</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <div class="admin-form-row full">
            <label class="checkbox-inline">
                <input type="checkbox" name="is_admin" value="1" <?= (int)$u['is_admin'] === 1 ? 'checked' : '' ?>
                    <?= $me && (int)$me['id'] === (int)$u['id'] ? 'disabled' : '' ?>>
                Administrátor (přístup do této sekce)
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="users.php<?= e($queryString()) ?>">Zrušit</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <form method="get" class="row row-wrap">
        <div class="week-nav week-nav--tight">
            <a href="<?= htmlspecialchars($queryString(['f' => null])) ?>" class="<?= $filter === 'all'        ? 'is-active' : '' ?>">Vše <small>(<?= (int)$counts['total'] ?>)</small></a>
            <a href="<?= htmlspecialchars($queryString(['f' => 'registered'])) ?>" class="<?= $filter === 'registered' ? 'is-active' : '' ?>">Registrovaní <small>(<?= (int)$counts['registered'] ?>)</small></a>
            <a href="<?= htmlspecialchars($queryString(['f' => 'guest'])) ?>"      class="<?= $filter === 'guest'      ? 'is-active' : '' ?>">Hosté <small>(<?= (int)$counts['guest'] ?>)</small></a>
            <a href="<?= htmlspecialchars($queryString(['f' => 'admin'])) ?>"      class="<?= $filter === 'admin'      ? 'is-active' : '' ?>">Admini <small>(<?= (int)$counts['admin'] ?>)</small></a>
        </div>
        <input type="hidden" name="f" value="<?= e($filter) ?>">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Hledat jméno, e-mail nebo telefon…" class="filter-input">
        <button class="btn btn-secondary" type="submit">Hledat</button>
        <?php if ($search !== '' || $filter !== 'all'): ?>
            <a class="btn btn-ghost" href="users.php">Vymazat</a>
        <?php endif; ?>
    </form>
</div>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Uživatelé <span class="hint count-tag">(<?= count($users) ?>)</span></h2>
        <a class="btn btn-primary" href="?action=new">+ Nový uživatel</a>
    </div>
    <?php if (!$users): ?>
        <p class="hint">Nenalezeny žádné uživatele.</p>
    <?php else: ?>
        <form method="get" class="mobile-sort" aria-label="Řazení">
            <input type="hidden" name="f" value="<?= e($filter) ?>">
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
            <label class="mobile-sort-label" for="mobile-sort">Řadit dle</label>
            <select id="mobile-sort" name="s" onchange="this.form.submit()">
                <option value="created" <?= $sort === 'created' ? 'selected' : '' ?>>Data registrace</option>
                <option value="name"    <?= $sort === 'name'    ? 'selected' : '' ?>>Jména</option>
                <option value="email"   <?= $sort === 'email'   ? 'selected' : '' ?>>E-mailu</option>
                <option value="phone"   <?= $sort === 'phone'   ? 'selected' : '' ?>>Telefonu</option>
                <option value="type"    <?= $sort === 'type'    ? 'selected' : '' ?>>Typu (admin první)</option>
                <option value="res"     <?= $sort === 'res'     ? 'selected' : '' ?>>Počtu rezervací</option>
            </select>
            <select name="d" onchange="this.form.submit()" aria-label="Směr řazení">
                <option value="desc" <?= $dir === 'desc' ? 'selected' : '' ?>>↓ sestupně</option>
                <option value="asc"  <?= $dir === 'asc'  ? 'selected' : '' ?>>↑ vzestupně</option>
            </select>
            <noscript><button class="btn btn-secondary" type="submit">Seřadit</button></noscript>
        </form>
        <div class="tbl-wrap">
        <table class="admin-tbl admin-tbl--sortable admin-tbl--users">
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
                $created = $u['created_at'] ? new DateTimeImmutable($u['created_at']) : null;
                $isSelf  = $me && (int)$me['id'] === (int)$u['id'];
            ?>
                <tr>
                    <td data-label="Jméno">
                        <div class="user-cell">
                            <?php if (!empty($u['avatar'])): ?>
                                <span class="user-avatar user-avatar--md"><img src="../assets/avatars/<?= e(rawurlencode($u['avatar'])) ?>" alt=""></span>
                            <?php else: ?>
                                <span class="user-avatar user-avatar--md"><?= e(mb_strtoupper(mb_substr((string)$u['display_name'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <span class="user-cell-name"><strong><?= e($u['display_name']) ?></strong><?php if ($isSelf): ?> <span class="hint">(vy)</span><?php endif; ?></span>
                        </div>
                    </td>
                    <td data-label="E-mail"><?= e($u['email']) ?></td>
                    <td data-label="Telefon"><?= e((string)($u['phone'] ?? '—')) ?></td>
                    <td data-label="Registrace"><?= $created ? e($created->format('j. n. Y')) : '—' ?></td>
                    <td data-label="Typ">
                        <?php if ((int)$u['is_admin'] === 1): ?>
                            <span class="badge badge-success">admin</span>
                        <?php elseif ((int)$u['is_guest'] === 1): ?>
                            <span class="badge">host</span>
                        <?php else: ?>
                            <span class="badge">uživatel</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Rezervací"><?= (int)$u['active_res'] ?></td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&id=<?= (int)$u['id'] ?><?= e(str_replace('?', '&', $queryString())) ?>">Upravit</a>
                        <?php if ((int)$u['is_guest'] === 0 && !$isSelf): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="action" value="toggle_admin">
                                <button class="btn btn-secondary" type="submit" title="<?= (int)$u['is_admin'] === 1 ? 'Odebrat admin oprávnění' : 'Nastavit jako admin' ?>">
                                    <?= (int)$u['is_admin'] === 1 ? 'Odebrat admin' : '+ Admin' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$isSelf): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat uživatele <?= e(addslashes($u['display_name'])) ?>? Smažou se i jeho rezervace.');">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button class="btn btn-danger" type="submit">Smazat</button>
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
