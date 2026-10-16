<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

ny_ensure_content_tables();
$pdo = ny_db();
$admin = ny_current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'approve' || $action === 'reject') {
            $topupId = (int)($_POST['topup_id'] ?? 0);
            $note    = trim((string)($_POST['admin_note'] ?? ''));
            ny_credit_topup_decide($topupId, (int)$admin['id'], $action === 'approve', $note);
            ny_flash_set('ok', $action === 'approve' ? 'Dobití bylo schváleno a připsáno na účet.' : 'Žádost byla zamítnuta.');
        } elseif ($action === 'adjust') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $amount = (int)($_POST['amount'] ?? 0);
            $sign   = (string)($_POST['sign'] ?? '+');
            $note   = trim((string)($_POST['note'] ?? ''));
            if ($userId <= 0) throw new RuntimeException('Vyberte uživatele.');
            $delta = $sign === '-' ? -$amount : $amount;
            ny_credit_adjust($userId, $delta, (int)$admin['id'], $note);
            ny_flash_set('ok', 'Úprava kreditu byla uložena.');
        }
    } catch (Throwable $e) {
        ny_flash_set('err', $e->getMessage());
    }
    ny_redirect('credits.php');
}

$export = (string)($_GET['export'] ?? '');
$exportType = (string)($_GET['t'] ?? 'xls');
if ($exportType !== 'csv') $exportType = 'xls';

