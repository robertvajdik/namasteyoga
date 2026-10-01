<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();

$pdo    = ny_db();
$action = (string)($_GET['action'] ?? $_POST['action'] ?? '');
$editId = (int)($_GET['id'] ?? 0);

$uploadDir = __DIR__ . '/../assets/events';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

function ny_event_slugify(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function ny_event_store_image(array $file, string $existing = ''): string {
    global $uploadDir;
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return $existing;
    }
    $mime = @mime_content_type($file['tmp_name']) ?: '';
    $extByMime = [
        'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extByMime[$mime])) {
        throw new RuntimeException('Nepodporovaný typ obrázku. Povoleno: JPG, PNG, WEBP.');
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Obrázek je příliš velký (max 8 MB).');
    }
    $name = bin2hex(random_bytes(6)) . '.' . $extByMime[$mime];
    $dest = $uploadDir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Obrázek se nepodařilo uložit.');
    }
    if ($existing !== '' && $existing !== $name && $existing !== 'puppy.png') {
        @unlink($uploadDir . '/' . basename($existing));
    }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id         = (int)($_POST['id'] ?? 0);
    $slug       = ny_event_slugify((string)($_POST['slug'] ?? ''));
    $title      = trim((string)($_POST['title'] ?? ''));
    $subtitle   = trim((string)($_POST['subtitle'] ?? ''));
    $summary    = trim((string)($_POST['summary'] ?? '')) ?: null;
    $body       = trim((string)($_POST['body'] ?? '')) ?: null;
    $eventDate  = trim((string)($_POST['event_date'] ?? '')) ?: null;
    $eventTime  = trim((string)($_POST['event_time'] ?? ''));
    $location   = trim((string)($_POST['location'] ?? ''));
    $price      = trim((string)($_POST['price'] ?? ''));
    $ctaLabel   = trim((string)($_POST['cta_label'] ?? ''));
    $ctaUrl     = trim((string)($_POST['cta_url'] ?? ''));
    $sort       = (int)($_POST['sort_order'] ?? 100);
    $published  = isset($_POST['is_published']) ? (int)$_POST['is_published'] : 1;

    if ($action === 'delete' && $id) {
        $row = $pdo->prepare('SELECT image FROM ny_events WHERE id = ?');
        $row->execute([$id]);
        $img = (string)($row->fetchColumn() ?: '');
        if ($img !== '' && $img !== 'puppy.png') {
            @unlink($uploadDir . '/' . basename($img));
        }
        $pdo->prepare('DELETE FROM ny_events WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Akce byla smazána.');
        ny_redirect('events.php');
    }

    if ($action === 'remove_image' && $id) {
        $row = $pdo->prepare('SELECT image FROM ny_events WHERE id = ?');
        $row->execute([$id]);
        $img = (string)($row->fetchColumn() ?: '');
        if ($img !== '' && $img !== 'puppy.png') {
            @unlink($uploadDir . '/' . basename($img));
        }
        $pdo->prepare('UPDATE ny_events SET image = "" WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Obrázek byl odstraněn.');
        ny_redirect('events.php?action=edit&id=' . $id);
    }

    try {
        if ($title === '') {
            throw new RuntimeException('Vyplňte název akce.');
        }
        if ($slug === '') {
            $slug = ny_event_slugify($title);
        }
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new RuntimeException('Slug musí obsahovat jen malá písmena, čísla nebo pomlčky.');
        }
        if ($eventDate !== null && !DateTimeImmutable::createFromFormat('Y-m-d', $eventDate)) {
            throw new RuntimeException('Datum musí být ve formátu RRRR-MM-DD.');
        }

        $dupe = $pdo->prepare('SELECT id FROM ny_events WHERE slug = ? AND id <> ? LIMIT 1');
        $dupe->execute([$slug, $id]);
        if ($dupe->fetch()) {
            throw new RuntimeException('Slug „' . $slug . '" už existuje – zvolte jiný.');
        }

        $existing = '';
        if ($id) {
            $row = $pdo->prepare('SELECT image FROM ny_events WHERE id = ?');
            $row->execute([$id]);
            $existing = (string)($row->fetchColumn() ?: '');
        }
        $image = ny_event_store_image($_FILES['image'] ?? [], $existing);

        if ($id) {
            $pdo->prepare(
                'UPDATE ny_events
                    SET slug = ?, title = ?, subtitle = ?, summary = ?, body = ?,
                        image = ?, event_date = ?, event_time = ?, location = ?,
                        price = ?, cta_label = ?, cta_url = ?, sort_order = ?, is_published = ?
                  WHERE id = ?'
            )->execute([
                $slug, $title, $subtitle, $summary, $body,
                $image, $eventDate, $eventTime, $location,
                $price, $ctaLabel, $ctaUrl, $sort, $published,
                $id,
            ]);
            ny_flash_set('ok', 'Akce byla uložena.');
        } else {
            $pdo->prepare(
                'INSERT INTO ny_events
                    (slug, title, subtitle, summary, body, image, event_date, event_time,
                     location, price, cta_label, cta_url, sort_order, is_published)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $slug, $title, $subtitle, $summary, $body, $image, $eventDate, $eventTime,
                $location, $price, $ctaLabel, $ctaUrl, $sort, $published,
            ]);
            ny_flash_set('ok', 'Akce byla vytvořena.');
        }
        ny_redirect('events.php');
    } catch (Throwable $e) {
        ny_flash_set('err', $e->getMessage());
    }
}

