<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();
$pdo = ny_db();

$statuses = [
    'pending'   => ['label' => 'Nová objednávka', 'badge' => 'badge'],
    'paid'      => ['label' => 'Zaplaceno',        'badge' => 'badge-success'],
    'issued'    => ['label' => 'Vystaveno',        'badge' => 'badge-success'],
    'redeemed'  => ['label' => 'Uplatněno',        'badge' => ''],
    'cancelled' => ['label' => 'Zrušeno',          'badge' => 'badge-danger'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $pdo->prepare('DELETE FROM ny_vouchers WHERE id = ?')->execute([$id]);
        ny_flash_set('ok', 'Poukaz byl smazán.');
        ny_redirect('vouchers.php');
    }
    if ($action === 'status' && $id) {
        $status = (string)($_POST['status'] ?? 'pending');
        if (!isset($statuses[$status])) $status = 'pending';
        $pdo->prepare('UPDATE ny_vouchers SET status = ? WHERE id = ?')->execute([$status, $id]);
        ny_flash_set('ok', 'Stav poukazu byl upraven.');
        ny_redirect('vouchers.php' . ($_POST['back'] ?? ''));
    }
    if ($action === 'save' && $id) {
        $buyerName  = trim((string)($_POST['buyer_name'] ?? ''));
        $buyerEmail = trim((string)($_POST['buyer_email'] ?? ''));
        $forWhom    = trim((string)($_POST['for_whom'] ?? ''));
        $amountRaw  = trim((string)($_POST['amount_raw'] ?? ''));
        $amountCzk  = (int)($_POST['amount_czk'] ?? 0);
        $validUntil = trim((string)($_POST['valid_until'] ?? ''));
        $note       = trim((string)($_POST['note'] ?? ''));
        $status     = (string)($_POST['status'] ?? 'pending');
        if (!isset($statuses[$status])) $status = 'pending';
        $validDate = null;
        if ($validUntil !== '') {
            $d = DateTimeImmutable::createFromFormat('Y-m-d', $validUntil);
            if ($d) $validDate = $d->format('Y-m-d');
        }
        $pdo->prepare(
            'UPDATE ny_vouchers
                SET buyer_name = ?, buyer_email = ?, for_whom = ?,
                    amount_raw = ?, amount_czk = ?, valid_until = ?, note = ?, status = ?
              WHERE id = ?'
        )->execute([$buyerName, $buyerEmail, $forWhom, $amountRaw, $amountCzk, $validDate, $note ?: null, $status, $id]);
        ny_flash_set('ok', 'Poukaz byl uložen.');
        ny_redirect('vouchers.php');
    }
    if ($action === 'new') {
        $buyerName  = trim((string)($_POST['buyer_name'] ?? ''));
        $buyerEmail = trim((string)($_POST['buyer_email'] ?? ''));
        $forWhom    = trim((string)($_POST['for_whom'] ?? ''));
        $amountRaw  = trim((string)($_POST['amount_raw'] ?? ''));
        $amountCzk  = (int)($_POST['amount_czk'] ?? 0);
        $note       = trim((string)($_POST['note'] ?? ''));
        $months     = max(1, (int)ny_setting('voucher_validity_months', '2'));
        $validUntil = (new DateTimeImmutable('today'))->modify('+' . $months . ' months')->format('Y-m-d');
        $pdo->prepare(
            'INSERT INTO ny_vouchers (code, buyer_name, buyer_email, for_whom, amount_raw, amount_czk, note, status, valid_until)
             VALUES (?, ?, ?, ?, ?, ?, ?, "issued", ?)'
        )->execute([ny_voucher_generate_code(), $buyerName, $buyerEmail, $forWhom, $amountRaw, $amountCzk, $note ?: null, $validUntil]);
        ny_flash_set('ok', 'Nový poukaz byl vytvořen.');
        ny_redirect('vouchers.php');
    }
}