if ($export === 'topups' || $export === 'ledger' || $export === 'balances') {
    $kindLabel = [
        'topup'       => 'Dobití',
        'reservation' => 'Rezervace',
        'refund'      => 'Vrácení',
        'adjustment'  => 'Ruční úprava',
    ];
    $statusLabel = [
        'pending'  => 'Čeká',
        'approved' => 'Schváleno',
        'rejected' => 'Zamítnuto',
    ];

    if ($export === 'topups') {
        $sheet   = 'Zadosti o dobiti';
        $base    = 'kredity-zadosti-' . date('Y-m-d');
        $headers = ['ID', 'Uživatel', 'E-mail', 'Částka (Kč)', 'Stav', 'Žádost', 'Vyřízeno', 'Poznámka uživatele', 'Poznámka administrátora'];
        $stmt = $pdo->query(
            "SELECT t.id, u.display_name, u.email, t.amount_kc, t.status,
                    t.requested_at, t.decided_at, t.note, t.admin_note
               FROM ny_credit_topups t
               JOIN ny_users u ON u.id = t.user_id
              ORDER BY t.requested_at DESC"
        );
        $rowFor = static function (array $r) use ($statusLabel): array {
            $rq = $r['requested_at'] ? (new DateTimeImmutable((string)$r['requested_at']))->format('Y-m-d H:i') : '';
            $dc = $r['decided_at']   ? (new DateTimeImmutable((string)$r['decided_at']))->format('Y-m-d H:i')   : '';
            return [
                (int)$r['id'],
                (string)$r['display_name'],
                (string)$r['email'],
                (int)$r['amount_kc'],
                $statusLabel[$r['status']] ?? (string)$r['status'],
                $rq,
                $dc,
                (string)$r['note'],
                (string)$r['admin_note'],
            ];
        };
    } elseif ($export === 'ledger') {
        $sheet   = 'Historie pohybu';
        $base    = 'kredity-historie-' . date('Y-m-d');
        $headers = ['ID', 'Datum', 'Uživatel', 'E-mail', 'Typ', 'Změna (Kč)', 'Zůstatek po (Kč)', 'Reference', 'Poznámka'];
        $stmt = $pdo->query(
            "SELECT l.id, l.created_at, u.display_name, u.email,
                    l.kind, l.delta_kc, l.balance_kc, l.ref_type, l.ref_id, l.note
               FROM ny_credit_ledger l
               JOIN ny_users u ON u.id = l.user_id
              ORDER BY l.created_at DESC, l.id DESC"
        );
        $rowFor = static function (array $r) use ($kindLabel): array {
            $dt  = $r['created_at'] ? (new DateTimeImmutable((string)$r['created_at']))->format('Y-m-d H:i') : '';
            $ref = $r['ref_type'] !== '' ? ($r['ref_type'] . ($r['ref_id'] ? '#' . (int)$r['ref_id'] : '')) : '';
            return [
                (int)$r['id'],
                $dt,
                (string)$r['display_name'],
                (string)$r['email'],
                $kindLabel[$r['kind']] ?? (string)$r['kind'],
                (int)$r['delta_kc'],
                (int)$r['balance_kc'],
                $ref,
                (string)$r['note'],
            ];
        };
    } else {
        $sheet   = 'Stav kreditu';
        $base    = 'kredity-stav-' . date('Y-m-d');
        $headers = ['ID uživatele', 'Uživatel', 'E-mail', 'Zůstatek (Kč)'];
        $stmt = $pdo->query(
            "SELECT id, display_name, email, credit_balance_kc
               FROM ny_users
              WHERE (is_guest = 0 OR is_guest IS NULL)
              ORDER BY credit_balance_kc DESC, display_name"
        );
        $rowFor = static function (array $r): array {
            return [
                (int)$r['id'],
                (string)$r['display_name'],
                (string)$r['email'],
                (int)$r['credit_balance_kc'],
            ];
        };
    }

    while (ob_get_level() > 0) ob_end_clean();

    if ($exportType === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $base . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        // Neutralise spreadsheet formulas in user-supplied text (names, notes)
        // so a value like "=HYPERLINK(...)" isn't executed when opened in Excel.
        $csvSafe = static fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
        while ($r = $stmt->fetch()) fputcsv($out, array_map($csvSafe, $rowFor($r)), ';');
        fclose($out);
        exit;
    }

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . '.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $xmlCell = static function ($v): string {
        if (is_int($v) || (is_string($v) && $v !== '' && ctype_digit($v))) {
            return '<Cell><Data ss:Type="Number">' . htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
        }
        return '<Cell><Data ss:Type="String">' . htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
    };

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
       . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    echo '<Styles>'
       . '<Style ss:ID="hdr"><Font ss:Bold="1"/><Interior ss:Color="#ECDCCB" ss:Pattern="Solid"/></Style>'
       . '</Styles>' . "\n";
    echo '<Worksheet ss:Name="' . htmlspecialchars($sheet, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"><Table>' . "\n";
    echo '<Row>';
    foreach ($headers as $h) {
        echo '<Cell ss:StyleID="hdr"><Data ss:Type="String">' . htmlspecialchars($h, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</Data></Cell>';
    }
    echo "</Row>\n";
    while ($r = $stmt->fetch()) {
        echo '<Row>';
        foreach ($rowFor($r) as $cell) echo $xmlCell($cell);
        echo "</Row>\n";
    }
    echo '</Table></Worksheet></Workbook>' . "\n";
    exit;
}

$pending = ny_credit_topups_pending();
$recent  = ny_credit_topups_recent(30);
$ledger  = $pdo->query(
    "SELECT l.created_at, l.kind, l.delta_kc, l.balance_kc, l.ref_type, l.ref_id, l.note,
            u.display_name, u.email
       FROM ny_credit_ledger l
       JOIN ny_users u ON u.id = l.user_id
      ORDER BY l.created_at DESC, l.id DESC
      LIMIT 30"
)->fetchAll();
$users   = $pdo->query(
    'SELECT id, display_name, email, credit_balance_kc
       FROM ny_users
      WHERE (is_guest = 0 OR is_guest IS NULL)
      ORDER BY display_name'
)->fetchAll();

$totals = $pdo->query(
    "SELECT
        COALESCE(SUM(credit_balance_kc), 0) AS balance_sum,
        COUNT(CASE WHEN credit_balance_kc > 0 THEN 1 END) AS wallets
       FROM ny_users"
)->fetch() ?: ['balance_sum' => 0, 'wallets' => 0];

$topupStats = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount_kc END), 0) AS pending_sum,
        COALESCE(SUM(CASE WHEN status = 'approved' AND decided_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount_kc END), 0) AS approved_30d
       FROM ny_credit_topups"
)->fetch() ?: ['pending_sum' => 0, 'approved_30d' => 0];

