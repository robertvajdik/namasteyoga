<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$user = ny_current_user();
if (!$user) {
    ny_flash_set('err', t('my.flash.login_required'));
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
        if ($act === 'update_profile') {
            $name  = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $phone = trim((string)($_POST['phone'] ?? ''));
            if ($name === '') {
                throw new RuntimeException(t('my.err.name_required'));
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(t('my.err.email_invalid'));
            }
            $dupe = $pdo->prepare('SELECT id FROM ny_users WHERE email = ? AND id <> ? LIMIT 1');
            $dupe->execute([$email, $user['id']]);
            if ($dupe->fetch()) {
                throw new RuntimeException(t('my.err.email_taken'));
            }
            $pdo->prepare('UPDATE ny_users SET display_name = ?, email = ?, phone = ? WHERE id = ?')
                ->execute([$name, $email, $phone ?: null, $user['id']]);
            ny_flash_set('ok', t('my.flash.profile_saved'));
            ny_redirect('myprofile.php');
        }
        if ($act === 'change_password') {
            $current = (string)($_POST['current_password'] ?? '');
            $new1    = (string)($_POST['new_password'] ?? '');
            $new2    = (string)($_POST['new_password2'] ?? '');
            if (empty($user['password_hash']) || !ny_verify_password($current, (string)$user['password_hash'])) {
                throw new RuntimeException(t('my.err.password_current'));
            }
            if (strlen($new1) < 8) {
                throw new RuntimeException(t('my.err.password_short'));
            }
            if ($new1 !== $new2) {
                throw new RuntimeException(t('my.err.password_mismatch'));
            }
            $hash = password_hash($new1, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE ny_users SET password_hash = ? WHERE id = ?')
                ->execute([$hash, $user['id']]);
            ny_flash_set('ok', t('my.flash.password_changed'));
            ny_redirect('myprofile.php');
        }
        if ($act === 'remove_avatar' && !empty($user['avatar'])) {
            @unlink($avatarDir . '/' . basename((string)$user['avatar']));
            $pdo->prepare('UPDATE ny_users SET avatar = "" WHERE id = ?')->execute([$user['id']]);
            ny_flash_set('ok', t('my.flash.avatar_removed'));
            ny_redirect('myprofile.php');
        }
        if ($act === 'newsletter_toggle') {
            $wants = !empty($_POST['subscribe']);
            $email = (string)($user['email'] ?? '');
            if ($wants) {
                ny_newsletter_subscribe($email, (string)$user['display_name'], 'profile');
                ny_flash_set('ok', t('my.flash.newsletter_on'));
            } else {
                ny_newsletter_unsubscribe_by_email($email);
                ny_flash_set('ok', t('my.flash.newsletter_off'));
            }
            ny_redirect('myprofile.php');
        }
        if ($act === 'request_topup') {
            $amount = (int)($_POST['amount'] ?? 0);
            $note   = trim((string)($_POST['note'] ?? ''));
            if ($amount < 100 || $amount > 20000) {
                throw new RuntimeException(t('my.credits.err.amount_range'));
            }
            ny_credit_topup_request((int)$user['id'], $amount, $note);
            ny_flash_set('ok', t('my.credits.flash.requested'));
            ny_redirect('myprofile.php#credits');
        }
        if ($act === 'redeem_voucher') {
            // Throttle guessing: at most 5 failed codes per 15 minutes per session.
            $fails = array_filter(
                (array)($_SESSION['voucher_fails'] ?? []),
                fn($ts) => $ts > time() - 900
            );
            if (count($fails) >= 5) {
                throw new RuntimeException(t('my.credits.voucher.err.throttle'));
            }
            try {
                $credited = ny_credit_redeem_voucher((int)$user['id'], (string)($_POST['code'] ?? ''));
            } catch (RuntimeException $e) {
                $fails[] = time();
                $_SESSION['voucher_fails'] = array_values($fails);
                throw $e;
            }
            unset($_SESSION['voucher_fails']);
            ny_flash_set('ok', sprintf(t('my.credits.voucher.flash.ok'), number_format($credited, 0, ',', ' ')));
            ny_redirect('myprofile.php#credits');
        }
        if ($act === 'upload_avatar' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['avatar'];
            $mime = @mime_content_type($f['tmp_name']) ?: '';
            $exts = ['image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($exts[$mime])) {
                throw new RuntimeException(t('my.err.avatar_type'));
            }
            if ($f['size'] > 4 * 1024 * 1024) {
                throw new RuntimeException(t('my.err.avatar_too_big'));
            }
            $name = bin2hex(random_bytes(6)) . '.' . $exts[$mime];
            $dest = $avatarDir . '/' . $name;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                throw new RuntimeException(t('my.err.avatar_save'));
            }
            if (!empty($user['avatar'])) {
                @unlink($avatarDir . '/' . basename((string)$user['avatar']));
            }
            $pdo->prepare('UPDATE ny_users SET avatar = ? WHERE id = ?')->execute([$name, $user['id']]);
            ny_flash_set('ok', t('my.flash.avatar_uploaded'));
            ny_redirect('myprofile.php');
        }
    } catch (Throwable $e) {
        ny_flash_set('err', $e->getMessage());
        ny_redirect('myprofile.php');
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

// Last 12 months of attendance (status=booked, class_date in [first-of-month-11-months-ago, today]).
$twelveStart = (new DateTimeImmutable('first day of this month'))->modify('-11 months')->format('Y-m-d');
$monthlyStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(class_date, '%Y-%m') AS ym, COUNT(*) AS n
       FROM ny_reservations
      WHERE user_id = ? AND status = 'booked'
        AND class_date >= ? AND class_date <= ?
      GROUP BY ym"
);
$monthlyStmt->execute([$user['id'], $twelveStart, $today]);
$monthlyRaw = [];
foreach ($monthlyStmt->fetchAll() as $r) $monthlyRaw[$r['ym']] = (int)$r['n'];
$monthly = [];
$cursor = new DateTimeImmutable($twelveStart);
for ($i = 0; $i < 12; $i++) {
    $key = $cursor->format('Y-m');
    $monthly[] = [
        'ym'    => $key,
        'label' => $cursor->format('n/y'),
        'count' => $monthlyRaw[$key] ?? 0,
    ];
    $cursor = $cursor->modify('+1 month');
}
$monthlyMax   = max(array_column($monthly, 'count'));
$monthlyTotal = array_sum(array_column($monthly, 'count'));

