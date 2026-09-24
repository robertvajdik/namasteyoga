<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id       = (int)($_POST['id'] ?? 0);
    $name     = trim((string)($_POST['name'] ?? ''));
    $duration = trim((string)($_POST['duration'] ?? ''));
    $price    = trim((string)($_POST['price'] ?? ''));
    $desc     = trim((string)($_POST['description'] ?? '')) ?: null;
    $sort     = (int)($_POST['sort_order'] ?? 100);
    $active   = isset($_POST['active']) ? (int)$_POST['active'] : 1;

    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM ny_massages WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Masáž byla smazána.');
        ny_redirect('massages.php');
    }

    if ($name === '') {
        ny_flash_set('err', 'Vyplňte název masáže.');
    } elseif ($id) {
        $pdo->prepare(
            'UPDATE ny_massages SET name=?, duration=?, price=?, description=?, sort_order=?, active=? WHERE id=?'
        )->execute([$name, $duration, $price, $desc, $sort, $active, $id]);
        ny_flash_set('ok', 'Masáž byla uložena.');
        ny_redirect('massages.php');
    } else {
        $pdo->prepare(
            'INSERT INTO ny_massages (name, duration, price, description, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $duration, $price, $desc, $sort, $active]);
        ny_flash_set('ok', 'Masáž byla vytvořena.');
        ny_redirect('massages.php');
    }
}

$editing = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare('SELECT * FROM ny_massages WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

$massages = $pdo->query('SELECT * FROM ny_massages ORDER BY active DESC, sort_order, name')->fetchAll();

ny_admin_render_header('Masáže', 'massages');
?>

<?php if ($editing || $isNew):
    $m = $editing ?: ['id' => 0, 'name' => '', 'duration' => '', 'price' => '', 'description' => '', 'sort_order' => 100, 'active' => 1];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit masáž' : 'Nová masáž' ?></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <div class="admin-form-row">
            <label>Název
                <input type="text" name="name" value="<?= e($m['name']) ?>" required>
            </label>
            <label>Délka (text)
                <input type="text" name="duration" value="<?= e((string)$m['duration']) ?>" placeholder="Např. 60 / 90 min">
            </label>
            <label>Cena (text)
                <input type="text" name="price" value="<?= e((string)$m['price']) ?>" placeholder="Např. 850 / 1 200 Kč">
            </label>
            <label>Pořadí (menší = dřív)
                <input type="number" name="sort_order" value="<?= (int)$m['sort_order'] ?>" step="10" min="0">
            </label>
            <label>Zobrazit
                <select name="active">
                    <option value="1" <?= (int)$m['active'] === 1 ? 'selected' : '' ?>>Ano – zobrazit na webu</option>
                    <option value="0" <?= (int)$m['active'] === 0 ? 'selected' : '' ?>>Ne – skrytá</option>
                </select>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Popis
                <textarea name="description" rows="3"><?= e((string)($m['description'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="massages.php">Zrušit</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Přehled masáží</h2>
        <a class="btn btn-primary" href="?action=new">+ Nová masáž</a>
    </div>
    <?php if (!$massages): ?>
        <p class="hint">Žádné masáže.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>#</th><th>Název</th><th>Délka</th><th>Cena</th><th>Popis</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($massages as $m): ?>
                <tr>
                    <td><?= (int)$m['sort_order'] ?></td>
                    <td><strong><?= e($m['name']) ?></strong></td>
                    <td><?= e((string)$m['duration']) ?></td>
                    <td><?= e((string)$m['price']) ?></td>
                    <td><?= e(mb_strimwidth((string)($m['description'] ?? ''), 0, 80, '…')) ?></td>
                    <td>
                        <?php if ((int)$m['active'] === 1): ?>
                            <span class="badge badge-success">aktivní</span>
                        <?php else: ?>
                            <span class="badge">skrytá</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&id=<?= (int)$m['id'] ?>">Upravit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat masáž?');">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
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
