<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();

// Massage / physio slots. Stored in ny_classes with kind = 'therapy' so they
// reuse the whole reservation flow (reserve/cancel, credits, reminders), but
// are managed here instead of in Lekce and shown in their own calendar.

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);
$showPast = isset($_GET['past']);

$daysCz    = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];
$daysShort = [1 => 'po', 2 => 'út', 3 => 'st', 4 => 'čt', 5 => 'pá', 6 => 'so', 7 => 'ne'];
$today     = new DateTimeImmutable('today');

function _nyTherapyTime(string $s): ?DateTimeImmutable {
    $t = DateTimeImmutable::createFromFormat('!H:i', substr(trim($s), 0, 5));
    return $t ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    $backUrl = 'therapy.php' . ($showPast ? '?past=1' : '');

    if ($action === 'delete' && $id) {
        // Hard delete – reservations go with it (FK CASCADE).
        $pdo->prepare("DELETE FROM ny_classes WHERE id = ? AND kind = 'therapy'")->execute([$id]);
        ny_flash_set('ok', 'Termín byl smazán včetně rezervací.');
        ny_redirect($backUrl);
    }
    if (($action === 'hide' || $action === 'show') && $id) {
        $pdo->prepare("UPDATE ny_classes SET active = ? WHERE id = ? AND kind = 'therapy'")
            ->execute([$action === 'show' ? 1 : 0, $id]);
        ny_flash_set('ok', $action === 'show' ? 'Termín je znovu zveřejněný.' : 'Termín byl skryt.');
        ny_redirect($backUrl);
    }

    $name     = trim((string)($_POST['name'] ?? ''));
    $teacher  = trim((string)($_POST['teacher'] ?? ''));
    $room     = trim((string)($_POST['room'] ?? '')) ?: null;
    $desc     = trim((string)($_POST['description'] ?? '')) ?: null;
    $capacity = max(1, (int)($_POST['capacity'] ?? 1));
    $price    = trim((string)($_POST['price'] ?? ''));
    $active   = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    $repeat   = ($_POST['repeat'] ?? 'once') === 'weekly' ? 'weekly' : 'once';
    $dateObj  = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($_POST['date'] ?? ''));
    $untilObj = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($_POST['until'] ?? ''));
    $start    = _nyTherapyTime((string)($_POST['start_time'] ?? ''));
    $end      = _nyTherapyTime((string)($_POST['end_time'] ?? ''));
    $split    = max(0, (int)($_POST['split_minutes'] ?? 0));
    $pause    = max(0, (int)($_POST['pause_minutes'] ?? 0));

    $err = null;
    if ($name === '' || $teacher === '' || !$dateObj || !$start || !$end) {
        $err = 'Vyplňte druh, terapeuta, datum a čas.';
    } elseif ($end <= $start) {
        $err = 'Konec musí být po začátku.';
    } elseif ($repeat === 'weekly' && $untilObj && $untilObj < $dateObj) {
        $err = 'Datum „opakovat do“ nesmí být před prvním termínem.';
    }

    // Time ranges to save: one, or the start–end window split into slots.
    $ranges = [];
    if (!$err) {
        if (!$id && $split > 0) {
            for ($t = $start; $t->modify('+' . $split . ' minutes') <= $end; $t = $t->modify('+' . ($split + $pause) . ' minutes')) {
                $ranges[] = [$t, $t->modify('+' . $split . ' minutes')];
                if (count($ranges) > 40) break;
            }
            if (!$ranges) {
                $err = 'Do zvoleného času se nevejde ani jeden termín této délky.';
            }
        } else {
            $ranges[] = [$start, $end];
        }
    }

    if ($err) {
        ny_flash_set('err', $err);
        ny_redirect('therapy.php?action=' . ($id ? 'edit&id=' . $id : 'new'));
    }

    $dow      = (int)$dateObj->format('N');
    $startsOn = $dateObj->format('Y-m-d');
    $endsOn   = $repeat === 'once' ? $startsOn : ($untilObj ? $untilObj->format('Y-m-d') : null);

    if ($id) {
        [$s, $e] = $ranges[0];
        $pdo->prepare(
            "UPDATE ny_classes SET name=?, description=?, teacher=?, room=?, capacity=?, price=?, day_of_week=?, start_time=?, end_time=?,
                                   active=?, starts_on=?, ends_on=?
              WHERE id=? AND kind = 'therapy'"
        )->execute([$name, $desc, $teacher, $room, $capacity, $price, $dow, $s->format('H:i'), $e->format('H:i'), $active, $startsOn, $endsOn, $id]);
        ny_flash_set('ok', 'Termín byl uložen.');
    } else {
        $ins = $pdo->prepare(
            "INSERT INTO ny_classes (name, description, teacher, room, capacity, price, kind, day_of_week, start_time, end_time, active, starts_on, ends_on)
             VALUES (?, ?, ?, ?, ?, ?, 'therapy', ?, ?, ?, ?, ?, ?)"
        );
        foreach ($ranges as [$s, $e]) {
            $ins->execute([$name, $desc, $teacher, $room, $capacity, $price, $dow, $s->format('H:i'), $e->format('H:i'), $active, $startsOn, $endsOn]);
        }
        ny_flash_set('ok', count($ranges) === 1 ? 'Termín byl vytvořen.' : 'Vytvořeno ' . count($ranges) . ' termínů.');
    }
    ny_redirect('therapy.php');
}

