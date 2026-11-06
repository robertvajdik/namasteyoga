<?php
declare(strict_types=1);
require __DIR__ . '/../_app.php';
$admin = require_admin();

$inv = find_invoice((int)($_GET['id'] ?? 0));
if (!$inv) {
    flash('Faktura nenalezena.', 'error');
    redirect('/admin/faktury');
}

if (is_post()) {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'send') {
        $to = trim((string)($_POST['to'] ?? '')) ?: $inv['customer_email'];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('Zadejte platný e-mail.', 'error');
        } else {
            $ok = invoice_send($inv, $to);
            flash($ok ? "Faktura odeslána na $to." : 'E-mail se nepodařilo odeslat (funkce mail() selhala).', $ok ? 'ok' : 'error');
        }
    } elseif ($action === 'paid') {
        db()->prepare("UPDATE invoices SET status = 'zaplacena', paid_at = CURDATE() WHERE id = ? AND status <> 'stornovana'")->execute([$inv['id']]);
        flash('Faktura označena jako zaplacená.');
    } elseif ($action === 'unpaid') {
        db()->prepare("UPDATE invoices SET status = IF(sent_at IS NULL, 'vystavena', 'odeslana'), paid_at = NULL WHERE id = ? AND status = 'zaplacena'")->execute([$inv['id']]);
        flash('Faktura vrácena mezi nezaplacené.');
    } elseif ($action === 'storno') {
        db()->prepare("UPDATE invoices SET status = 'stornovana' WHERE id = ?")->execute([$inv['id']]);
        flash('Faktura stornována. K objednávce můžete vystavit novou.');
    }
    redirect('/admin/faktura?id=' . (int)$inv['id']);
}

$f = flash();
$order = null;
if ($inv['order_id']) {
    $st = db()->prepare('SELECT id, number FROM orders WHERE id = ?');
    $st->execute([$inv['order_id']]);
    $order = $st->fetch() ?: null;
}
$status = invoice_statuses()[$inv['status']] ?? $inv['status'];
$overdue = in_array($inv['status'], ['vystavena', 'odeslana'], true) && $inv['due'] < date('Y-m-d');
$post = fn(string $action, string $label, string $class = '') => '<form method="post">' . csrf_field()
    . '<input type="hidden" name="action" value="' . $action . '"><button type="submit" class="' . $class . '">' . e($label) . '</button></form>';

ob_start(); ?>
<div class="inv-bar">
  <a href="/admin/faktury">← Faktury</a>
  <?php if ($order): ?><a href="/admin/objednavka?id=<?= (int)$order['id'] ?>">Objednávka <?= e($order['number']) ?></a><?php endif; ?>
  <?php if (!empty($inv['preorder_id'])): ?><a href="/admin/predobjednavky?view=done">Předobjednávky</a><?php endif; ?>
  <button type="button" class="primary" data-print>Tisk / uložit PDF</button>
  <?php if ($inv['status'] !== 'stornovana'): ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="send">
      <input id="to" type="email" name="to" value="<?= e($inv['customer_email']) ?>" aria-label="Poslat na e-mail">
      <button type="submit"><?= $inv['sent_at'] ? 'Poslat znovu' : 'Odeslat e-mailem' ?></button>
    </form>
    <?= $inv['status'] === 'zaplacena' ? $post('unpaid', 'Zrušit „zaplaceno“') : $post('paid', 'Označit zaplaceno') ?>
  <?php endif; ?>
  <details>
    <summary>Další ▾</summary>
    <div class="menu">
      <a href="<?= e(invoice_public_url($inv)) ?>" target="_blank" rel="noopener">Odkaz pro zákazníka</a>
      <?php if ($inv['status'] !== 'stornovana'): ?><?= $post('storno', 'Stornovat fakturu', 'danger') ?><?php endif; ?>
    </div>
  </details>
  <span class="note">
    <?= e($status) ?><?= $overdue ? ' · po splatnosti' : '' ?>
    <?= $inv['sent_at'] ? ' · odesláno ' . e(date('j. n. Y H:i', strtotime($inv['sent_at']))) : '' ?>
    <?= $inv['paid_at'] ? ' · zaplaceno ' . e(date('j. n. Y', strtotime($inv['paid_at']))) : '' ?>
  </span>
</div>
<?php if ($f): ?><div class="inv-flash <?= $f['type'] === 'error' ? 'error' : '' ?>" role="status"><?= e($f['msg']) ?></div><?php endif; ?>
<script nonce="<?= e(CSP_NONCE) ?>">document.querySelector('[data-print]').addEventListener('click', () => window.print());</script>
<?php
echo invoice_html($inv, (string)ob_get_clean());
