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
    $isPublic = isset($_POST['is_public']) ? (int)$_POST['is_public'] : 1;

    if ($action === 'delete' && $origSlug !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ny_gallery WHERE section = ?');
        $stmt->execute([$origSlug]);
        if ((int)$stmt->fetchColumn() > 0) {
            ny_flash_set('err', 'Album nelze smazat – obsahuje fotografie. Nejprve je přesuňte nebo smažte.');
            ny_redirect('gallery_albums.php');
        }
        $pdo->prepare('DELETE FROM ny_gallery_albums WHERE slug = ?')->execute([$origSlug]);
        ny_flash_set('ok', 'Album bylo smazáno.');
        ny_redirect('gallery_albums.php');
    }

    if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
        ny_flash_set('err', 'Slug musí obsahovat jen malá písmena, čísla, „-" nebo „_".');
    } elseif ($label === '') {
        ny_flash_set('err', 'Vyplňte název alba.');
    } elseif ($origSlug !== '') {
        if ($origSlug !== $slug) {
            $dupe = $pdo->prepare('SELECT 1 FROM ny_gallery_albums WHERE slug = ?');
            $dupe->execute([$slug]);
            if ($dupe->fetchColumn()) {
                ny_flash_set('err', 'Album s tímto slug už existuje.');
                ny_redirect('gallery_albums.php?action=edit&slug=' . rawurlencode($origSlug));
            }
            $pdo->prepare('UPDATE ny_gallery_albums SET slug=?, label=?, description=?, sort_order=?, is_public=? WHERE slug=?')
                ->execute([$slug, $label, $desc, $sort, $isPublic, $origSlug]);
            // Keep photo section references in sync.
            $pdo->prepare('UPDATE ny_gallery SET section=? WHERE section=?')
                ->execute([$slug, $origSlug]);
        } else {
            $pdo->prepare('UPDATE ny_gallery_albums SET label=?, description=?, sort_order=?, is_public=? WHERE slug=?')
                ->execute([$label, $desc, $sort, $isPublic, $slug]);
        }
        ny_flash_set('ok', 'Album bylo uloženo.');
        ny_redirect('gallery_albums.php');
    } else {
        $dupe = $pdo->prepare('SELECT 1 FROM ny_gallery_albums WHERE slug = ?');
        $dupe->execute([$slug]);
        if ($dupe->fetchColumn()) {
            ny_flash_set('err', 'Album s tímto slug už existuje.');
            ny_redirect('gallery_albums.php?action=new');
        }
        $pdo->prepare('INSERT INTO ny_gallery_albums (slug, label, description, sort_order, is_public) VALUES (?, ?, ?, ?, ?)')
            ->execute([$slug, $label, $desc, $sort, $isPublic]);
        ny_flash_set('ok', 'Album bylo vytvořeno.');
        ny_redirect('gallery_albums.php');
    }
}

$editing = null;
if ($action === 'edit' && $editSlug !== '') {
    $stmt = $pdo->prepare('SELECT * FROM ny_gallery_albums WHERE slug = ?');
    $stmt->execute([$editSlug]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

$albums = $pdo->query(
    'SELECT a.*, (SELECT COUNT(*) FROM ny_gallery g WHERE g.section = a.slug) AS photo_count
       FROM ny_gallery_albums a
      ORDER BY a.sort_order, a.label'
)->fetchAll();

ny_admin_render_header('Galerie – alba', 'gallery_albums');
?>

<?php if ($editing || $isNew):
    $a = $editing ?: ['slug' => '', 'label' => '', 'description' => '', 'sort_order' => 100, 'is_public' => 1];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit album' : 'Nové album' ?></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="orig_slug" value="<?= e((string)$a['slug']) ?>">
        <div class="admin-form-row">
            <label>Slug (v URL)
                <input type="text" name="slug" value="<?= e((string)$a['slug']) ?>" required placeholder="např. retreat-2026">
                <small class="hint hint-inline">
                    Malá písmena bez diakritiky. Používá se v adrese <code>?album=<em>slug</em></code> a jako identifikátor fotografií.
                </small>
            </label>
            <label>Název
                <input type="text" name="label" value="<?= e((string)$a['label']) ?>" required>
            </label>
            <label>Pořadí (menší = dřív)
                <input type="number" name="sort_order" value="<?= (int)$a['sort_order'] ?>" step="10" min="0">
            </label>
            <label>Veřejné
                <select name="is_public">
                    <option value="1" <?= (int)$a['is_public'] === 1 ? 'selected' : '' ?>>Ano – na stránce Galerie</option>
                    <option value="0" <?= (int)$a['is_public'] === 0 ? 'selected' : '' ?>>Ne – jen interní (např. dlaždice domů)</option>
                </select>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Popis (zobrazí se na kartě alba a v záhlaví)
                <textarea name="description" rows="3"><?= e((string)($a['description'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="gallery_albums.php">Zrušit</a>
            <?php if ($editing): ?>
                <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat toto album?');">
                    <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="orig_slug" value="<?= e((string)$a['slug']) ?>">
                    <button class="btn btn-danger" type="submit">Smazat album</button>
                </form>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Alba galerie</h2>
        <a class="btn btn-primary" href="?action=new">+ Nové album</a>
    </div>
    <p class="hint hint-lift">
        Veřejná alba se zobrazují na stránce <a href="../galerie.php" target="_blank" rel="noopener">/galerie.php</a>.
        Fotografie do alb přiřazujete v sekci <a href="gallery.php">Galerie</a>.
    </p>
    <?php if (!$albums): ?>
        <p class="hint">Žádná alba.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>#</th><th>Slug</th><th>Název</th><th>Popis</th><th>Fotografií</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($albums as $a): ?>
                <tr>
                    <td><?= (int)$a['sort_order'] ?></td>
                    <td><code><?= e((string)$a['slug']) ?></code></td>
                    <td><strong><?= e((string)$a['label']) ?></strong></td>
                    <td><?= e(mb_strimwidth((string)($a['description'] ?? ''), 0, 80, '…')) ?></td>
                    <td><?= (int)$a['photo_count'] ?></td>
                    <td>
                        <?php if ((int)$a['is_public'] === 1): ?>
                            <span class="badge badge-success">veřejné</span>
                        <?php else: ?>
                            <span class="badge">interní</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&slug=<?= e((string)$a['slug']) ?>">Upravit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php ny_admin_render_footer();
