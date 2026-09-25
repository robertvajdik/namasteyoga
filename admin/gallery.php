<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

$sections = ny_gallery_sections();
$uploadDir = __DIR__ . '/../assets/gallery';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

function ny_gallery_store_upload(array $file, string $existing = ''): string {
    global $uploadDir;
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return $existing;
    }
    $mime = @mime_content_type($file['tmp_name']) ?: '';
    $extByMime = [
        'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    if (!isset($extByMime[$mime])) {
        throw new RuntimeException('Nepodporovaný typ souboru. Povoleno: JPG, PNG, WEBP, GIF.');
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Soubor je příliš velký (max 8 MB).');
    }
    $ext  = $extByMime[$mime];
    $name = bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $uploadDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Soubor se nepodařilo uložit.');
    }
    if ($existing !== '') {
        @unlink($uploadDir . '/' . basename($existing));
    }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id      = (int)($_POST['id'] ?? 0);
    $section = (string)($_POST['section'] ?? 'studio');
    if (!isset($sections[$section])) $section = 'studio';
    $title   = trim((string)($_POST['title'] ?? ''));
    $alt     = trim((string)($_POST['alt'] ?? ''));
    $sort    = (int)($_POST['sort_order'] ?? 100);
    $active  = isset($_POST['active']) ? (int)$_POST['active'] : 1;

    if ($action === 'delete' && $id) {
        $row = $pdo->prepare('SELECT file FROM ny_gallery WHERE id = ?');
        $row->execute([$id]);
        if ($file = $row->fetchColumn()) {
            @unlink($uploadDir . '/' . basename((string)$file));
        }
        $pdo->prepare('DELETE FROM ny_gallery WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Fotografie byla smazána.');
        ny_redirect('gallery.php');
    }

    try {
        $existing = '';
        if ($id) {
            $row = $pdo->prepare('SELECT file FROM ny_gallery WHERE id = ?');
            $row->execute([$id]);
            $existing = (string)($row->fetchColumn() ?: '');
        }
        $file = ny_gallery_store_upload($_FILES['image'] ?? [], $existing);

        if ($id === 0 && $file === '') {
            ny_flash_set('err', 'Vyberte prosím obrázek k nahrání.');
        } elseif ($id) {
            $pdo->prepare(
                'UPDATE ny_gallery SET section = ?, title = ?, alt = ?, file = ?, sort_order = ?, active = ? WHERE id = ?'
            )->execute([$section, $title, $alt, $file, $sort, $active, $id]);
            ny_flash_set('ok', 'Fotografie byla uložena.');
            ny_redirect('gallery.php');
        } else {
            $pdo->prepare(
                'INSERT INTO ny_gallery (section, title, alt, file, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$section, $title, $alt, $file, $sort, $active]);
            ny_flash_set('ok', 'Fotografie byla přidána.');
            ny_redirect('gallery.php');
        }
    } catch (Throwable $e) {
        ny_flash_set('err', $e->getMessage());
    }
}

$editing = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare('SELECT * FROM ny_gallery WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

$items = $pdo->query('SELECT * FROM ny_gallery ORDER BY section, sort_order, id')->fetchAll();

ny_admin_render_header('Galerie', 'gallery');
?>

<?php if ($editing || $isNew):
    $g = $editing ?: ['id' => 0, 'section' => 'studio', 'title' => '', 'alt' => '', 'file' => '', 'sort_order' => 100, 'active' => 1];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit fotografii' : 'Nová fotografie' ?></h2>
    <form method="post" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
        <div class="admin-form-row">
            <label>Sekce
                <select name="section">
                    <?php foreach ($sections as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $g['section'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Popisek (nadpis)
                <input type="text" name="title" value="<?= e((string)$g['title']) ?>" placeholder="Např. Workshop pránájáma 2024">
            </label>
            <label>Alt text (pro SEO/přístupnost)
                <input type="text" name="alt" value="<?= e((string)$g['alt']) ?>" placeholder="Popis obrázku">
            </label>
            <label>Pořadí (menší = dřív)
                <input type="number" name="sort_order" value="<?= (int)$g['sort_order'] ?>" step="10" min="0">
            </label>
            <label>Zobrazit
                <select name="active">
                    <option value="1" <?= (int)$g['active'] === 1 ? 'selected' : '' ?>>Ano – zobrazit</option>
                    <option value="0" <?= (int)$g['active'] === 0 ? 'selected' : '' ?>>Ne – skrytý</option>
                </select>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Obrázek<?= $editing ? ' (nechte prázdné pro zachování stávajícího)' : '' ?>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"<?= $editing ? '' : ' required' ?>>
            </label>
            <?php if ($editing && $g['file']): ?>
                <div class="hint">
                    Stávající: <a href="../assets/gallery/<?= e(rawurlencode($g['file'])) ?>" target="_blank"><?= e($g['file']) ?></a>
                    <div style="margin-top:8px">
                        <img src="../assets/gallery/<?= e(rawurlencode($g['file'])) ?>" alt="" style="max-width:200px;border-radius:8px">
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="gallery.php">Zrušit</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Přehled fotografií (<?= count($items) ?>)</h2>
        <a class="btn btn-primary" href="?action=new">+ Přidat fotografii</a>
    </div>
    <?php if (!$items): ?>
        <p class="hint">Žádné fotografie. Přidejte první přes tlačítko výše.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>Náhled</th><th>Sekce</th><th>Popisek</th><th>#</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $g):
                $secLabel = $sections[$g['section']] ?? $g['section'];
            ?>
                <tr>
                    <td>
                        <a href="../assets/gallery/<?= e(rawurlencode($g['file'])) ?>" target="_blank">
                            <img src="../assets/gallery/<?= e(rawurlencode($g['file'])) ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px">
                        </a>
                    </td>
                    <td><?= e($secLabel) ?></td>
                    <td><strong><?= e((string)$g['title']) ?: '<em>bez názvu</em>' ?></strong></td>
                    <td><?= (int)$g['sort_order'] ?></td>
                    <td>
                        <?php if ((int)$g['active'] === 1): ?>
                            <span class="badge badge-success">aktivní</span>
                        <?php else: ?>
                            <span class="badge">skrytý</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&id=<?= (int)$g['id'] ?>">Upravit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat fotografii?');">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
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
