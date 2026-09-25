<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$user = ny_current_user();
if (!$user) {
    ny_flash_set('err', 'Přihlaste se prosím.');
    ny_redirect('login.php');
}

ny_ensure_content_tables();
$pdo = ny_db();

$avatarDir = __DIR__ . '/assets/avatars';
if (!is_dir($avatarDir)) {
    @mkdir($avatarDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $act = (string)($_POST['action'] ?? '');
    try {
        if ($act === 'remove_avatar' && !empty($user['avatar'])) {
            @unlink($avatarDir . '/' . basename((string)$user['avatar']));
            $pdo->prepare('UPDATE ny_users SET avatar = "" WHERE id = ?')->execute([$user['id']]);
            ny_flash_set('ok', 'Profilový obrázek byl odstraněn.');
            ny_redirect('my.php');
        }
        if ($act === 'upload_avatar' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['avatar'];
            $mime = @mime_content_type($f['tmp_name']) ?: '';
            $exts = ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($exts[$mime])) {
                throw new RuntimeException('Nepodporovaný typ obrázku. Použijte JPG, PNG nebo WEBP.');
            }
            if ($f['size'] > 4 * 1024 * 1024) {
                throw new RuntimeException('Obrázek je příliš velký (max 4 MB).');
            }
            $name = bin2hex(random_bytes(6)) . '.' . $exts[$mime];
            $dest = $avatarDir . '/' . $name;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                throw new RuntimeException('Obrázek se nepodařilo uložit.');
            }
            if (!empty($user['avatar'])) {
                @unlink($avatarDir . '/' . basename((string)$user['avatar']));
            }
            $pdo->prepare('UPDATE ny_users SET avatar = ? WHERE id = ?')->execute([$name, $user['id']]);
            ny_flash_set('ok', 'Profilový obrázek byl nahrán.');
            ny_redirect('my.php');
        }
    } catch (Throwable $e) {
        ny_flash_set('err', $e->getMessage());
        ny_redirect('my.php');
    }
}

// Refresh (avatar may have changed above; but we redirect anyway, this is defensive).
$user = ny_current_user();
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

$upcomingStmt = $pdo->prepare(
    "SELECT r.*, c.name, c.teacher, c.room, c.start_time, c.end_time, c.day_of_week
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id
      WHERE r.user_id = ? AND r.status = 'booked' AND r.class_date >= ?
      ORDER BY r.class_date, c.start_time"
);
$upcomingStmt->execute([$user['id'], $today]);
$upcoming = $upcomingStmt->fetchAll();

$historyStmt = $pdo->prepare(
    "SELECT r.*, c.name, c.teacher, c.start_time
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id
      WHERE r.user_id = ? AND (r.class_date < ? OR r.status = 'cancelled')
      ORDER BY r.class_date DESC, c.start_time DESC
      LIMIT 40"
);
$historyStmt->execute([$user['id'], $today]);
$history = $historyStmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT
        SUM(status = 'booked'   AND class_date <  ?) AS attended,
        SUM(status = 'booked'   AND class_date >= ?) AS upcoming,
        SUM(status = 'cancelled')                    AS cancelled,
        MIN(created_at)                              AS first_at
       FROM ny_reservations
      WHERE user_id = ?"
);
$stmt->execute([$today, $today, $user['id']]);
$myStats = $stmt->fetch() ?: ['attended' => 0, 'upcoming' => 0, 'cancelled' => 0, 'first_at' => null];

$stmt = $pdo->prepare(
    "SELECT c.name, COUNT(*) AS n
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id
      WHERE r.user_id = ? AND r.status = 'booked'
      GROUP BY c.id, c.name
      ORDER BY n DESC
      LIMIT 1"
);
$stmt->execute([$user['id']]);
$favClass = $stmt->fetch() ?: null;

$since = null;
if (!empty($user['created_at'])) {
    $since = new DateTimeImmutable($user['created_at']);
} elseif (!empty($myStats['first_at'])) {
    $since = new DateTimeImmutable($myStats['first_at']);
}

$daysShort = [1 => 'PO', 2 => 'ÚT', 3 => 'ST', 4 => 'ČT', 5 => 'PÁ', 6 => 'SO', 7 => 'NE'];

ny_render_header('Moje rezervace', 'my');
?>
<section class="section-title-block">
    <div class="eyebrow">Můj účet</div>
    <h1 class="page-title">Moje rezervace</h1>
    <p class="page-lead">Přehled nadcházejících lekcí a historie vašich návštěv.</p>
</section>