$kc = static fn(int $v): string => number_format($v, 0, ',', ' ') . "\u{00A0}Kč";

// Allow deep-linking a user into the adjust form (credits.php?user=123#adjust).
$preselectUser = (int)($_GET['user'] ?? 0);

$kindLabels = [
    'topup'       => 'Dobití',
    'reservation' => 'Rezervace',
    'refund'      => 'Vrácení',
    'adjustment'  => 'Ruční úprava',
];

ny_admin_render_header('Kredity', 'credits');
?>
<div class="admin-stats credit-stats">
    <a class="admin-stat<?= $pending ? ' admin-stat--alert' : '' ?>" href="#pending">
        <div class="num"><?= count($pending) ?></div>
        <div class="lbl">Čeká na schválení</div>
    </a>
    <div class="admin-stat"><div class="num"><?= $kc((int)$topupStats['pending_sum']) ?></div><div class="lbl">Částka ke schválení</div></div>
    <div class="admin-stat"><div class="num"><?= $kc((int)$totals['balance_sum']) ?></div><div class="lbl">Kredity v oběhu</div></div>
    <div class="admin-stat"><div class="num"><?= (int)$totals['wallets'] ?></div><div class="lbl">Aktivní peněženky</div></div>
    <div class="admin-stat"><div class="num"><?= $kc((int)$topupStats['approved_30d']) ?></div><div class="lbl">Dobito za 30 dní</div></div>
</div>

