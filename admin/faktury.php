<?php
declare(strict_types=1);
require __DIR__ . '/../_app.php';
$admin = require_admin();

if (is_post()) {
    csrf_check();
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $action = (string)($_POST['bulk'] ?? '');
    if ($ids && $action === 'paid') {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("UPDATE invoices SET status = 'zaplacena', paid_at = CURDATE() WHERE id IN ($in) AND status IN ('vystavena', 'odeslana')");
        $st->execute($ids);
        flash('Označeno jako zaplacené: ' . $st->rowCount());
    } elseif ($ids && $action === 'send') {
        $ok = $fail = 0;
        foreach ($ids as $id) {
            $inv = find_invoice($id);
            if (!$inv || $inv['status'] === 'stornovana') continue;
            invoice_send($inv) ? $ok++ : $fail++;
        }
        flash("Odesláno faktur: $ok" . ($fail ? ", nepodařilo se: $fail (chybí e-mail nebo selhalo odeslání)" : ''), $fail && !$ok ? 'error' : 'ok');
    }
    redirect('/admin/faktury' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
}

$status = array_key_exists($_GET['status'] ?? '', invoice_statuses()) || ($_GET['status'] ?? '') === 'overdue' ? $_GET['status'] : '';
$month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? $_GET['m'] : '';
$q = trim((string)($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if ($status === 'overdue') { $where[] = "i.status IN ('vystavena', 'odeslana') AND i.due < CURDATE()"; }
elseif ($status) { $where[] = 'i.status = ?'; $params[] = $status; }
if ($month) { $where[] = "DATE_FORMAT(i.issued, '%Y-%m') = ?"; $params[] = $month; }
if ($q !== '') { $where[] = '(i.number LIKE ? OR i.vs LIKE ? OR i.customer_name LIKE ? OR i.customer_email LIKE ? OR i.customer_ico LIKE ?)'; array_push($params, ...array_fill(0, 5, "%$q%")); }
$st = db()->prepare('SELECT i.*, o.number AS order_number, r.number AS preorder_number FROM invoices i LEFT JOIN orders o ON o.id = i.order_id LEFT JOIN preorders r ON r.id = i.preorder_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id DESC LIMIT 500');
$st->execute($params);
$invoices = $st->fetchAll();
$counts = db()->query('SELECT status, COUNT(*) FROM invoices GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$overdueCount = (int)db()->query("SELECT COUNT(*) FROM invoices WHERE status IN ('vystavena', 'odeslana') AND due < CURDATE()")->fetchColumn();
$sum = array_sum(array_map(fn($i) => $i['status'] === 'stornovana' ? 0 : (int)$i['total'], $invoices));
$months = db()->query("SELECT DISTINCT DATE_FORMAT(issued, '%Y-%m') FROM invoices ORDER BY 1 DESC LIMIT 36")->fetchAll(PDO::FETCH_COLUMN);
$monthNames = [1 => 'leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec'];

$title = 'Faktury';
$section = 'invoices';
$exportUrl = '/admin/export?type=invoices' . ($month ? '&m=' . $month : '') . ($status && $status !== 'overdue' ? '&status=' . $status : '');
$headerAction = '<a class="btn btn-ghost" href="' . e($exportUrl) . '">' . icon('download') . ' Export do Excelu</a>';
require APP_DIR . '/views/admin-header.php';
?>
<?php if (!supplier_ready()): ?>
  <p class="alert alert-warn">Před vystavením první faktury doplňte údaje dodavatele (název, adresa, IČO) v <a href="/admin/nastaveni#fakturace">Nastavení › Fakturace</a>.</p>
<?php endif; ?>
<nav class="tabs" aria-label="Stav faktury">
  <a href="/admin/faktury<?= $month ? '?m=' . e($month) : '' ?>" <?= $status === '' ? 'aria-current="page"' : '' ?>>Vše <span><?= array_sum($counts) ?></span></a>
  <?php foreach (invoice_statuses() as $k => $label): ?>
    <a href="/admin/faktury?status=<?= e($k) ?><?= $month ? '&amp;m=' . e($month) : '' ?>" <?= $status === $k ? 'aria-current="page"' : '' ?>><?= e($label) ?> <span><?= (int)($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
  <a href="/admin/faktury?status=overdue" <?= $status === 'overdue' ? 'aria-current="page"' : '' ?>>Po splatnosti <span><?= $overdueCount ?></span></a>
</nav>
<form class="toolbar" method="get">
  <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
  <label class="sr-only" for="im">Měsíc</label>
  <select id="im" name="m" data-autosubmit>
    <option value="">Všechny měsíce</option>
    <?php foreach ($months as $m): [$y, $mm] = explode('-', $m); ?>
      <option value="<?= e($m) ?>" <?= $month === $m ? 'selected' : '' ?>><?= e(ucfirst($monthNames[(int)$mm]) . ' ' . $y) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="sr-only" for="iq">Hledat</label>
  <input id="iq" type="search" name="q" value="<?= e($q) ?>" placeholder="Číslo, VS, odběratel, IČO…">
  <button class="btn btn-ghost" type="submit">Hledat</button>
</form>

<form method="post" class="panel" data-bulk>
  <?= csrf_field() ?>
  <div class="bulk-bar">
    <span class="muted"><?= count($invoices) ?> faktur · <?= money($sum) ?></span>
    <label class="sr-only" for="bulk">Hromadná akce</label>
    <select id="bulk" name="bulk">
      <option value="">Hromadná akce…</option>
      <option value="send">Poslat zákazníkům e-mailem</option>
      <option value="paid">Označit jako zaplacené</option>
    </select>
    <button class="btn btn-ghost btn-sm" type="submit" data-confirm="Opravdu provést hromadnou akci?">Provést</button>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <th class="col-check"><input type="checkbox" data-check-all aria-label="Vybrat vše"></th>
        <th>Číslo</th><th>Vystaveno</th><th>Odběratel</th><th>Objednávka</th><th>Splatnost</th><th class="num">Celkem</th><th>Stav</th>
      </tr></thead>
      <tbody>
      <?php foreach ($invoices as $i): $late = in_array($i['status'], ['vystavena', 'odeslana'], true) && $i['due'] < date('Y-m-d'); ?>
        <tr>
          <td class="col-check"><input type="checkbox" name="ids[]" value="<?= (int)$i['id'] ?>" aria-label="Vybrat <?= e($i['number']) ?>"></td>
          <td><a href="/admin/faktura?id=<?= (int)$i['id'] ?>"><strong><?= e($i['number']) ?></strong></a></td>
          <td><?= e(date('j. n. Y', strtotime($i['issued']))) ?></td>
          <td><?= e($i['customer_name']) ?><?= $i['customer_ico'] ? ' <span class="muted small">IČO ' . e($i['customer_ico']) . '</span>' : '' ?><br><span class="muted small"><?= e($i['customer_email']) ?></span></td>
          <td><?= $i['order_id'] && $i['order_number'] ? '<a href="/admin/objednavka?id=' . (int)$i['order_id'] . '">' . e($i['order_number']) . '</a>'
            : ($i['preorder_number'] ? '<a href="/admin/predobjednavky?view=done">' . e($i['preorder_number']) . '</a>' : '—') ?></td>
          <td class="<?= $late ? 'warn' : '' ?>"><?= e(date('j. n. Y', strtotime($i['due']))) ?><?= $late ? '<br><span class="small">po splatnosti</span>' : '' ?></td>
          <td class="num"><?= $i['status'] === 'stornovana' ? '<s>' . money($i['total']) . '</s>' : money($i['total']) ?></td>
          <td><span class="status status-inv-<?= e($i['status']) ?>"><?= e(invoice_statuses()[$i['status']] ?? $i['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?><tr><td colspan="8" class="empty-note">Žádné faktury. Fakturu vystavíte v detailu objednávky, nebo hromadně v seznamu objednávek.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</form>
<?php require APP_DIR . '/views/admin-footer.php'; ?>