// Top 5 classes and teachers by attendance count (all time, booked).
$topClassesStmt = $pdo->prepare(
    "SELECT c.name, COUNT(*) AS n
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id
      WHERE r.user_id = ? AND r.status = 'booked'
      GROUP BY c.id, c.name
      ORDER BY n DESC, c.name
      LIMIT 5"
);
$topClassesStmt->execute([$user['id']]);
$topClasses = $topClassesStmt->fetchAll();

$topTeachersStmt = $pdo->prepare(
    "SELECT c.teacher, COUNT(*) AS n
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id
      WHERE r.user_id = ? AND r.status = 'booked' AND c.teacher <> ''
      GROUP BY c.teacher
      ORDER BY n DESC, c.teacher
      LIMIT 5"
);
$topTeachersStmt->execute([$user['id']]);
$topTeachers = $topTeachersStmt->fetchAll();

$attendedTotal  = (int)$myStats['attended'];
$cancelledTotal = (int)$myStats['cancelled'];
$bookedTotal    = $attendedTotal + (int)$myStats['upcoming'] + $cancelledTotal;
$cancelRate     = $bookedTotal > 0 ? (int)round(($cancelledTotal / $bookedTotal) * 100) : 0;
if ($since) {
    $diff = (new DateTimeImmutable('today'))->diff($since);
    $monthsActive = max(1, $diff->m + $diff->y * 12 + 1);
} else {
    $monthsActive = 1;
}
$avgPerMonth = round($attendedTotal / $monthsActive, 1);

$nextClass = $upcoming[0] ?? null;

$last30Stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM ny_reservations
      WHERE user_id = ? AND status = 'booked'
        AND class_date >= DATE_SUB(?, INTERVAL 30 DAY)
        AND class_date < ?"
);
$last30Stmt->execute([$user['id'], $today, $today]);
$last30Count = (int)$last30Stmt->fetchColumn();