$editing = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare("SELECT * FROM ny_classes WHERE id = ? AND kind = 'therapy'");
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

$massages = ny_massages_active();
$teacherNames = array_map(fn($t) => (string)$t['name'], ny_teachers_active());

$slots = $pdo->query(
    "SELECT * FROM ny_classes WHERE kind = 'therapy'
      ORDER BY (ends_on IS NULL OR ends_on <> starts_on), starts_on, day_of_week, start_time"
)->fetchAll();

// Upcoming bookings per slot – admin sees who is coming and how to reach them.
$bookings = [];
$bookStmt = $pdo->prepare(
    "SELECT r.class_id, r.class_date, r.payment_method, r.price_kc, u.display_name, u.email, u.phone
       FROM ny_reservations r
       JOIN ny_classes c ON c.id = r.class_id AND c.kind = 'therapy'
       JOIN ny_users u   ON u.id = r.user_id
      WHERE r.status = 'booked' AND r.class_date >= ?
      ORDER BY r.class_date, u.display_name"
);
$bookStmt->execute([$today->format('Y-m-d')]);
foreach ($bookStmt as $b) {
    $bookings[(int)$b['class_id']][] = $b;
}

$todayStr = $today->format('Y-m-d');
$isPastSlot = fn(array $c): bool => !empty($c['ends_on']) && $c['ends_on'] < $todayStr;
$visible = array_values(array_filter($slots, fn($c) => $showPast ? ($isPastSlot($c) || (int)$c['active'] === 0) : (!$isPastSlot($c) && (int)$c['active'] === 1)));
$pastCount = count(array_filter($slots, fn($c) => $isPastSlot($c) || (int)$c['active'] === 0));

ny_admin_render_header('Masáže – termíny', 'therapy');
?>