$editing = null;
if ($action === 'edit' && $editId) {
    $stmt = $pdo->prepare('SELECT * FROM ny_events WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = $action === 'new';

$events = $pdo->query(
    'SELECT * FROM ny_events
      ORDER BY is_published DESC,
               CASE WHEN event_date IS NULL OR event_date >= CURDATE() THEN 0 ELSE 1 END,
               CASE WHEN event_date >= CURDATE() THEN event_date END ASC,
               CASE WHEN event_date <  CURDATE() THEN event_date END DESC,
               sort_order, id'
)->fetchAll();

ny_admin_render_header('Akce', 'events');
?>

<?php if ($editing || $isNew):
    $ev = $editing ?: [
        'id' => 0, 'slug' => '', 'title' => '', 'subtitle' => '', 'summary' => '', 'body' => '',
        'image' => '', 'event_date' => null, 'event_time' => '', 'location' => '',
        'price' => '', 'cta_label' => '', 'cta_url' => '', 'sort_order' => 100, 'is_published' => 1,
    ];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit akci' : 'Nová akce' ?></h2>
    <form method="post" class="admin-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
        <div class="admin-form-row">
            <label>Název akce
                <input type="text" name="title" value="<?= e((string)$ev['title']) ?>" required>
            </label>
            <label>Podtitul (volitelné)
                <input type="text" name="subtitle" value="<?= e((string)$ev['subtitle']) ?>">
            </label>
            <label>Slug (v URL)
                <input type="text" name="slug" value="<?= e((string)$ev['slug']) ?>" placeholder="např. puppy-vibe">
                <small class="hint hint-inline">Nechte prázdné – vygenerujeme z názvu.</small>
            </label>
            <label>Pořadí (menší = dřív)
                <input type="number" name="sort_order" value="<?= (int)$ev['sort_order'] ?>" step="10" min="0">
            </label>
            <label>Stav
                <select name="is_published">
                    <option value="1" <?= (int)$ev['is_published'] === 1 ? 'selected' : '' ?>>Publikováno</option>
                    <option value="0" <?= (int)$ev['is_published'] === 0 ? 'selected' : '' ?>>Koncept (skryté)</option>
                </select>
            </label>
        </div>
        <div class="admin-form-row">
            <label>Datum (volitelné)
                <input type="date" name="event_date" value="<?= e((string)($ev['event_date'] ?? '')) ?>">
            </label>
            <label>Čas / délka (volný text)
                <input type="text" name="event_time" value="<?= e((string)$ev['event_time']) ?>" placeholder="např. 18:00 – 19:30">
            </label>
            <label>Místo
                <input type="text" name="location" value="<?= e((string)$ev['location']) ?>" placeholder="Studio Namasté">
            </label>
            <label>Cena
                <input type="text" name="price" value="<?= e((string)$ev['price']) ?>" placeholder="např. 450 Kč">
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Krátký popis (do výpisu)
                <textarea name="summary" rows="2"><?= e((string)($ev['summary'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Celý popis (HTML povoleno)
                <textarea name="body" rows="10"><?= e((string)($ev['body'] ?? '')) ?></textarea>
                <small class="hint">Běžné HTML tagy (p, h3, ul/li, strong, a). Zobrazí se na detailu akce.</small>
            </label>
        </div>
        <div class="admin-form-row">
            <label>Tlačítko – text
                <input type="text" name="cta_label" value="<?= e((string)$ev['cta_label']) ?>" placeholder="např. Rezervovat">
            </label>
            <label>Tlačítko – odkaz
                <input type="text" name="cta_url" value="<?= e((string)$ev['cta_url']) ?>" placeholder="rezervace.php nebo https://…">
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Obrázek akce<?= $editing && !empty($ev['image']) ? ' (nechte prázdné pro zachování stávajícího)' : '' ?>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <small class="hint">JPG/PNG/WEBP, doporučeno šířka min. 1200 px. Max 8 MB.</small>
            </label>
            <?php if (!empty($ev['image'])): ?>
                <div class="teacher-photo-preview">
                    <img src="../assets/events/<?= e(rawurlencode((string)$ev['image'])) ?>" alt="<?= e((string)$ev['title']) ?>">
                    <form method="post" class="inline" onsubmit="return confirm('Odstranit obrázek?');">
                        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                        <input type="hidden" name="action" value="remove_image">
                        <button class="btn btn-ghost btn-sm" type="submit">Odstranit obrázek</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit</button>
            <a class="btn btn-ghost" href="events.php">Zrušit</a>
            <?php if ($editing): ?>
                <a class="btn btn-secondary" href="../akce.php?slug=<?= e(rawurlencode((string)$ev['slug'])) ?>" target="_blank" rel="noopener">Zobrazit na webu</a>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Přehled akcí</h2>
        <a class="btn btn-primary" href="?action=new">+ Nová akce</a>
    </div>
    <p class="hint hint-lift">
        Zobrazí se jako výpis na stránce <a href="../akce.php" target="_blank" rel="noopener">/akce.php</a>.
    </p>
    <?php if (!$events): ?>
        <p class="hint">Žádné akce.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>Obrázek</th><th>Datum</th><th>Název</th><th>Slug</th><th>Pořadí</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($events as $ev):
                $dt = $ev['event_date']
                    ? DateTimeImmutable::createFromFormat('Y-m-d', (string)$ev['event_date'])
                    : null;
                $past = $ev['event_date'] && (string)$ev['event_date'] < date('Y-m-d');
            ?>
                <tr>
                    <td data-label="Obrázek">
                        <?php if (!empty($ev['image'])): ?>
                            <img src="../assets/events/<?= e(rawurlencode((string)$ev['image'])) ?>" alt="" class="teacher-thumb">
                        <?php else: ?>
                            <span class="teacher-thumb teacher-thumb--placeholder"><?= e(mb_substr((string)$ev['title'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Datum">
                        <?= $dt ? e($dt->format('j. n. Y')) : '<span class="hint">—</span>' ?>
                        <?= $past ? ' <span class="badge">proběhlo</span>' : '' ?>
                    </td>
                    <td data-label="Název"><strong><?= e((string)$ev['title']) ?></strong>
                        <?php if (!empty($ev['subtitle'])): ?><br><small class="hint"><?= e((string)$ev['subtitle']) ?></small><?php endif; ?>
                    </td>
                    <td data-label="Slug"><code><?= e((string)$ev['slug']) ?></code></td>
                    <td data-label="Pořadí"><?= (int)$ev['sort_order'] ?></td>
                    <td data-label="Stav">
                        <?php if ((int)$ev['is_published'] === 1): ?>
                            <span class="badge badge-success">publikováno</span>
                        <?php else: ?>
                            <span class="badge">koncept</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&id=<?= (int)$ev['id'] ?>">Upravit</a>
                        <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat akci?');">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
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