<div class="profile-card">
    <?php if (!empty($user['avatar'])): ?>
        <span class="user-avatar user-avatar--lg"><img src="assets/avatars/<?= e(rawurlencode($user['avatar'])) ?>" alt=""></span>
    <?php else: ?>
        <span class="user-avatar user-avatar--lg"><?= e(mb_strtoupper(mb_substr((string)$user['display_name'], 0, 1))) ?></span>
    <?php endif; ?>
    <div class="profile-card-info">
        <h2><?= e($user['display_name']) ?></h2>
        <div class="muted"><?= e($user['email']) ?><?php if (!empty($user['phone'])): ?> · <?= e($user['phone']) ?><?php endif; ?></div>
    </div>
    <form method="post" enctype="multipart/form-data" class="profile-card-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="upload_avatar">
        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
        <button class="btn btn-secondary btn-sm" type="submit"><?= !empty($user['avatar']) ? 'Změnit obrázek' : 'Nahrát obrázek' ?></button>
    </form>
    <?php if (!empty($user['avatar'])): ?>
        <form method="post" onsubmit="return confirm('Odstranit profilový obrázek?');" class="inline">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="action" value="remove_avatar">
            <button class="btn btn-ghost btn-sm" type="submit">Odstranit</button>
        </form>
    <?php endif; ?>
</div>

<div class="my-stats">
    <div class="my-stat"><div class="num"><?= (int)$myStats['attended'] ?></div><div class="lbl">Navštívených lekcí</div></div>
    <div class="my-stat"><div class="num"><?= (int)$myStats['upcoming'] ?></div><div class="lbl">Nadcházejících</div></div>
    <div class="my-stat"><div class="num"><?= (int)$myStats['cancelled'] ?></div><div class="lbl">Zrušených</div></div>
    <div class="my-stat">
        <div class="num num-text"><?= $favClass ? e($favClass['name']) : '—' ?></div>
        <div class="lbl">Oblíbená lekce<?= $favClass ? ' (' . (int)$favClass['n'] . '×)' : '' ?></div>
    </div>
    <div class="my-stat">
        <div class="num num-text"><?= $since ? e($since->format('n / Y')) : '—' ?></div>
        <div class="lbl">Členem od</div>
    </div>
</div>

<h2 class="section-h">Nadcházející</h2>
<?php if (!$upcoming): ?>
    <div class="card text-center empty-state">
        <div class="empty-state-title">Zatím nemáte žádnou rezervaci</div>
        <p class="text-muted empty-state-hint">Vyberte si lekci v rozvrhu.</p>
        <a class="btn btn-primary" href="rezervace.php">Rezervovat lekci</a>
    </div>
<?php else: ?>
    <?php foreach ($upcoming as $r):
        $d = new DateTimeImmutable($r['class_date']);
        $dow = (int)$d->format('N');
    ?>
        <div class="booking-row">
            <div class="date-block">
                <div class="dow"><?= e($daysShort[$dow]) ?></div>
                <div class="num"><?= e($d->format('j. n.')) ?></div>
            </div>
            <div class="info">
                <div class="name"><?= e($r['name']) ?></div>
                <div class="meta">
                    <?= e(substr($r['start_time'], 0, 5)) ?> – <?= e(substr($r['end_time'], 0, 5)) ?>
                    · <?= e($r['teacher']) ?><?php if ($r['room']): ?> · <?= e($r['room']) ?><?php endif; ?>
                </div>
            </div>
            <span class="badge badge-success"><span class="dot"></span>Potvrzeno</span>
            <form method="post" action="cancel.php" class="inline">
                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                <input type="hidden" name="class_id" value="<?= (int)$r['class_id'] ?>">
                <input type="hidden" name="class_date" value="<?= e($r['class_date']) ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Zrušit</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2 class="section-h section-h-gap">Historie</h2>
<?php if (!$history): ?>
    <p class="hint">Zatím prázdné.</p>
<?php else: ?>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Datum</th><th>Lekce</th><th>Lektor</th><th>Stav</th></tr></thead>
            <tbody>
            <?php foreach ($history as $r):
                $d = new DateTimeImmutable($r['class_date']); ?>
                <tr>
                    <td data-label="Datum"><?= e($d->format('j. n. Y')) ?></td>
                    <td data-label="Lekce"><?= e($r['name']) ?></td>
                    <td data-label="Lektor"><?= e($r['teacher']) ?></td>
                    <td data-label="Stav">
                        <?php if ($r['status'] === 'cancelled'): ?>
                            <span class="badge badge-danger">zrušeno</span>
                        <?php else: ?>
                            <span class="badge">proběhlo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php ny_render_footer();