<?php if ($editing || $isNew):
    $c = $editing ?: [
        'id' => 0, 'name' => '', 'description' => '', 'teacher' => $teacherNames[0] ?? '', 'room' => '',
        'capacity' => 1, 'price' => '', 'start_time' => '09:00', 'end_time' => '10:00', 'active' => 1,
        'starts_on' => $today->modify('+1 day')->format('Y-m-d'), 'ends_on' => null,
    ];
    $cRepeat = ($editing && ($c['ends_on'] === null || $c['ends_on'] !== $c['starts_on'])) ? 'weekly' : 'once';
    $cDate   = (string)($c['starts_on'] ?: $todayStr);
    $cUntil  = $cRepeat === 'weekly' ? (string)($c['ends_on'] ?? '') : '';
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit termín' : 'Nové termíny' ?></h2>
    <?php if ($editing && !empty($bookings[(int)$editing['id']])): ?>
        <div class="flash flash-err">Termín už má rezervace. Při změně data nebo času je klienti nedostanou automaticky – dejte jim vědět.</div>
    <?php endif; ?>
    <form method="post" class="admin-form" id="therapy-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="admin-form-row">
            <?php if ($massages): ?>
            <label>Z nabídky masáží
                <select id="therapy-preset">
                    <option value="">— vyberte pro předvyplnění —</option>
                    <?php foreach ($massages as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"
                                data-name="<?= e((string)$m['name']) ?>"
                                data-price="<?= e((string)$m['price']) ?>"
                                <?= (string)$m['name'] === (string)$c['name'] ? 'selected' : '' ?>><?= e((string)$m['name']) ?><?= $m['duration'] !== '' ? ' · ' . e((string)$m['duration']) : '' ?></option>
                    <?php endforeach; ?>
                    <option value="" data-name="Fyzioterapie" data-price="">Fyzioterapie</option>
                </select>
            </label>
            <?php endif; ?>
            <label>Druh (název termínu)
                <input type="text" name="name" id="therapy-name" value="<?= e((string)$c['name']) ?>" required placeholder="Např. Klasická masáž 60 min">
            </label>
            <label>Terapeut
                <input type="text" name="teacher" value="<?= e((string)$c['teacher']) ?>" required list="therapy-teachers">
                <datalist id="therapy-teachers">
                    <?php foreach ($teacherNames as $tn): ?><option value="<?= e($tn) ?>"><?php endforeach; ?>
                </datalist>
            </label>
            <label>Cena
                <input type="text" name="price" id="therapy-price" value="<?= e((string)$c['price']) ?>" placeholder="např. 850 Kč">
            </label>
            <label>Místnost (nepovinné)
                <input type="text" name="room" value="<?= e((string)($c['room'] ?? '')) ?>">
            </label>
            <label>Kapacita (klientů na termín)
                <input type="number" min="1" name="capacity" value="<?= (int)$c['capacity'] ?>" required>
            </label>
        </div>
        <div class="admin-form-row">
            <label>Datum
                <input type="date" name="date" value="<?= e($cDate) ?>" required>
            </label>
            <label><?= $editing ? 'Začátek' : 'Od' ?>
                <input type="time" name="start_time" value="<?= e(substr((string)$c['start_time'], 0, 5)) ?>" required>
            </label>
            <label><?= $editing ? 'Konec' : 'Do' ?>
                <input type="time" name="end_time" value="<?= e(substr((string)$c['end_time'], 0, 5)) ?>" required>
            </label>
            <?php if (!$editing): ?>
            <label>Rozdělit na termíny po (min)
                <input type="number" name="split_minutes" min="0" step="5" value="0">
                <small class="hint">0 = jeden termín Od–Do. Např. 60 rozdělí 9:00–13:00 na 4 termíny.</small>
            </label>
            <label>Pauza mezi termíny (min)
                <input type="number" name="pause_minutes" min="0" step="5" value="0">
            </label>
            <?php endif; ?>
            <label>Opakování
                <select name="repeat" id="therapy-repeat">
                    <option value="once"   <?= $cRepeat === 'once' ? 'selected' : '' ?>>Jednorázově</option>
                    <option value="weekly" <?= $cRepeat === 'weekly' ? 'selected' : '' ?>>Každý týden ve stejný den</option>
                </select>
            </label>
            <label id="therapy-until-wrap">Opakovat do (nepovinné)
                <input type="date" name="until" value="<?= e($cUntil) ?>">
            </label>
            <label>Zobrazit
                <select name="active">
                    <option value="1" <?= (int)$c['active'] === 1 ? 'selected' : '' ?>>Ano – lze rezervovat</option>
                    <option value="0" <?= (int)$c['active'] === 0 ? 'selected' : '' ?>>Ne – skrytý</option>
                </select>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Poznámka / popis (nepovinné)
                <textarea name="description" rows="2"><?= e((string)($c['description'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="therapy.php">Zrušit</a>
        </div>
    </form>
</div>
<script>
(function () {
    var preset = document.getElementById('therapy-preset');
    var nameEl = document.getElementById('therapy-name');
    var priceEl = document.getElementById('therapy-price');
    if (preset) preset.addEventListener('change', function () {
        var o = preset.options[preset.selectedIndex];
        if (!o || !o.dataset.name) return;
        nameEl.value = o.dataset.name;
        if (o.dataset.price) priceEl.value = o.dataset.price;
    });
    var repeat = document.getElementById('therapy-repeat');
    var untilWrap = document.getElementById('therapy-until-wrap');
    function syncUntil() { untilWrap.hidden = repeat.value !== 'weekly'; }
    repeat.addEventListener('change', syncUntil);
    syncUntil();
})();
</script>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2><?= $showPast ? 'Proběhlé a skryté termíny' : 'Nadcházející termíny' ?></h2>
        <div class="row">
            <?php if ($showPast): ?>
                <a class="btn btn-ghost" href="therapy.php">Zpět na nadcházející</a>
            <?php elseif ($pastCount): ?>
                <a class="btn btn-ghost" href="?past=1">Proběhlé a skryté (<?= $pastCount ?>)</a>
            <?php endif; ?>
            <a class="btn btn-primary" href="?action=new">+ Nové termíny</a>
        </div>
    </div>
    <p class="hint">Termíny se zobrazují v rezervacích v kalendáři „Masáže a fyzioterapie“ a na stránce Masáže. Klienti se rezervují sami; seznam klientů vidí jen admin.</p>
    <?php if (!$visible): ?>
        <p class="hint">Žádné termíny.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>Termín</th><th>Čas</th><th>Druh</th><th>Terapeut</th><th>Cena</th><th>Rezervace</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($visible as $c):
                $once   = !empty($c['starts_on']) && $c['ends_on'] === $c['starts_on'];
                $dow    = (int)$c['day_of_week'];
                $slotBk = $bookings[(int)$c['id']] ?? [];
                if ($once) {
                    $when = $daysShort[$dow] . ' ' . (new DateTimeImmutable($c['starts_on']))->format('j. n. Y');
                } else {
                    $when = 'každé ' . $daysShort[$dow];
                    if (!empty($c['starts_on'])) $when .= ' od ' . (new DateTimeImmutable($c['starts_on']))->format('j. n.');
                    if (!empty($c['ends_on']))   $when .= ' do ' . (new DateTimeImmutable($c['ends_on']))->format('j. n. Y');
                }
                $confirm = $slotBk
                    ? 'Opravdu smazat termín? Bude odstraněno také ' . count($slotBk) . ' rezervací. Klienty informujte sami.'
                    : 'Opravdu smazat termín?';
            ?>
                <tr>
                    <td data-label="Termín"><strong><?= e($when) ?></strong></td>
                    <td data-label="Čas"><?= e(substr((string)$c['start_time'], 0, 5)) ?> – <?= e(substr((string)$c['end_time'], 0, 5)) ?></td>
                    <td data-label="Druh"><?= e((string)$c['name']) ?></td>
                    <td data-label="Terapeut"><?= e((string)$c['teacher']) ?></td>
                    <td data-label="Cena"><?= $c['price'] !== '' ? e((string)$c['price']) : '<span class="hint">—</span>' ?></td>
                    <td data-label="Rezervace">
                        <?php if (!$slotBk): ?>
                            <span class="hint">volno</span>
                        <?php else: foreach ($slotBk as $b): ?>
                            <div class="therapy-booking">
                                <?php if (!$once): ?><span class="hint"><?= e((new DateTimeImmutable($b['class_date']))->format('j. n.')) ?></span><?php endif; ?>
                                <strong><?= e((string)$b['display_name']) ?></strong>
                                <?php if (!empty($b['phone'])): ?> · <a href="tel:<?= e(preg_replace('/\s+/', '', (string)$b['phone'])) ?>"><?= e((string)$b['phone']) ?></a><?php endif; ?>
                                · <a href="mailto:<?= e((string)$b['email']) ?>"><?= e((string)$b['email']) ?></a>
                                <?php if ($b['payment_method'] === 'credits'): ?> <span class="badge badge-success">kredit <?= (int)$b['price_kc'] ?> Kč</span><?php elseif ($b['payment_method'] === 'qr'): ?> <span class="badge badge-info">QR převod <?= (int)$b['price_kc'] ?> Kč</span><?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </td>
                    <td data-label="Stav">
                        <?php if ((int)$c['active'] === 0): ?>
                            <span class="badge">skrytý</span>
                        <?php elseif ($isPastSlot($c)): ?>
                            <span class="badge">proběhl</span>
                        <?php else: ?>
                            <span class="badge badge-success">aktivní</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&amp;id=<?= (int)$c['id'] ?>">Upravit</a>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="action" value="<?= (int)$c['active'] === 1 ? 'hide' : 'show' ?>">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-ghost" type="submit"><?= (int)$c['active'] === 1 ? 'Skrýt' : 'Zveřejnit' ?></button>
                        </form>
                        <form method="post" class="inline" onsubmit="return confirm(<?= e(json_encode($confirm, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>);">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button class="btn btn-danger" type="submit">Smazat</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php ny_admin_render_footer();