$csPlural = static function (int $n, string $one, string $few, string $many): string {
    if ($n === 1) return $one;
    if ($n >= 2 && $n <= 4) return $few;
    return $many;
};
if ($since) {
    $sinceDiff   = (new DateTimeImmutable('today'))->diff($since);
    $totalMonths = $sinceDiff->y * 12 + $sinceDiff->m;
    if ($totalMonths < 1) {
        $memberForNum   = 0;
        $memberForLabel = t('my.member_for.new');
    } elseif ($totalMonths < 12) {
        $memberForNum   = $totalMonths;
        $memberForLabel = $csPlural($totalMonths, t('my.member_for.month.one'), t('my.member_for.month.few'), t('my.member_for.month.many'));
    } else {
        $memberForNum   = $sinceDiff->y;
        $memberForLabel = $csPlural($sinceDiff->y, t('my.member_for.year.one'), t('my.member_for.year.few'), t('my.member_for.year.many'));
    }
} else {
    $memberForNum   = 0;
    $memberForLabel = t('my.member_for.new');
}

$creditBalance    = ny_user_balance_kc((int)$user['id']);
$creditLedger     = ny_credit_ledger_recent((int)$user['id'], 10);
$pendingTopupStmt = $pdo->prepare(
    "SELECT amount_kc, requested_at FROM ny_credit_topups
      WHERE user_id = ? AND status = 'pending'
      ORDER BY requested_at DESC"
);
$pendingTopupStmt->execute([$user['id']]);
$pendingTopups = $pendingTopupStmt->fetchAll();

$iban        = trim((string)ny_setting('bank_iban', ''));
$bankAccount = trim((string)ny_setting('bank_account_number', ''));

$isSubscribed = ny_newsletter_is_subscribed((string)$user['email']);

$daysShort = [
    1 => t('my.dow.1'),
    2 => t('my.dow.2'),
    3 => t('my.dow.3'),
    4 => t('my.dow.4'),
    5 => t('my.dow.5'),
    6 => t('my.dow.6'),
    7 => t('my.dow.7'),
];

