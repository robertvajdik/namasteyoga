<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

$sections  = ny_gallery_sections();
$uploadDir = __DIR__ . '/../assets/gallery';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$filterSection = (string)($_GET['sec'] ?? 'all');
if ($filterSection !== 'all' && !isset($sections[$filterSection])) {
    $filterSection = 'all';
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

function ny_gallery_delete_row(PDO $pdo, int $id): void {
    global $uploadDir;
    $row = $pdo->prepare('SELECT file FROM ny_gallery WHERE id = ?');
    $row->execute([$id]);
    if ($file = $row->fetchColumn()) {
        @unlink($uploadDir . '/' . basename((string)$file));
    }
    $pdo->prepare('DELETE FROM ny_gallery WHERE id = ?')->execute([$id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id      = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        ny_gallery_delete_row($pdo, $id);
        ny_flash_set('ok', 'Fotografie byla smazána.');
        ny_redirect('gallery.php' . ($filterSection !== 'all' ? '?sec=' . $filterSection : ''));
    }

    if ($action === 'toggle_active' && $id) {
        $pdo->prepare('UPDATE ny_gallery SET active = 1 - active WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Viditelnost fotografie byla upravena.');
        ny_redirect('gallery.php' . ($filterSection !== 'all' ? '?sec=' . $filterSection : ''));
    }

    if ($action === 'bulk_delete') {
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $ids = array_filter($ids, static fn ($v) => $v > 0);
        foreach ($ids as $rid) ny_gallery_delete_row($pdo, (int)$rid);
        ny_flash_set('ok', count($ids) . ' fotografií smazáno.');
        ny_redirect('gallery.php' . ($filterSection !== 'all' ? '?sec=' . $filterSection : ''));
    }

    // Bulk upload (new items).
    if ($action === 'bulk_upload') {
        $section = (string)($_POST['section'] ?? 'studio');
        if (!isset($sections[$section])) $section = 'studio';
        $sortStart = (int)($_POST['sort_order'] ?? 100);
        $added = 0; $errs = [];
        $files = $_FILES['images'] ?? null;
        if ($files && is_array($files['tmp_name'])) {
            foreach ($files['tmp_name'] as $i => $tmp) {
                $one = [
                    'name'     => $files['name'][$i]     ?? '',
                    'type'     => $files['type'][$i]     ?? '',
                    'tmp_name' => $tmp,
                    'error'    => $files['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $files['size'][$i]     ?? 0,
                ];
                if ($one['error'] === UPLOAD_ERR_NO_FILE) continue;
                try {
                    $stored = ny_gallery_store_upload($one, '');
                    $pdo->prepare(
                        'INSERT INTO ny_gallery (section, title, alt, file, sort_order, active) VALUES (?, ?, ?, ?, ?, 1)'
                    )->execute([$section, '', '', $stored, $sortStart + ($added * 10)]);
                    $added++;
                } catch (Throwable $e) {
                    $errs[] = ($one['name'] ?: 'soubor') . ': ' . $e->getMessage();
                }
            }
        }
        if ($added > 0) ny_flash_set('ok', 'Nahráno ' . $added . ' fotografií.');
        if ($errs)      ny_flash_set('err', implode(' | ', $errs));
        ny_redirect('gallery.php?sec=' . $section);
    }

    // Edit / single-file create form.
    $section = (string)($_POST['section'] ?? 'studio');
    if (!isset($sections[$section])) $section = 'studio';
    $title   = trim((string)($_POST['title'] ?? ''));
    $alt     = trim((string)($_POST['alt'] ?? ''));
    $sort    = (int)($_POST['sort_order'] ?? 100);
    $active  = isset($_POST['active']) ? (int)$_POST['active'] : 1;

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
            ny_redirect('gallery.php?sec=' . $section);
        } else {
            $pdo->prepare(
                'INSERT INTO ny_gallery (section, title, alt, file, sort_order, active) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$section, $title, $alt, $file, $sort, $active]);
            ny_flash_set('ok', 'Fotografie byla přidána.');
            ny_redirect('gallery.php?sec=' . $section);
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

if ($filterSection === 'all') {
    $items = $pdo->query('SELECT * FROM ny_gallery ORDER BY section, sort_order, id')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM ny_gallery WHERE section = ? ORDER BY sort_order, id');
    $stmt->execute([$filterSection]);
    $items = $stmt->fetchAll();
}

$countsBySection = [];
foreach ($sections as $slug => $_) $countsBySection[$slug] = 0;
$rows = $pdo->query('SELECT section, COUNT(*) c FROM ny_gallery GROUP BY section')->fetchAll();
foreach ($rows as $r) $countsBySection[$r['section']] = (int)$r['c'];
$totalCount = array_sum($countsBySection);

ny_admin_render_header('Galerie', 'gallery');
?>

<?php if ($editing || $isNew):
    $g = $editing ?: ['id' => 0, 'section' => ($filterSection !== 'all' ? $filterSection : 'studio'), 'title' => '', 'alt' => '', 'file' => '', 'sort_order' => 100, 'active' => 1];
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
                <div class="gal-preview">
                    <img src="../assets/gallery/<?= e(rawurlencode($g['file'])) ?>" alt="">
                </div>
            <?php endif; ?>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="gallery.php<?= $filterSection !== 'all' ? '?sec=' . e($filterSection) : '' ?>">Zrušit</a>
            <?php if ($editing): ?>
                <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat tuto fotografii?');">
                    <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                    <button class="btn btn-danger" type="submit">Smazat fotografii</button>
                </form>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <h2>Hromadné nahrání</h2>
    <p class="hint">Vyberte více souborů najednou (Ctrl / Shift). Všechny se uloží do vybrané sekce a lze je později doplnit o popisek.</p>
    <form method="post" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="bulk_upload">
        <div class="admin-form-row">
            <label>Sekce
                <select name="section">
                    <?php foreach ($sections as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $filterSection === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Počáteční pořadí
                <input type="number" name="sort_order" value="100" step="10" min="0">
            </label>
            <label>Soubory (více najednou)
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Nahrát vybrané</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <form method="get" class="row row-wrap">
        <div class="week-nav week-nav--tight">
            <a href="gallery.php"                          class="<?= $filterSection === 'all' ? 'is-active' : '' ?>">Vše <small>(<?= (int)$totalCount ?>)</small></a>
            <?php foreach ($sections as $slug => $label): ?>
                <a href="gallery.php?sec=<?= e($slug) ?>" class="<?= $filterSection === $slug ? 'is-active' : '' ?>"><?= e($label) ?> <small>(<?= (int)$countsBySection[$slug] ?>)</small></a>
            <?php endforeach; ?>
        </div>
        <span class="spacer"></span>
        <a class="btn btn-primary" href="?action=new<?= $filterSection !== 'all' ? '&sec=' . e($filterSection) : '' ?>">+ Přidat jednu</a>
    </form>
</div>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Přehled fotografií <span class="hint count-tag">(<?= count($items) ?>)</span></h2>
    </div>
    <?php if (!$items): ?>
        <p class="hint">V této sekci zatím nejsou žádné fotografie.</p>
    <?php else: ?>
        <form method="post" id="gal-bulk">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="action" value="bulk_delete">
            <div class="row form-actions bulk-bar">
                <label class="checkbox-inline"><input type="checkbox" id="gal-check-all"> Vybrat vše</label>
                <button class="btn btn-danger" type="submit" onclick="return confirm('Smazat všechny vybrané fotografie?');">Smazat vybrané</button>
            </div>
            <div class="gal-grid">
                <?php foreach ($items as $g):
                    $secLabel = $sections[$g['section']] ?? $g['section'];
                ?>
                    <article class="gal-card <?= (int)$g['active'] === 0 ? 'is-hidden' : '' ?>">
                        <label class="gal-check">
                            <input type="checkbox" name="ids[]" value="<?= (int)$g['id'] ?>">
                        </label>
                        <a class="gal-thumb" href="../assets/gallery/<?= e(rawurlencode($g['file'])) ?>" target="_blank" rel="noopener">
                            <img src="../assets/gallery/<?= e(rawurlencode($g['file'])) ?>" alt="<?= e((string)($g['alt'] ?: $g['title'])) ?>" loading="lazy">
                        </a>
                        <div class="gal-meta">
                            <div class="gal-title"><?= $g['title'] !== '' ? e($g['title']) : '<em class="hint">bez názvu</em>' ?></div>
                            <div class="gal-sub"><?= e($secLabel) ?> · #<?= (int)$g['sort_order'] ?>
                                <?php if ((int)$g['active'] === 0): ?><span class="badge">skrytý</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="gal-actions">
                            <a class="btn btn-secondary btn-sm" href="?action=edit&id=<?= (int)$g['id'] ?>">Upravit</a>
                        </div>
                        <div class="gal-quick">
                            <button class="gal-quick-btn" formaction="gallery.php" formmethod="post" name="__submitForm" type="submit"
                                    onclick="event.preventDefault(); ny_gal_toggle(<?= (int)$g['id'] ?>);"
                                    title="<?= (int)$g['active'] === 1 ? 'Skrýt' : 'Zobrazit' ?>">
                                <?= (int)$g['active'] === 1 ? '👁' : '🚫' ?>
                            </button>
                            <button class="gal-quick-btn gal-quick-del" type="submit"
                                    onclick="event.preventDefault(); ny_gal_delete(<?= (int)$g['id'] ?>);"
                                    title="Smazat">🗑</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </form>

        <form id="gal-action-form" method="post" style="display:none">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="action" value="">
            <input type="hidden" name="id"     value="">
        </form>
        <script>
        (function () {
            var checkAll = document.getElementById('gal-check-all');
            var boxes    = document.querySelectorAll('#gal-bulk input[name="ids[]"]');
            if (checkAll) checkAll.addEventListener('change', function () {
                boxes.forEach(function (b) { b.checked = checkAll.checked; });
            });
            window.ny_gal_toggle = function (id) {
                var f = document.getElementById('gal-action-form');
                f.elements['action'].value = 'toggle_active';
                f.elements['id'].value     = id;
                f.submit();
            };
            window.ny_gal_delete = function (id) {
                if (!confirm('Opravdu smazat tuto fotografii?')) return;
                var f = document.getElementById('gal-action-form');
                f.elements['action'].value = 'delete';
                f.elements['id'].value     = id;
                f.submit();
            };
        })();
        </script>
    <?php endif; ?>
</div>

<?php ny_admin_render_footer();