$filter = (string)($_GET['f'] ?? 'active');
$where = '';
if ($filter === 'pending')  $where = "WHERE status = 'pending'";
elseif ($filter === 'active')   $where = "WHERE status IN ('pending','paid','issued')";
elseif ($filter === 'redeemed') $where = "WHERE status = 'redeemed'";
elseif ($filter === 'cancelled')$where = "WHERE status = 'cancelled'";

$rows = $pdo->query(
    "SELECT * FROM ny_vouchers $where ORDER BY created_at DESC LIMIT 500"
)->fetchAll();

$counts = $pdo->query(
    "SELECT
        COUNT(*)                                             AS total,
        SUM(status IN ('pending','paid','issued'))           AS active,
        SUM(status = 'pending')                              AS pending,
        SUM(status = 'redeemed')                             AS redeemed,
        SUM(status = 'cancelled')                            AS cancelled,
        COALESCE(SUM(CASE WHEN status IN ('pending','paid','issued') THEN amount_czk ELSE 0 END), 0) AS active_sum
     FROM ny_vouchers"
)->fetch();

$editId = (int)($_GET['id'] ?? 0);
$editing = null;
if (($_GET['action'] ?? '') === 'edit' && $editId) {
    $stmt = $pdo->prepare('SELECT * FROM ny_vouchers WHERE id = ?');
    $stmt->execute([$editId]);
    $editing = $stmt->fetch() ?: null;
}
$isNew = ($_GET['action'] ?? '') === 'new';

ny_admin_render_header('Dárkové poukazy', 'vouchers');
?>

<?php if ($editing || $isNew):
    $v = $editing ?: [
        'id' => 0, 'code' => '(vygeneruje se)', 'buyer_name' => '', 'buyer_email' => '',
        'for_whom' => '', 'amount_raw' => '', 'amount_czk' => 0,
        'status' => 'issued', 'valid_until' => null, 'note' => '', 'created_at' => null,
    ];
?>
<div class="admin-card">
    <h2><?= $editing ? 'Upravit poukaz ' . e((string)$v['code']) : 'Nový poukaz' ?></h2>
    <form method="post" class="admin-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'save' : 'new' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
        <?php endif; ?>
        <div class="admin-form-row">
            <label>Kód poukazu
                <input type="text" value="<?= e((string)$v['code']) ?>" disabled>
            </label>
            <label>Stav
                <select name="status">
                    <?php foreach ($statuses as $key => $info): ?>
                        <option value="<?= e($key) ?>" <?= (string)$v['status'] === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Objednatel – jméno
                <input type="text" name="buyer_name" value="<?= e((string)$v['buyer_name']) ?>">
            </label>
            <label>Objednatel – e-mail
                <input type="email" name="buyer_email" value="<?= e((string)$v['buyer_email']) ?>">
            </label>
            <label>Poukaz pro
                <input type="text" name="for_whom" value="<?= e((string)$v['for_whom']) ?>">
            </label>
            <label>Částka (Kč) – celé číslo pro QR
                <input type="number" name="amount_czk" min="0" value="<?= (int)$v['amount_czk'] ?>">
            </label>
            <label>Popis částky (jak zadal zákazník)
                <input type="text" name="amount_raw" value="<?= e((string)$v['amount_raw']) ?>">
            </label>
            <label>Platnost do
                <input type="date" name="valid_until" value="<?= e((string)($v['valid_until'] ?? '')) ?>">
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Interní poznámka
                <textarea name="note" rows="3"><?= e((string)($v['note'] ?? '')) ?></textarea>
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit"><?= $editing ? 'Uložit' : 'Vytvořit poukaz' ?></button>
            <a class="btn btn-ghost" href="vouchers.php">Zrušit</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="admin-card">
    <form method="get" class="row row-wrap">
        <div class="week-nav week-nav--tight">
            <a href="vouchers.php?f=active"    class="<?= $filter === 'active'    ? 'is-active' : '' ?>">Aktivní <small>(<?= (int)$counts['active'] ?>)</small></a>
            <a href="vouchers.php?f=pending"   class="<?= $filter === 'pending'   ? 'is-active' : '' ?>">Nové <small>(<?= (int)$counts['pending'] ?>)</small></a>
            <a href="vouchers.php?f=redeemed"  class="<?= $filter === 'redeemed'  ? 'is-active' : '' ?>">Uplatněné <small>(<?= (int)$counts['redeemed'] ?>)</small></a>
            <a href="vouchers.php?f=cancelled" class="<?= $filter === 'cancelled' ? 'is-active' : '' ?>">Zrušené <small>(<?= (int)$counts['cancelled'] ?>)</small></a>
            <a href="vouchers.php?f=all"       class="<?= $filter === 'all'       ? 'is-active' : '' ?>">Vše <small>(<?= (int)$counts['total'] ?>)</small></a>
        </div>
        <span class="spacer"></span>
        <a class="btn btn-primary" href="?action=new">+ Nový poukaz</a>
    </form>
