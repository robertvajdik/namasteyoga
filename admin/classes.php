<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim((string)($_POST['name'] ?? ''));
    $teacher  = trim((string)($_POST['teacher'] ?? ''));
    $room     = trim((string)($_POST['room'] ?? '')) ?: null;
    $desc     = trim((string)($_POST['description'] ?? '')) ?: null;
    $capacity = max(1, (int)($_POST['capacity'] ?? 12));
    $dow      = (int)($_POST['day_of_week'] ?? 1);
    $start    = (string)($_POST['start_time'] ?? '');
    $end      = (string)($_POST['end_time'] ?? '');
    $active   = isset($_POST['active']) ? 1 : 0;

    if ($action === 'delete' && $id) {
        $pdo->prepare('UPDATE ny_classes SET active = 0 WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Lekce byla deaktivována.');
        ny_redirect('classes.php');
    }

    if ($name === '' || $teacher === '' || $dow < 1 || $dow > 7 || !$start || !$end) {
        ny_flash_set('err', 'Vyplňte všechna povinná pole.');
    } elseif ($id) {
        $pdo->prepare(
            'UPDATE ny_classes SET name=?, description=?, teacher=?, room=?, capacity=?, day_of_week=?, start_time=?, end_time=?, active=? WHERE id=?'
        )->execute([$name, $desc, $teacher, $room, $capacity, $dow, $start, $end, $active, $id]);
        ny_flash_set('ok', 'Lekce byla uložena.');
        ny_redirect('classes.php');
    } else {
        $pdo->prepare(
            'INSERT INTO ny_classes (name, description, teacher, room, capacity, day_of_week, start_time, end_time, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$name, $desc, $teacher, $room, $capacity, $dow, $start, $end, $active]);
        ny_flash_set('ok', 'Lekce byla vytvořena.');
        ny_redirect('classes.php');
    }
}

$editing = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare('SELECT * FROM ny_classes WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

$classes = $pdo->query('SELECT * FROM ny_classes ORDER BY active DESC, day_of_week, start_time')->fetchAll();

$daysCz = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];

ny_admin_render_header('Lekce', 'classes');
?>

<?php if ($editing || $isNew):
    $c = $editing ?: ['id' => 0, 'name' => '', 'description' => '', 'teacher' => '', 'room' => '', 'capacity' => 12, 'day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '19:15', 'active' => 1];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit lekci' : 'Nová lekce' ?></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <div class="admin-form-row">
            <label>Název lekce
                <input type="text" name="name" value="<?= e($c['name']) ?>" required>
            </label>
            <label>Lektor
                <input type="text" name="teacher" value="<?= e($c['teacher']) ?>" required>
            </label>
            <label>Sál
                <input type="text" name="room" value="<?= e((string)($c['room'] ?? '')) ?>">
            </label>
            <label>Kapacita
                <input type="number" min="1" name="capacity" value="<?= (int)$c['capacity'] ?>" required>
            </label>
            <label>Den v týdnu
                <select name="day_of_week" required>
                    <?php foreach ($daysCz as $n => $lbl): ?>
                        <option value="<?= $n ?>" <?= (int)$c['day_of_week'] === $n ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Aktivní
                <select name="active">
                    <option value="1" <?= (int)$c['active'] === 1 ? 'selected' : '' ?>>Ano – zobrazovat v rozvrhu</option>
                    <option value="0" <?= (int)$c['active'] === 0 ? 'selected' : '' ?>>Ne – skrytá</option>
                </select>
            </label>
            <label>Začátek
                <input type="time" name="start_time" value="<?= e(substr((string)$c['start_time'], 0, 5)) ?>" required>
            </label>
            <label>Konec
                <input type="time" name="end_time" value="<?= e(substr((string)$c['end_time'], 0, 5)) ?>" required>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Popis (nepovinné)
                <textarea name="description" rows="3"><?= e((string)($c['description'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="classes.php">Zrušit</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Přehled lekcí</h2>
        <a class="btn btn-primary" href="?action=new">+ Nová lekce</a>
    </div>
    <?php if (!$classes): ?>
        <p class="hint">Žádné lekce.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>Den</th><th>Čas</th><th>Název</th><th>Lektor</th><th>Sál</th><th>Kapacita</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($classes as $c): ?>
                <tr>
                    <td><?= e($daysCz[(int)$c['day_of_week']] ?? '?') ?></td>
                    <td><?= e(substr((string)$c['start_time'], 0, 5)) ?> – <?= e(substr((string)$c['end_time'], 0, 5)) ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td><?= e($c['teacher']) ?></td>
                    <td><?= e((string)($c['room'] ?? '—')) ?></td>
                    <td><?= (int)$c['capacity'] ?></td>
                    <td>
                        <?php if ((int)$c['active'] === 1): ?>
                            <span class="badge badge-success">aktivní</span>
                        <?php else: ?>
                            <span class="badge">skrytá</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&id=<?= (int)$c['id'] ?>">Upravit</a>
                        <?php if ((int)$c['active'] === 1): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Deaktivovat lekci?');">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                <button class="btn btn-danger" type="submit">Skrýt</button>
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