<div class="admin-card" id="pending">
    <div class="admin-header admin-card-head">
        <h2>Žádosti o dobití <span class="hint count-tag">(<?= count($pending) ?>)</span></h2>
    </div>
    <?php if (!$pending): ?>
        <p class="hint">Žádné nevyřízené žádosti.</p>
    <?php else: ?>
        <div class="credit-requests">
        <?php foreach ($pending as $r):
            $dt = new DateTimeImmutable((string)$r['requested_at']);
        ?>
            <div class="credit-request">
                <div class="credit-request-info">
                    <strong class="credit-request-name"><?= e((string)$r['display_name']) ?></strong>
                    <a class="credit-request-email" href="mailto:<?= e((string)$r['email']) ?>"><?= e((string)$r['email']) ?></a>
                    <div class="credit-request-meta"><?= ny_icon('clock', 13) ?> <?= e($dt->format('j. n. Y H:i')) ?></div>
                    <?php if ((string)$r['note'] !== ''): ?>
                        <div class="credit-request-note">„<?= e((string)$r['note']) ?>“</div>
                    <?php endif; ?>
                </div>
                <div class="credit-request-amount"><?= $kc((int)$r['amount_kc']) ?></div>
                <form method="post" class="credit-decide-form">
                    <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                    <input type="hidden" name="topup_id" value="<?= (int)$r['id'] ?>">
                    <input type="text" name="admin_note" placeholder="Poznámka (volitelné)" maxlength="200" aria-label="Poznámka administrátora">
                    <div class="credit-decide-buttons">
                        <button class="btn btn-primary btn-sm" type="submit" name="action" value="approve">Schválit</button>
                        <button class="btn btn-danger btn-sm" type="submit" name="action" value="reject"
                                onclick="return confirm('Zamítnout tuto žádost?');">Zamítnout</button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card" id="adjust">
    <h2>Ruční úprava kreditu</h2>
    <p class="hint">Použijte pro refundace mimo rezervační systém nebo pro opravu stavu účtu. Úprava se zapíše do historie.</p>
    <form method="post" class="admin-form credit-adjust" id="credit-adjust-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="adjust">

        <div class="credit-adjust-grid">
            <fieldset class="credit-picker">
                <legend>Uživatel</legend>
                <input type="search" id="credit-user-search" class="credit-picker-search"
                       placeholder="Hledat jméno nebo e-mail…"
                       autocomplete="off" spellcheck="false" aria-label="Hledat uživatele">
                <div class="credit-picker-list" id="credit-user-list" role="radiogroup" aria-label="Uživatel">
                    <?php foreach ($users as $i => $u):
                        $haystack = mb_strtolower(($u['display_name'] ?? '') . ' ' . ($u['email'] ?? ''));
                        $bal = (int)$u['credit_balance_kc'];
                    ?>
                        <label class="credit-picker-opt" data-search="<?= e($haystack) ?>">
                            <input type="radio" name="user_id" value="<?= (int)$u['id'] ?>"
                                   data-balance="<?= $bal ?>" data-name="<?= e((string)$u['display_name']) ?>"
                                   <?= $i === 0 ? 'required' : '' ?>
                                   <?= $preselectUser === (int)$u['id'] ? 'checked' : '' ?>>
                            <span class="credit-picker-who">
                                <strong><?= e((string)$u['display_name']) ?></strong>
                                <small><?= e((string)$u['email']) ?></small>
                            </span>
                            <span class="credit-picker-bal<?= $bal > 0 ? ' has-credit' : '' ?>"><?= $kc($bal) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <p class="hint credit-picker-empty" id="credit-user-empty" hidden>Nikdo neodpovídá hledání.</p>
                </div>
                <small class="hint hint-inline" id="credit-user-count"><?= count($users) ?> uživatelů</small>
            </fieldset>

            <div class="credit-adjust-side">
                <div class="credit-field">
                    <span class="credit-field-lbl">Operace</span>
                    <div class="credit-seg" role="radiogroup" aria-label="Operace">
                        <label class="credit-seg-opt credit-seg-opt--plus">
                            <input type="radio" name="sign" value="+" checked>
                            <span>+ připsat</span>
                        </label>
                        <label class="credit-seg-opt credit-seg-opt--minus">
                            <input type="radio" name="sign" value="-">
                            <span>− odepsat</span>
                        </label>
                    </div>
                </div>

                <label class="credit-field">Částka (Kč)
                    <input type="number" name="amount" id="credit-amount" min="1" step="1" inputmode="numeric" required>
                </label>
                <div class="credit-chips" aria-label="Rychlá volba částky">
                    <?php foreach ([100, 200, 300, 500, 1000] as $amt): ?>
                        <button type="button" class="credit-chip" data-amount="<?= $amt ?>"><?= $amt ?></button>
                    <?php endforeach; ?>
                </div>

                <label class="credit-field">Důvod úpravy
                    <input type="text" name="note" maxlength="200" required placeholder="např. refundace hotovostní platby">
                </label>

                <div class="credit-preview" id="credit-preview" hidden>
                    <span id="credit-preview-name"></span>
                    <span class="credit-preview-calc">
                        <span id="credit-preview-from"></span> → <strong id="credit-preview-to"></strong>
                    </span>
                </div>

                <div class="row form-actions">
                    <button class="btn btn-primary" type="submit">Uložit úpravu</button>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