</div>

<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Poukazy <span class="hint count-tag">(<?= count($rows) ?>)</span></h2>
        <div class="hint">Aktivní hodnota poukazů: <strong><?= number_format((int)$counts['active_sum'], 0, ',', ' ') ?> Kč</strong></div>
    </div>
    <?php if (!$rows): ?>
        <p class="hint">Zatím žádné poukazy.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr>
                <th>Kód</th>
                <th>Objednatel</th>
                <th>Pro koho</th>
                <th>Částka</th>
                <th>Vytvořeno</th>
                <th>Platnost do</th>
                <th>Stav</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $created = $r['created_at'] ? new DateTimeImmutable($r['created_at']) : null;
                $valid   = $r['valid_until'] ? new DateTimeImmutable($r['valid_until']) : null;
                $status  = $statuses[$r['status']] ?? ['label' => $r['status'], 'badge' => ''];
            ?>
                <tr>
                    <td data-label="Kód"><strong class="mono"><?= e((string)$r['code']) ?></strong></td>
                    <td data-label="Objednatel">
                        <?= e((string)$r['buyer_name']) ?>
                        <?php if ($r['buyer_email']): ?><br><small><?= e((string)$r['buyer_email']) ?></small><?php endif; ?>
                    </td>
                    <td data-label="Pro koho"><?= e((string)$r['for_whom']) ?: '—' ?></td>
                    <td data-label="Částka">
                        <?php if ((int)$r['amount_czk'] > 0): ?>
                            <strong><?= number_format((int)$r['amount_czk'], 0, ',', ' ') ?>&nbsp;Kč</strong>
                        <?php else: ?>
                            <?= e((string)$r['amount_raw']) ?: '—' ?>
                        <?php endif; ?>
                    </td>
                    <td data-label="Vytvořeno"><?= $created ? e($created->format('j. n. Y')) : '—' ?></td>
                    <td data-label="Platnost do"><?= $valid ? e($valid->format('j. n. Y')) : '—' ?></td>
                    <td data-label="Stav">
                        <span class="badge <?= e((string)$status['badge']) ?>"><?= e((string)$status['label']) ?></span>
                    </td>
                    <td class="actions">
                        <a class="btn btn-secondary" href="?action=edit&id=<?= (int)$r['id'] ?>">Upravit</a>
                        <?php if ($r['status'] === 'pending'): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="status" value="paid">
                                <button class="btn btn-primary" type="submit">Zaplaceno</button>
                            </form>
                        <?php elseif (in_array($r['status'], ['paid', 'issued'], true)): ?>
                            <form method="post" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="status" value="redeemed">
                                <button class="btn btn-ghost" type="submit">Uplatnit</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="inline" onsubmit="return confirm('Opravdu smazat poukaz <?= e(addslashes((string)$r['code'])) ?>?');">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
