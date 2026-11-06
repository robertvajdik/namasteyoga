<?php
declare(strict_types=1);
require __DIR__ . '/../_app.php';
require_once APP_DIR . '/xlsx.php';
$admin = require_admin();

$date = fn(string $k, string $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET[$k] ?? '')) ? $_GET[$k] : $def;
$from = $date('from', date('Y-m-01'));
$to = $date('to', date('Y-m-d'));
if ($from > $to) [$from, $to] = [$to, $from];
if (preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? ''))) {
    $from = $_GET['m'] . '-01';
    $to = date('Y-m-t', strtotime($from));
}
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];
$suffix = $from === $to ? $from : "{$from}_{$to}";
$cfg = config();
$type = (string)($_GET['type'] ?? '');

if ($type === 'orders') {
    $status = array_key_exists($_GET['status'] ?? '', order_statuses()) ? $_GET['status'] : '';
    $sql = 'SELECT o.*, (SELECT SUM(qty) FROM order_items i WHERE i.order_id = o.id) AS pieces,
            (SELECT number FROM invoices f WHERE f.order_id = o.id AND f.status <> \'stornovana\' ORDER BY f.id DESC LIMIT 1) AS invoice
        FROM orders o WHERE o.created_at BETWEEN ? AND ?' . ($status ? ' AND o.status = ?' : '') . ' ORDER BY o.created_at';
    $st = db()->prepare($sql);
    $st->execute($status ? [...$range, $status] : $range);
    $rows = [];
    foreach ($st->fetchAll() as $o) {
        $rows[] = [$o['number'], $o['created_at'], order_statuses()[$o['status']] ?? $o['status'], $o['name'], $o['email'], $o['phone'],
            $o['street'], $o['zip'], $o['city'], $cfg['shipping'][$o['shipping']]['label'] ?? $o['shipping'], $cfg['payment'][$o['payment']]['label'] ?? $o['payment'],
            (int)$o['pieces'], (int)$o['items_total'], $o['voucher_code'], (int)$o['discount'], (int)$o['shipping_price'], (int)$o['total'], $o['invoice'], $o['note']];
    }
    $st = db()->prepare('SELECT o.number, o.created_at, i.name, i.size, i.product_id, i.price, i.qty FROM order_items i JOIN orders o ON o.id = i.order_id
        WHERE o.created_at BETWEEN ? AND ?' . ($status ? ' AND o.status = ?' : '') . ' ORDER BY o.created_at, i.id');
    $st->execute($status ? [...$range, $status] : $range);
    $items = array_map(fn($i) => [$i['number'], $i['created_at'], $i['name'], $i['size'], $i['product_id'], (int)$i['price'], (int)$i['qty'], $i['price'] * $i['qty']], $st->fetchAll());
    xlsx_download("objednavky-$suffix.xlsx", [
        'Objednávky' => ['cols' => [['Číslo', 's', 10], ['Datum', 't', 16], ['Stav', 's', 14], ['Jméno', 's', 22], ['E-mail', 's', 26], ['Telefon', 's', 15],
            ['Ulice', 's', 22], ['PSČ', 's', 8], ['Město', 's', 16], ['Doprava', 's', 24], ['Platba', 's', 18], ['Kusů', 'n', 7],
            ['Zboží', 'n', 10], ['Slevový kód', 's', 12], ['Sleva', 'n', 9], ['Doprava a platba', 'n', 10], ['Celkem', 'n', 11], ['Faktura', 's', 11], ['Poznámka', 's', 30]],
            'rows' => $rows, 'sum' => ['Kusů', 'Zboží', 'Sleva', 'Doprava a platba', 'Celkem']],
        'Položky' => ['cols' => [['Objednávka', 's', 11], ['Datum', 't', 16], ['Produkt', 's', 32], ['Velikost', 's', 9], ['ID produktu', 'n', 10],
            ['Cena za ks', 'n', 10], ['Ks', 'n', 6], ['Celkem', 'n', 11]], 'rows' => $items, 'sum' => ['Ks', 'Celkem']],
    ]);
}

if ($type === 'invoices') {
    $status = array_key_exists($_GET['status'] ?? '', invoice_statuses()) ? $_GET['status'] : '';
    $noStorno = !$status && ($_GET['storno'] ?? '') !== '1';
    $st = db()->prepare('SELECT i.*, o.number AS order_number FROM invoices i LEFT JOIN orders o ON o.id = i.order_id WHERE i.issued BETWEEN ? AND ?'
        . ($status ? ' AND i.status = ?' : '') . ($noStorno ? " AND i.status <> 'stornovana'" : '') . ' ORDER BY i.issued, i.number');
    $st->execute($status ? [$from, $to, $status] : [$from, $to]);
    $rows = [];
    foreach ($st->fetchAll() as $i) {
        $v = invoice_vat($i);
        $rows[] = [$i['number'], $i['issued'], $i['taxable'], $i['due'], $i['vs'], $i['order_number'], $i['customer_name'], $i['customer_ico'], $i['customer_dic'],
            $i['customer_email'], trim($i['customer_street'] . ', ' . $i['customer_zip'] . ' ' . $i['customer_city'], ', '), $i['payment'],
            $v['rate'], $v['base'], $v['vat'], $v['total'], invoice_statuses()[$i['status']] ?? $i['status'], $i['paid_at'], $i['sent_at']];
    }
    xlsx_download("faktury-$suffix.xlsx", [
        'Faktury' => ['cols' => [['Číslo', 's', 11], ['Vystaveno', 'd', 11], ['DUZP', 'd', 11], ['Splatnost', 'd', 11], ['VS', 's', 10], ['Objednávka', 's', 11],
            ['Odběratel', 's', 24], ['IČO', 's', 10], ['DIČ', 's', 12], ['E-mail', 's', 26], ['Adresa', 's', 32], ['Forma úhrady', 's', 18],
            ['Sazba DPH %', 'n', 8], ['Základ', 'm', 12], ['DPH', 'm', 11], ['Celkem', 'm', 12], ['Stav', 's', 12], ['Zaplaceno', 'd', 11], ['Odesláno', 't', 16]],
            'rows' => $rows, 'sum' => ['Základ', 'DPH', 'Celkem']],
    ]);
}

if ($type === 'products') {
    $all = ($_GET['all'] ?? '') === '1';
    $list = db()->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id'
        . ($all ? '' : ' WHERE p.is_active = 1') . ' ORDER BY c.sort, p.name')->fetchAll();
    products_xlsx_download($list, 'produkty-' . date('Y-m-d') . '.xlsx');
}

if ($type === 'reservations') {
    $st = db()->prepare('SELECT * FROM reservations WHERE created_at BETWEEN ? AND ? ORDER BY created_at');
    $st->execute($range);
    $rows = array_map(fn($r) => [$r['number'], $r['created_at'], $r['pickup_date'], reservation_statuses()[$r['status']] ?? $r['status'], $r['product_name'], $r['size'],
        (int)$r['price'], $r['name'], $r['email'], $r['phone'], $r['note']], $st->fetchAll());
    xlsx_download("rezervace-$suffix.xlsx", [
        'Rezervace' => ['cols' => [['Číslo', 's', 10], ['Vytvořeno', 't', 16], ['Vyzvednutí', 'd', 11], ['Stav', 's', 22], ['Produkt', 's', 30], ['Velikost', 's', 9],
            ['Cena', 'n', 9], ['Jméno', 's', 22], ['E-mail', 's', 26], ['Telefon', 's', 15], ['Poznámka', 's', 30]], 'rows' => $rows, 'sum' => ['Cena']],
    ]);
}

if ($type === 'preorders') {
    $st = db()->prepare('SELECT r.*, s.title AS stream FROM preorders r LEFT JOIN live_streams s ON s.id = r.stream_id WHERE r.created_at BETWEEN ? AND ? ORDER BY r.created_at');
    $st->execute($range);
    $rows = array_map(fn($r) => [$r['number'], $r['created_at'], preorder_statuses()[$r['status']] ?? $r['status'], $r['product_name'], $r['size'], (int)$r['qty'],
        (int)$r['price'], $r['price'] * $r['qty'], preorder_deliveries()[$r['delivery']] ?? $r['delivery'], $r['name'], $r['email'], $r['phone'], $r['stream'], $r['note']], $st->fetchAll());
    xlsx_download("predobjednavky-$suffix.xlsx", [
        'Předobjednávky' => ['cols' => [['Číslo', 's', 10], ['Vytvořeno', 't', 16], ['Stav', 's', 14], ['Produkt', 's', 30], ['Velikost', 's', 9], ['Ks', 'n', 6],
            ['Cena za ks', 'n', 10], ['Celkem', 'n', 10], ['Převzetí', 's', 18], ['Jméno', 's', 22], ['E-mail', 's', 26], ['Telefon', 's', 15],
            ['Vysílání', 's', 24], ['Poznámka', 's', 30]], 'rows' => $rows, 'sum' => ['Ks', 'Celkem']],
    ]);
}

$title = 'Export do Excelu';
$section = 'export';
$presets = [
    'Tento měsíc'    => [date('Y-m-01'), date('Y-m-d')],
    'Minulý měsíc'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
    'Letos'          => [date('Y-01-01'), date('Y-m-d')],
    'Loni'           => [(date('Y') - 1) . '-01-01', (date('Y') - 1) . '-12-31'],
];
$rangeFields = function (string $id) use ($from, $to, $presets) {
    $out = '<div class="form-row"><div class="field"><label for="' . $id . '-f">Od</label><input id="' . $id . '-f" type="date" name="from" value="' . e($from) . '" required></div>'
        . '<div class="field"><label for="' . $id . '-t">Do</label><input id="' . $id . '-t" type="date" name="to" value="' . e($to) . '" required></div></div><p class="presets">';
    foreach ($presets as $label => [$f, $t]) {
        $out .= '<button type="button" class="chip-btn" data-range="' . e($f) . '|' . e($t) . '">' . e($label) . '</button>';
    }
    return $out . '</p>';
};
require APP_DIR . '/views/admin-header.php';
?>
<p class="muted">Soubory .xlsx se otevřou v Excelu, Google Tabulkách i LibreOffice. Částky jsou čísla (dají se sčítat a filtrovat), první řádek je ukotvený a s filtrem, pod daty je řádek se součty.</p>
<div class="export-grid">
  <form class="panel panel-pad" method="get">
    <input type="hidden" name="type" value="invoices">
    <h2 class="panel-h">Faktury</h2>
    <p class="small muted">Pro účetní: datum vystavení a zdanitelného plnění, VS, odběratel, IČO/DIČ, základ, DPH a celkem.</p>
    <?= $rangeFields('inv') ?>
    <div class="field">
      <label for="inv-s">Stav</label>
      <select id="inv-s" name="status"><option value="">Všechny kromě stornovaných</option><?php foreach (invoice_statuses() as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('download') ?> Stáhnout faktury</button>
  </form>

  <form class="panel panel-pad" method="get">
    <input type="hidden" name="type" value="orders">
    <h2 class="panel-h">Objednávky</h2>
    <p class="small muted">Dva listy: objednávky (zákazník, doprava, platba, částky, číslo faktury) a jednotlivé položky.</p>
    <?= $rangeFields('ord') ?>
    <div class="field">
      <label for="ord-s">Stav</label>
      <select id="ord-s" name="status"><option value="">Všechny</option><?php foreach (order_statuses() as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('download') ?> Stáhnout objednávky</button>
  </form>

  <form class="panel panel-pad" method="get">
    <input type="hidden" name="type" value="products">
    <h2 class="panel-h">Produkty a sklad</h2>
    <p class="small muted">Aktuální ceny, velikosti, počet kusů skladem a hodnota skladu v prodejních cenách – vhodné i pro inventuru.</p>
    <label class="check-inline"><input type="checkbox" name="all" value="1"> Včetně skrytých produktů</label>
    <button class="btn btn-primary" type="submit"><?= icon('download') ?> Stáhnout produkty</button>
  </form>

  <form class="panel panel-pad" method="get">
    <input type="hidden" name="type" value="preorders">
    <h2 class="panel-h">Předobjednávky</h2>
    <p class="small muted">Předobjednávky novinek z živého vysílání: produkt, velikost, kusy, zákazník a převzetí.</p>
    <?= $rangeFields('pre') ?>
    <button class="btn btn-primary" type="submit"><?= icon('download') ?> Stáhnout předobjednávky</button>
  </form>

  <form class="panel panel-pad" method="get">
    <input type="hidden" name="type" value="reservations">
    <h2 class="panel-h">Rezervace</h2>
    <p class="small muted">Rezervace do prodejny podle data vytvoření.</p>
    <?= $rangeFields('res') ?>
    <button class="btn btn-primary" type="submit"><?= icon('download') ?> Stáhnout rezervace</button>
  </form>
</div>
<?php require APP_DIR . '/views/admin-footer.php'; ?>