ny_render_header(t('my.title'), 'my', ['description' => t('my.meta.description'), 'noindex' => true]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('my.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('my.title')) ?></h1>
    <p class="page-lead"><?= e(t('my.lead')) ?></p>
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
        <button class="btn btn-secondary btn-sm" type="submit"><?= e(!empty($user['avatar']) ? t('my.avatar.change') : t('my.avatar.upload')) ?></button>
    </form>
    <?php if (!empty($user['avatar'])): ?>
        <form method="post" onsubmit="return confirm('<?= e(t('my.avatar.remove_confirm')) ?>');" class="inline">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="action" value="remove_avatar">
            <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('my.avatar.remove')) ?></button>
        </form>
    <?php endif; ?>
</div>

<?php if ($nextClass):
    $nd    = new DateTimeImmutable((string)$nextClass['class_date']);
    $today0 = new DateTimeImmutable('today');
    $daysTo = (int)$today0->diff($nd)->days;
    $dow   = (int)$nd->format('N');
    if ($daysTo === 0)      $when = t('my.next.today');
    elseif ($daysTo === 1)  $when = t('my.next.tomorrow');
    else                    $when = sprintf(t('my.next.in_days'), $daysTo);
?>
<section class="my-next-class">
    <div class="my-next-class-date">
        <div class="dow"><?= e($daysShort[$dow]) ?></div>
        <div class="num"><?= e($nd->format('j. n.')) ?></div>
    </div>
    <div class="my-next-class-info">
        <div class="my-next-class-eyebrow"><?= e(t('my.next.h')) ?> · <?= e($when) ?></div>
        <h2 class="my-next-class-name"><?= e((string)$nextClass['name']) ?></h2>
        <div class="my-next-class-meta">
            <strong><?= e(substr((string)$nextClass['start_time'], 0, 5)) ?></strong> – <?= e(substr((string)$nextClass['end_time'], 0, 5)) ?>
            · <?= e((string)$nextClass['teacher']) ?>
            <?php if (!empty($nextClass['room'])): ?> · <?= e((string)$nextClass['room']) ?><?php endif; ?>
        </div>
    </div>
    <a class="btn btn-secondary btn-sm" href="#upcoming"><?= e(t('my.next.manage')) ?></a>
</section>
<?php endif; ?>

<div class="my-stats">
    <div class="my-stat my-stat--credit">
        <div class="num"><?= number_format($creditBalance, 0, ',', ' ') ?><span class="unit"> <?= e(t('my.credits.unit')) ?></span></div>
        <div class="lbl"><?= e(t('my.stat.credit')) ?></div>
        <?php if ($pendingTopups):
            $sumPending = array_sum(array_map(fn($t) => (int)$t['amount_kc'], $pendingTopups)); ?>
            <div class="my-stat-sub"><?= sprintf(e(t('my.stat.credit.pending')), (int)$sumPending) ?></div>
        <?php else: ?>
            <a class="my-stat-sub my-stat-sub--link" href="#credits"><?= e(t('my.stat.credit.topup')) ?></a>
        <?php endif; ?>
    </div>
    <div class="my-stat"><div class="num"><?= (int)$myStats['attended'] ?></div><div class="lbl"><?= e(t('my.stat.attended')) ?></div></div>
    <div class="my-stat"><div class="num"><?= $last30Count ?></div><div class="lbl"><?= e(t('my.stat.last_30')) ?></div></div>
    <div class="my-stat">
        <div class="num num-text"><?= $favClass ? e($favClass['name']) : '—' ?></div>
        <div class="lbl"><?= e(t('my.stat.favorite')) ?><?= $favClass ? ' (' . (int)$favClass['n'] . '×)' : '' ?></div>
    </div>
    <div class="my-stat">
        <?php if ($memberForNum > 0): ?>
            <div class="num"><?= $memberForNum ?><span class="unit"> <?= e($memberForLabel) ?></span></div>
        <?php else: ?>
            <div class="num num-text"><?= e($memberForLabel) ?></div>
        <?php endif; ?>
        <div class="lbl"><?= e(t('my.stat.member_for')) ?></div>
    </div>
</div>

<?php if ($attendedTotal > 0 || $cancelledTotal > 0): ?>
<section class="stats-card">
    <div class="stats-head">
        <h3><?= e(t('my.stats.h')) ?></h3>
        <div class="stats-head-meta">
            <?= sprintf(e(t('my.stats.summary')), (int)$monthlyTotal, number_format($avgPerMonth, 1, ',', ' '), (int)$cancelRate) ?>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stats-block stats-block--chart">
            <h4><?= e(t('my.stats.monthly.h')) ?></h4>
            <?php if ($monthlyMax === 0): ?>
                <p class="hint"><?= e(t('my.stats.monthly.empty')) ?></p>
            <?php else: ?>
                <div class="attend-chart" role="list">
                    <?php foreach ($monthly as $m):
                        $h = $monthlyMax > 0 ? max(5, (int)round(($m['count'] / $monthlyMax) * 20) * 5) : 0;
                    ?>
                        <div class="attend-bar" role="listitem" title="<?= e($m['label']) ?>: <?= (int)$m['count'] ?>">
                            <div class="attend-bar-num"><?= $m['count'] > 0 ? (int)$m['count'] : '' ?></div>
                            <div class="attend-bar-track">
                                <div class="attend-bar-fill pct-<?= $h ?>"></div>
                            </div>
                            <div class="attend-bar-lbl"><?= e($m['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="stats-block">
            <h4><?= e(t('my.stats.top_classes.h')) ?></h4>
            <?php if (!$topClasses): ?>
                <p class="hint"><?= e(t('my.stats.top.empty')) ?></p>
            <?php else:
                $maxN = (int)$topClasses[0]['n'];
            ?>
                <ul class="stats-list">
                    <?php foreach ($topClasses as $tc):
                        $w = $maxN > 0 ? (int)round(((int)$tc['n'] / $maxN) * 20) * 5 : 0;
                    ?>
                        <li>
                            <div class="stats-list-row">
                                <span class="stats-list-name"><?= e($tc['name']) ?></span>
                                <span class="stats-list-num"><?= (int)$tc['n'] ?>×</span>
                            </div>
                            <div class="stats-list-track"><div class="stats-list-fill pct-<?= $w ?>"></div></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="stats-block">
            <h4><?= e(t('my.stats.top_teachers.h')) ?></h4>
            <?php if (!$topTeachers): ?>
                <p class="hint"><?= e(t('my.stats.top.empty')) ?></p>
            <?php else:
                $maxN = (int)$topTeachers[0]['n'];
            ?>
                <ul class="stats-list">
                    <?php foreach ($topTeachers as $tt):
                        $w = $maxN > 0 ? (int)round(((int)$tt['n'] / $maxN) * 20) * 5 : 0;
                    ?>
                        <li>
                            <div class="stats-list-row">
                                <span class="stats-list-name"><?= e($tt['teacher']) ?></span>
                                <span class="stats-list-num"><?= (int)$tt['n'] ?>×</span>
                            </div>
                            <div class="stats-list-track"><div class="stats-list-fill pct-<?= $w ?>"></div></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="pref-card" id="credits">
    <div class="pref-card-info">
        <h3><?= e(t('my.credits.h')) ?></h3>
        <p class="muted"><?= e(t('my.credits.desc')) ?></p>
        <div class="credits-balance"><?= number_format($creditBalance, 0, ',', ' ') ?> <span class="credits-balance-unit"><?= e(t('my.credits.unit')) ?></span></div>
        <?php if ($pendingTopups): ?>
            <p class="hint">
                <?php $sumPending = array_sum(array_map(fn($t) => (int)$t['amount_kc'], $pendingTopups)); ?>
                <?= sprintf(e(t('my.credits.pending')), count($pendingTopups), (int)$sumPending) ?>
            </p>
        <?php endif; ?>
    </div>
    <form method="post" class="pref-card-form profile-edit-form credits-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="request_topup">
        <label class="profile-field"><span><?= e(t('my.credits.amount')) ?></span>
            <input type="number" id="credits-topup-amount" name="amount" min="100" max="20000" step="50" required inputmode="numeric" placeholder="500">
        </label>
        <label class="profile-field"><span><?= e(t('my.credits.note')) ?></span>
            <input type="text" name="note" maxlength="200" placeholder="<?= e(t('my.credits.note.ph')) ?>">
        </label>
        <button class="btn btn-primary btn-sm" type="submit"><?= e(t('my.credits.request')) ?></button>
    </form>
    <form method="post" class="pref-card-form profile-edit-form credits-form" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="redeem_voucher">
        <label class="profile-field"><span><?= e(t('my.credits.voucher.code')) ?></span>
            <input type="text" name="code" maxlength="32" required placeholder="NY-XXXXXX" style="text-transform: uppercase">
        </label>
        <button class="btn btn-secondary btn-sm" type="submit"><?= e(t('my.credits.voucher.redeem')) ?></button>
    </form>
    <?php if ($iban !== '' || $bankAccount !== ''): ?>
    <?php
        $qrDefaultAmount = 500;
        $qrMsg           = 'Kredit ' . (string)$user['display_name'];
        $qrSpayd         = $iban !== '' ? ny_spayd($iban, (float)$qrDefaultAmount, $qrMsg) : '';
    ?>
    <details class="credits-bank">
        <summary><?= e(t('my.credits.bank.summary')) ?></summary>
        <dl class="credits-bank-dl">
            <?php if ($bankAccount !== ''): ?>
                <dt><?= e(t('my.credits.bank.account')) ?></dt>
                <dd class="mono"><?= e($bankAccount) ?></dd>
            <?php endif; ?>
            <?php if ($iban !== ''): ?>
                <dt><?= e(t('my.credits.bank.iban')) ?></dt>
                <dd class="mono"><?= e($iban) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('my.credits.bank.msg')) ?></dt>
            <dd class="mono"><?= e($qrMsg) ?></dd>
        </dl>
        <?php if ($iban !== ''): ?>
            <div class="credits-qr">
                <div class="poukaz-qr-box" id="credits-qr"
                     data-iban="<?= e($iban) ?>"
                     data-amount="<?= (int)$qrDefaultAmount ?>"
                     data-msg="<?= e($qrMsg) ?>"
                     data-any-amount="<?= e(t('my.credits.bank.qr.any')) ?>"
                     data-spayd="<?= e($qrSpayd) ?>"></div>
                <div class="poukaz-qr-amount">
                    <span><?= e(t('my.credits.bank.qr.amount')) ?></span>
                    <strong id="credits-qr-amount-lbl"><?= number_format($qrDefaultAmount, 0, ',', ' ') ?> Kč</strong>
                </div>
                <p class="hint"><?= e(t('my.credits.bank.qr.hint')) ?></p>
            </div>
        <?php endif; ?>
        <p class="hint"><?= e(t('my.credits.bank.hint')) ?></p>
    </details>
    <?php if ($iban !== ''): ?>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>
    <script>
    (function () {
        var qrEl    = document.getElementById('credits-qr');
        var amtEl   = document.getElementById('credits-topup-amount');
        var lblEl   = document.getElementById('credits-qr-amount-lbl');
        if (!qrEl) return;

        function parseAmount(s) {
            var m = String(s || '').replace(/[^0-9]/g, '');
            return m ? parseInt(m, 10) : 0;
        }
        function buildSpayd(iban, amount, msg) {
            var parts = ['SPD*1.0*ACC:' + iban.replace(/\s+/g, '').toUpperCase()];
            if (amount > 0) parts.push('AM:' + amount.toFixed(2));
            parts.push('CC:CZK');
            if (msg) parts.push('MSG:' + msg.substring(0, 60));
            return parts.join('*');
        }
        function render(amount) {
            if (typeof QRCode === 'undefined') return;
            var iban = qrEl.getAttribute('data-iban') || '';
            var msg  = qrEl.getAttribute('data-msg')  || '';
            if (!iban) return;
            qrEl.innerHTML = '';
            new QRCode(qrEl, {
                text: buildSpayd(iban, amount, msg),
                width: 220, height: 220,
                correctLevel: QRCode.CorrectLevel.M
            });
            if (lblEl) {
                var anyLbl = qrEl.getAttribute('data-any-amount') || '';
                lblEl.textContent = amount > 0
                    ? amount.toLocaleString('cs-CZ') + ' Kč'
                    : anyLbl;
            }
        }

        var initAmount = parseInt(qrEl.getAttribute('data-amount') || '0', 10) || 0;
        window.addEventListener('load', function () { render(initAmount); });
        if (amtEl) {
            amtEl.addEventListener('input', function () {
                var v = parseAmount(amtEl.value);
                render(v > 0 ? v : initAmount);
            });
        }
    })();
    </script>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($creditLedger): ?>
    <details class="credits-history">
        <summary><?= e(t('my.credits.history.h')) ?></summary>
        <ul class="credits-history-list">
            <?php foreach ($creditLedger as $row):
                $dt = new DateTimeImmutable((string)$row['created_at']);
                $delta = (int)$row['delta_kc'];
                $kindKey = 'my.credits.kind.' . (string)$row['kind'];
            ?>
                <li>
                    <span class="credits-history-date"><?= e($dt->format('j. n. Y')) ?></span>
                    <span class="credits-history-kind"><?= e(t($kindKey)) ?><?= $row['note'] !== '' ? ' · ' . e((string)$row['note']) : '' ?></span>
                    <span class="credits-history-delta <?= $delta >= 0 ? 'is-plus' : 'is-minus' ?>">
                        <?= $delta > 0 ? '+' : '' ?><?= number_format($delta, 0, ',', ' ') ?>&nbsp;Kč
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
    <?php endif; ?>
</div>

<div class="pref-card">
    <div class="pref-card-info">
        <h3><?= e(t('my.profile.h')) ?></h3>
        <p class="muted"><?= e(t('my.profile.desc')) ?></p>
    </div>
    <form method="post" class="pref-card-form profile-edit-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="update_profile">
        <label class="profile-field"><span><?= e(t('my.profile.name')) ?></span>
            <input type="text" name="name" value="<?= e((string)$user['display_name']) ?>" required maxlength="190">
        </label>
        <label class="profile-field"><span><?= e(t('my.profile.email')) ?></span>
            <input type="email" name="email" value="<?= e((string)$user['email']) ?>" required maxlength="190" autocomplete="email">
        </label>
        <label class="profile-field"><span><?= e(t('my.profile.phone')) ?></span>
            <input type="tel" name="phone" value="<?= e((string)($user['phone'] ?? '')) ?>" maxlength="30" autocomplete="tel">
        </label>
        <button class="btn btn-primary btn-sm" type="submit"><?= e(t('my.profile.save')) ?></button>
    </form>
</div>

<div class="pref-card">
    <div class="pref-card-info">
        <h3><?= e(t('my.password.h')) ?></h3>
        <p class="muted"><?= e(t('my.password.desc')) ?></p>
    </div>
    <form method="post" class="pref-card-form profile-edit-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="change_password">
        <label class="profile-field"><span><?= e(t('my.password.current')) ?></span>
            <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <label class="profile-field"><span><?= e(t('my.password.new')) ?></span>
            <input type="password" name="new_password" required autocomplete="new-password" minlength="8">
        </label>
        <label class="profile-field"><span><?= e(t('my.password.new2')) ?></span>
            <input type="password" name="new_password2" required autocomplete="new-password" minlength="8">
        </label>
        <button class="btn btn-primary btn-sm" type="submit"><?= e(t('my.password.save')) ?></button>
    </form>
</div>

<div class="pref-card">
    <div class="pref-card-info">
        <h3><?= e(t('my.newsletter.h')) ?></h3>
        <p class="muted"><?= sprintf(e(t('my.newsletter.desc')), e($user['email'])) ?></p>
    </div>
    <form method="post" class="pref-card-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="newsletter_toggle">
        <label class="switch">
            <input type="checkbox" name="subscribe" value="1" <?= $isSubscribed ? 'checked' : '' ?> onchange="this.form.submit()">
            <span class="switch-track"><span class="switch-knob"></span></span>
            <span class="switch-label"><?= e($isSubscribed ? t('my.newsletter.on') : t('my.newsletter.off')) ?></span>
        </label>
        <noscript><button class="btn btn-secondary btn-sm" type="submit"><?= e(t('my.newsletter.save')) ?></button></noscript>
    </form>
</div>

<h2 class="section-h" id="upcoming"><?= e(t('my.upcoming.h')) ?></h2>
<?php if (!$upcoming): ?>
    <div class="card text-center empty-state">
        <div class="empty-state-title"><?= e(t('my.upcoming.empty_title')) ?></div>
        <p class="text-muted empty-state-hint"><?= e(t('my.upcoming.empty_hint')) ?></p>
        <a class="btn btn-primary" href="rezervace.php"><?= e(t('my.upcoming.book')) ?></a>
    </div>
<?php else: ?>
    <?php
    $studioAddr = ny_setting('address', '');
    foreach ($upcoming as $r):
        $d = new DateTimeImmutable($r['class_date']);
        $dow = (int)$d->format('N');
        $gcalTitle   = $r['name'] . ' · ' . ny_setting('site_name', 'Studio Namasté');
        $gcalDetails = 'Lektor: ' . $r['teacher']
                     . ($r['room'] ? "\nSál: " . $r['room'] : '')
                     . "\n\nRezervaci můžete spravovat na " . ny_base_url() . '/myprofile.php';
        $gcalUrl = ny_gcal_url((string)$r['class_date'], (string)$r['start_time'], (string)$r['end_time'], $gcalTitle, $gcalDetails, $studioAddr);
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
            <div class="booking-actions">
                <span class="badge badge-success"><span class="dot"></span><?= e(t('my.badge.confirmed')) ?></span>
                <a class="btn btn-secondary btn-sm gcal-btn" href="<?= e($gcalUrl) ?>" target="_blank" rel="noopener" title="<?= e(t('my.gcal.title')) ?>">
                    <svg class="icon" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2"/><line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/><line x1="12" y1="13" x2="12" y2="19" stroke="currentColor" stroke-width="2"/><line x1="9" y1="16" x2="15" y2="16" stroke="currentColor" stroke-width="2"/></svg>
                    <?= e(t('my.gcal.btn')) ?>
                </a>
                <form method="post" action="cancel.php" class="inline">
                    <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                    <input type="hidden" name="class_id" value="<?= (int)$r['class_id'] ?>">
                    <input type="hidden" name="class_date" value="<?= e($r['class_date']) ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('my.btn.cancel')) ?></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2 class="section-h section-h-gap"><?= e(t('my.history.h')) ?></h2>
<?php if (!$history): ?>
    <p class="hint"><?= e(t('my.history.empty')) ?></p>
<?php else: ?>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead><tr><th><?= e(t('my.history.col.date')) ?></th><th><?= e(t('my.history.col.class')) ?></th><th><?= e(t('my.history.col.teacher')) ?></th><th><?= e(t('my.history.col.status')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($history as $r):
                $d = new DateTimeImmutable($r['class_date']); ?>
                <tr>
                    <td data-label="<?= e(t('my.history.col.date')) ?>"><?= e($d->format('j. n. Y')) ?></td>
                    <td data-label="<?= e(t('my.history.col.class')) ?>"><?= e($r['name']) ?></td>
                    <td data-label="<?= e(t('my.history.col.teacher')) ?>"><?= e($r['teacher']) ?></td>
                    <td data-label="<?= e(t('my.history.col.status')) ?>">
                        <?php if ($r['status'] === 'cancelled'): ?>
                            <span class="badge badge-danger"><?= e(t('my.history.status.cancelled')) ?></span>
                        <?php else: ?>
                            <span class="badge"><?= e(t('my.history.status.attended')) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php ny_render_footer();