(function () {
    var form    = document.getElementById('credit-adjust-form');
    var search  = document.getElementById('credit-user-search');
    var list    = document.getElementById('credit-user-list');
    var countEl = document.getElementById('credit-user-count');
    var emptyEl = document.getElementById('credit-user-empty');
    var amount  = document.getElementById('credit-amount');
    if (!form || !search || !list) return;

    var opts       = Array.prototype.slice.call(list.querySelectorAll('.credit-picker-opt'));
    var totalLabel = countEl ? countEl.textContent : '';
    var fmt = function (n) {
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč';
    };

    function normalize(s) {
        return (s || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function filter() {
        var q = normalize(search.value.trim());
        var shown = 0;
        opts.forEach(function (opt) {
            var radio = opt.querySelector('input');
            var match = q === '' || normalize(opt.getAttribute('data-search')).indexOf(q) !== -1;
            // Keep the selected user visible so the choice never silently disappears.
            opt.hidden = !match && !radio.checked;
            if (!opt.hidden) shown++;
        });
        if (emptyEl) emptyEl.hidden = shown > 0;
        if (countEl) {
            countEl.textContent = q === '' ? totalLabel : shown + ' z ' + opts.length + ' uživatelů';
        }
    }

    function preview() {
        var user = form.querySelector('input[name="user_id"]:checked');
        var sign = form.querySelector('input[name="sign"]:checked');
        var amt  = parseInt(amount.value, 10);
        var box  = document.getElementById('credit-preview');
        opts.forEach(function (o) { o.classList.toggle('is-selected', o.querySelector('input').checked); });
        if (!user) { box.hidden = true; return; }
        var from = parseInt(user.getAttribute('data-balance'), 10) || 0;
        var to   = from + (isNaN(amt) ? 0 : (sign && sign.value === '-' ? -amt : amt));
        document.getElementById('credit-preview-name').textContent = user.getAttribute('data-name');
        document.getElementById('credit-preview-from').textContent = fmt(from);
        var toEl = document.getElementById('credit-preview-to');
        toEl.textContent = (to < 0 ? '−' : '') + fmt(Math.abs(to));
        toEl.classList.toggle('delta-minus', to < 0);
        box.hidden = false;
    }

    search.addEventListener('input', filter);
    search.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        // Enter picks the only visible user.
        var visible = opts.filter(function (o) { return !o.hidden; });
        if (visible.length === 1) {
            visible[0].querySelector('input').checked = true;
            preview();
            amount.focus();
        }
    });
    form.addEventListener('change', preview);
    amount.addEventListener('input', preview);
    Array.prototype.forEach.call(form.querySelectorAll('.credit-chip'), function (chip) {
        chip.addEventListener('click', function () {
            amount.value = chip.getAttribute('data-amount');
            preview();
        });
    });

    var pre = form.querySelector('input[name="user_id"]:checked');
    if (pre) pre.closest('.credit-picker-opt').scrollIntoView({ block: 'nearest' });
    preview();
})();
</script>

