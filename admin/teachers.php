<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id     = (int)($_POST['id'] ?? 0);
    $name   = trim((string)($_POST['name'] ?? ''));
    $role   = trim((string)($_POST['role'] ?? ''));
    $bio    = trim((string)($_POST['bio'] ?? '')) ?: null;
    $sort   = (int)($_POST['sort_order'] ?? 100);
    $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;

    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM ny_teachers WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Lektor byl smazán.');
        ny_redirect('teachers.php');
    }

    if ($name === '') {
        ny_flash_set('err', 'Vyplňte jméno lektora.');
    } elseif ($id) {
        $pdo->prepare(
            'UPDATE ny_teachers SET name = ?, role = ?, bio = ?, sort_order = ?, active = ? WHERE id = ?'
        )->execute([$name, $role, $bio, $sort, $active, $id]);
        ny_flash_set('ok', 'Lektor byl uložen.');
        ny_redirect('teachers.php');
    } else {
        $pdo->prepare(
            'INSERT INTO ny_teachers (name, role, bio, sort_order, active) VALUES (?, ?, ?, ?, ?)'
        )->execute([$name, $role, $bio, $sort, $active]);
        ny_flash_set('ok', 'Lektor byl vytvořen.');
        ny_redirect('teachers.php');
    }
}

$editing = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare('SELECT * FROM ny_teachers WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

$teachers = $pdo->query('SELECT * FROM ny_teachers ORDER BY active DESC, sort_order, name')->fetchAll();

ny_admin_render_header('Lektoři', 'teachers');
?>

<?php if ($editing || $isNew):
    $t = $editing ?: ['id' => 0, 'name' => '', 'role' => '', 'bio' => '', 'sort_order' => 100, 'active' => 1];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit lektora' : 'Nový lektor' ?></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
        <div class="admin-form-row">
            <label>Jméno
                <input type="text" name="name" value="<?= e($t['name']) ?>" required>
            </label>
            <label>Zaměření (styly)
                <input type="text" name="role" value="<?= e((string)$t['role']) ?>" placeholder="Např. Hatha, Vinyasa">
            </label>
            <label>Pořadí (menší = dřív)
                <input type="number" name="sort_order" value="<?= (int)$t['sort_order'] ?>" step="10" min="0">
            </label>
            <label>Zobrazit
                <select name="active">
                    <option value="1" <?= (int)$t['active'] === 1 ? 'selected' : '' ?>>Ano – zobrazit na webu</option>
                    <option value="0" <?= (int)$t['active'] === 0 ? 'selected' : '' ?>>Ne – skrytý</option>
                </select>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Bio (krátký popis)
                <textarea name="bio" rows="3"><?= e((string)($t['bio'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="teachers.php">Zrušit</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Přehled lektorů</h2>
        <a class="btn btn-primary" href="?action=new">+ Nový lektor</a>
    </div>
    <?php if (!$teachers): ?>
        <p class="hint">Žádní lektoři.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>#</th><th>Jméno</th><th>Zaměření</th><th>Bio</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td><?= (int)$t['sort_order'] ?></td>
                    <td><strong><?= e($t['name']) ?></strong></td>
                    <td><?= e((string)$t['role']) ?></td>
                    <td><?= e(mb_strimwidth((string)($t['bio'] ?? ''), 0, 80, '…')) ?></td>
                    <td>
                        <?php if ((int)$t['active'] === 1): ?>
                            <span class="badge badge-success">aktivní</span>
                        <?php else: ?>
                            <span class="badge">skrytý</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&id=<?= (int)$t['id'] ?>">Upravit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat lektora?');">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
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
