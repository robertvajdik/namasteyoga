<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editSlug = (string)($_GET['slug'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $origSlug = (string)($_POST['orig_slug'] ?? '');
    $slug     = strtolower(trim((string)($_POST['slug'] ?? '')));
    $label    = trim((string)($_POST['label'] ?? ''));
    $desc     = trim((string)($_POST['description'] ?? '')) ?: null;
    $sort     = (int)($_POST['sort_order'] ?? 100);
    $active   = isset($_POST['active']) ? (int)$_POST['active'] : 1;

    if ($action === 'delete' && $origSlug !== '') {
        $pdo->prepare('DELETE FROM ny_categories WHERE slug = ?')->execute([$origSlug]);
        ny_flash_set('ok', 'Kategorie byla smazána.');
        ny_redirect('categories.php');
    }

    if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
        ny_flash_set('err', 'Slug musí obsahovat jen malá písmena, čísla, „-" nebo „_".');
    } elseif ($label === '') {
        ny_flash_set('err', 'Vyplňte název kategorie.');
    } elseif ($origSlug !== '') {
        if ($origSlug !== $slug) {
            $pdo->prepare('UPDATE ny_categories SET slug=?, label=?, description=?, sort_order=?, active=? WHERE slug=?')
                ->execute([$slug, $label, $desc, $sort, $active, $origSlug]);
        } else {
            $pdo->prepare('UPDATE ny_categories SET label=?, description=?, sort_order=?, active=? WHERE slug=?')
                ->execute([$label, $desc, $sort, $active, $slug]);
        }
        ny_flash_set('ok', 'Kategorie byla uložena.');
        ny_redirect('categories.php');
    } else {
        $pdo->prepare('INSERT INTO ny_categories (slug, label, description, sort_order, active) VALUES (?, ?, ?, ?, ?)')
            ->execute([$slug, $label, $desc, $sort, $active]);
        ny_flash_set('ok', 'Kategorie byla vytvořena.');
        ny_redirect('categories.php');
    }
}

$editing = null;
if ($action === 'edit' && $editSlug !== '') {
    $stmt = $pdo->prepare('SELECT * FROM ny_categories WHERE slug = ?');
    $stmt->execute([$editSlug]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

$cats = $pdo->query('SELECT * FROM ny_categories ORDER BY active DESC, sort_order, label')->fetchAll();

ny_admin_render_header('Kategorie lekcí', 'categories');
?>

<?php if ($editing || $isNew):
    $c = $editing ?: ['slug' => '', 'label' => '', 'description' => '', 'sort_order' => 100, 'active' => 1];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit kategorii' : 'Nová kategorie' ?></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="orig_slug" value="<?= e((string)$c['slug']) ?>">
        <div class="admin-form-row">
            <label>Slug (v URL, barva)
                <input type="text" name="slug" value="<?= e((string)$c['slug']) ?>" required placeholder="např. yoga">
                <small class="hint hint-inline">
                    Malá písmena bez diakritiky. Ovlivňuje barvu (var(--cat-<em>slug</em>)) i kotvu #<em>slug</em>.
                </small>
            </label>
            <label>Název
                <input type="text" name="label" value="<?= e((string)$c['label']) ?>" required>
            </label>
            <label>Pořadí (menší = dřív)
                <input type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>" step="10" min="0">
            </label>
            <label>Zobrazit
                <select name="active">
                    <option value="1" <?= (int)$c['active'] === 1 ? 'selected' : '' ?>>Ano – zobrazit na webu</option>
                    <option value="0" <?= (int)$c['active'] === 0 ? 'selected' : '' ?>>Ne – skrytá</option>
                </select>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Popis (věta pod názvem)
                <textarea name="description" rows="3"><?= e((string)($c['description'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="categories.php">Zrušit</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Kategorie na stránce Lekce</h2>
        <a class="btn btn-primary" href="?action=new">+ Nová kategorie</a>
    </div>
    <p class="hint hint-lift">
        Zobrazí se jako dlaždice na stránce <a href="../lekce.php" target="_blank" rel="noopener">/lekce.php</a>.
    </p>
    <?php if (!$cats): ?>
        <p class="hint">Žádné kategorie.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>#</th><th>Slug</th><th>Název</th><th>Popis</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($cats as $c): ?>
                <tr>
                    <td><?= (int)$c['sort_order'] ?></td>
                    <td><code><?= e((string)$c['slug']) ?></code></td>
                    <td><strong><?= e((string)$c['label']) ?></strong></td>
                    <td><?= e(mb_strimwidth((string)($c['description'] ?? ''), 0, 80, '…')) ?></td>
                    <td>
                        <?php if ((int)$c['active'] === 1): ?>
                            <span class="badge badge-success">aktivní</span>
                        <?php else: ?>
                            <span class="badge">skrytá</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&slug=<?= e((string)$c['slug']) ?>">Upravit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat kategorii?');">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="orig_slug" value="<?= e((string)$c['slug']) ?>">
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