<div class="admin-card">
    <h2>Export dat</h2>
    <div class="credit-export">
        <?php foreach ([
            'topups'   => 'Žádosti o dobití',
            'ledger'   => 'Historie pohybů',
            'balances' => 'Aktuální zůstatky',
        ] as $key => $label): ?>
            <div class="credit-export-item">
                <span><?= ny_icon('download', 15) ?> <?= e($label) ?></span>
                <span class="credit-export-btns">
                    <a class="btn btn-secondary btn-sm" href="credits.php?export=<?= $key ?>&amp;t=xls">XLS</a>
                    <a class="btn btn-secondary btn-sm" href="credits.php?export=<?= $key ?>&amp;t=csv">CSV</a>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="admin-card">
    <h2>Posledních 30 žádostí o dobití <span class="hint count-tag">(všechny stavy)</span></h2>
    <?php if (!$recent): ?>
        <p class="hint">Zatím žádné žádosti.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl admin-tbl--credits admin-tbl--topups">
            <thead><tr>
                <th>Žádost</th>
                <th>Uživatel</th>
                <th class="text-right">Částka</th>
                <th>Stav</th>
                <th>Vyřízeno</th>
                <th>Poznámky</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recent as $r):
                $rq = new DateTimeImmutable((string)$r['requested_at']);
                $dc = $r['decided_at'] ? new DateTimeImmutable((string)$r['decided_at']) : null;
                $badge = match ($r['status']) {
                    'approved' => 'badge badge-success',
                    'rejected' => 'badge badge-danger',
                    default    => 'badge badge-warn',
                };
                $label = match ($r['status']) {
                    'approved' => 'Schváleno',
                    'rejected' => 'Zamítnuto',
                    default    => 'Čeká',
                };
                $hasNotes = $r['note'] || $r['admin_note'];
            ?>
                <tr>
                    <td class="c-date" data-label="Žádost"><?= e($rq->format('j. n. Y H:i')) ?></td>
                    <td class="c-user" data-label="Uživatel"><strong><?= e((string)$r['display_name']) ?></strong><small class="hint"><?= e((string)$r['email']) ?></small></td>
                    <td class="c-amt text-right" data-label="Částka"><strong><?= $kc((int)$r['amount_kc']) ?></strong></td>
                    <td class="c-status" data-label="Stav"><span class="<?= e($badge) ?>"><?= e($label) ?></span></td>
                    <td class="c-decided<?= $dc ? '' : ' is-empty' ?>" data-label="Vyřízeno"><?= $dc ? e($dc->format('j. n. Y H:i')) : '—' ?></td>
                    <td class="c-note<?= $hasNotes ? '' : ' is-empty' ?>" data-label="Poznámky">
                        <?php if ($r['note']): ?><div><?= e((string)$r['note']) ?></div><?php endif; ?>
                        <?php if ($r['admin_note']): ?><small class="hint">Admin: <?= e((string)$r['admin_note']) ?></small><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card">
    <h2>Historie pohybů kreditu <span class="hint count-tag">(posledních 30)</span></h2>
    <p class="hint">Všechny změny zůstatků – dobití, platby rezervací, vrácení i ruční úpravy.</p>
    <?php if (!$ledger): ?>
        <p class="hint">Zatím žádné pohyby.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl admin-tbl--credits admin-tbl--ledger">
            <thead><tr>
                <th>Datum</th>
                <th>Uživatel</th>
                <th>Typ</th>
                <th class="text-right">Změna</th>
                <th class="text-right">Zůstatek po</th>
                <th>Poznámka</th>
            </tr></thead>
            <tbody>
            <?php foreach ($ledger as $l):
                $dt    = new DateTimeImmutable((string)$l['created_at']);
                $delta = (int)$l['delta_kc'];
                $kind  = (string)$l['kind'];
                $label = $kindLabels[$kind] ?? $kind;
                $ref   = $l['ref_type'] !== '' ? ($l['ref_type'] . ($l['ref_id'] ? ' #' . (int)$l['ref_id'] : '')) : '';
            ?>
                <tr>
                    <td class="c-date" data-label="Datum"><?= e($dt->format('j. n. Y H:i')) ?></td>
                    <td class="c-user" data-label="Uživatel"><strong><?= e((string)$l['display_name']) ?></strong><small class="hint"><?= e((string)$l['email']) ?></small></td>
                    <td class="c-status" data-label="Typ"><span class="badge"><?= e($label) ?></span><?php if ($ref !== ''): ?> <small class="hint mono"><?= e($ref) ?></small><?php endif; ?></td>
                    <td class="c-amt text-right" data-label="Změna"><strong class="<?= $delta >= 0 ? 'delta-plus' : 'delta-minus' ?>"><?= $delta > 0 ? '+' : ($delta < 0 ? '−' : '') ?><?= $kc(abs($delta)) ?></strong></td>
                    <td class="c-bal text-right" data-label="Zůstatek po"><?= $kc((int)$l['balance_kc']) ?></td>
                    <td class="c-note<?= (string)$l['note'] === '' ? ' is-empty' : '' ?>" data-label="Poznámka"><?= e((string)$l['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php ny_admin_render_footer();
