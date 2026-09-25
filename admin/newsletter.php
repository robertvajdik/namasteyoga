<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();
$pdo = ny_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM ny_newsletter_subscribers WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Odběratel byl smazán.');
    } elseif ($action === 'toggle_unsubscribe' && $id) {
        $pdo->prepare(
            'UPDATE ny_newsletter_subscribers
                SET unsubscribed_at = CASE WHEN unsubscribed_at IS NULL THEN NOW() ELSE NULL END
              WHERE id = ?'
        )->execute([$id]);
        ny_flash_set('ok', 'Stav odběru byl upraven.');
    } elseif ($action === 'add') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $name  = trim((string)($_POST['name'] ?? ''));
        try {
            ny_newsletter_subscribe($email, $name, 'admin');
            ny_flash_set('ok', 'Odběratel byl přidán / obnoven.');
        } catch (Throwable $e) {
            ny_flash_set('err', $e->getMessage());
        }
    }
    ny_redirect('newsletter.php');
}

// CSV export.
if (($_GET['export'] ?? '') === 'csv') {
    ny_require_admin();
    $rows = $pdo->query(
        'SELECT email, name, source, created_at, confirmed_at, unsubscribed_at
           FROM ny_newsletter_subscribers ORDER BY created_at DESC'
    )->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel
    fputcsv($out, ['E-mail', 'Jméno', 'Zdroj', 'Přihlášen', 'Potvrzen', 'Odhlášen'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['email'], $r['name'], $r['source'],
            $r['created_at'], $r['confirmed_at'] ?? '', $r['unsubscribed_at'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

$filter = (string)($_GET['f'] ?? 'active');
if (!in_array($filter, ['all', 'active', 'unsubscribed'], true)) {
    $filter = 'active';
}
$where = '';
if ($filter === 'active')       $where = 'WHERE unsubscribed_at IS NULL';
elseif ($filter === 'unsubscribed') $where = 'WHERE unsubscribed_at IS NOT NULL';

$rows = $pdo->query(
    "SELECT * FROM ny_newsletter_subscribers $where
     ORDER BY created_at DESC LIMIT 500"
)->fetchAll();

$counts = $pdo->query(
    'SELECT
        COUNT(*)                                          AS total,
        SUM(unsubscribed_at IS NULL)                      AS active,
        SUM(unsubscribed_at IS NOT NULL)                  AS unsubscribed
     FROM ny_newsletter_subscribers'
)->fetch();

ny_admin_render_header('Newsletter', 'newsletter');
?>

<div class="admin-card">
    <h2>Přidat odběratele</h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="add">
        <div class="admin-form-row">
            <label>E-mail
                <input type="email" name="email" required>
            </label>
            <label>Jméno (nepovinné)
                <input type="text" name="name">
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Přidat</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <form method="get" class="row row-wrap">
        <div class="week-nav week-nav--tight">
            <a href="newsletter.php"                       class="<?= $filter === 'active'       ? 'is-active' : '' ?>">Aktivní <small>(<?= (int)$counts['active'] ?>)</small></a>
            <a href="newsletter.php?f=unsubscribed"        class="<?= $filter === 'unsubscribed' ? 'is-active' : '' ?>">Odhlášení <small>(<?= (int)$counts['unsubscribed'] ?>)</small></a>
            <a href="newsletter.php?f=all"                 class="<?= $filter === 'all'          ? 'is-active' : '' ?>">Vše <small>(<?= (int)$counts['total'] ?>)</small></a>
        </div>
        <span class="spacer"></span>
        <a class="btn btn-secondary" href="newsletter.php?export=csv">Export CSV</a>
    </form>
</div>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Odběratelé <span class="hint count-tag">(<?= count($rows) ?>)</span></h2>
    </div>
    <?php if (!$rows): ?>
        <p class="hint">Zatím žádní odběratelé.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr>
                <th>E-mail</th>
                <th>Jméno</th>
                <th>Zdroj</th>
                <th>Přihlášen</th>
                <th>Stav</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $created = $r['created_at'] ? new DateTimeImmutable($r['created_at']) : null;
                $unsub   = !empty($r['unsubscribed_at']);
            ?>
                <tr>
                    <td data-label="E-mail"><strong><?= e($r['email']) ?></strong></td>
                    <td data-label="Jméno"><?= e((string)($r['name'] ?? '')) ?: '—' ?></td>
                    <td data-label="Zdroj"><?= e($r['source']) ?></td>
                    <td data-label="Přihlášen"><?= $created ? e($created->format('j. n. Y')) : '—' ?></td>
                    <td data-label="Stav">
                        <?php if ($unsub): ?>
                            <span class="badge badge-danger">odhlášen</span>
                        <?php else: ?>
                            <span class="badge badge-success">aktivní</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="toggle_unsubscribe">
                            <button class="btn btn-secondary" type="submit">
                                <?= $unsub ? 'Obnovit' : 'Odhlásit' ?>
                            </button>
                        </form>
                        <form method="post" class="inline" onsubmit="return confirm('Smazat záznam <?= e(addslashes($r['email'])) ?>?');">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="delete">
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
