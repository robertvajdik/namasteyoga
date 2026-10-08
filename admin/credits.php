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

ny_admin_render_header('Kredity', 'credits');
?>
<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Žádosti o dobití <span class="hint count-tag">(<?= count($pending) ?>)</span></h2>
        <div class="hint">Celkem kreditů v oběhu: <strong><?= number_format((int)$totals['balance_sum'], 0, ',', ' ') ?> Kč</strong> · aktivních peněženek: <?= (int)$totals['wallets'] ?></div>
    </div>
    <?php if (!$pending): ?>
        <p class="hint">Žádné nevyřízené žádosti.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr>
                <th>Uživatel</th>
                <th>E-mail</th>
                <th>Částka</th>
                <th>Poznámka uživatele</th>
                <th>Žádost</th>
                <th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($pending as $r):
                $dt = new DateTimeImmutable((string)$r['requested_at']);
            ?>
                <tr>
                    <td data-label="Uživatel"><?= e((string)$r['display_name']) ?></td>
                    <td data-label="E-mail"><a href="mailto:<?= e((string)$r['email']) ?>"><?= e((string)$r['email']) ?></a></td>
                    <td data-label="Částka"><strong><?= number_format((int)$r['amount_kc'], 0, ',', ' ') ?> Kč</strong></td>
                    <td data-label="Poznámka"><?= e((string)$r['note']) ?></td>
                    <td data-label="Žádost"><?= e($dt->format('j. n. Y H:i')) ?></td>
                    <td data-label="Akce" class="text-right">
                        <form method="post" class="inline credit-decide-form">
                            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                            <input type="hidden" name="topup_id" value="<?= (int)$r['id'] ?>">
                            <input type="text" name="admin_note" placeholder="Poznámka (volitelné)" maxlength="200">
                            <button class="btn btn-primary btn-sm" type="submit" name="action" value="approve">Schválit</button>
                            <button class="btn btn-ghost btn-sm" type="submit" name="action" value="reject"
                                    onclick="return confirm('Zamítnout tuto žádost?');">Zamítnout</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card">
    <div class="row row-wrap">
        <strong>Export dat</strong>
        <span class="hint">Žádosti o dobití</span>
        <a class="btn btn-ghost btn-sm" href="credits.php?export=topups&amp;t=xls">XLS</a>
        <a class="btn btn-ghost btn-sm" href="credits.php?export=topups&amp;t=csv">CSV</a>
        <span class="spacer"></span>
        <span class="hint">Historie pohybů</span>
        <a class="btn btn-ghost btn-sm" href="credits.php?export=ledger&amp;t=xls">XLS</a>
        <a class="btn btn-ghost btn-sm" href="credits.php?export=ledger&amp;t=csv">CSV</a>
        <span class="spacer"></span>
        <span class="hint">Aktuální zůstatky</span>
        <a class="btn btn-ghost btn-sm" href="credits.php?export=balances&amp;t=xls">XLS</a>
        <a class="btn btn-ghost btn-sm" href="credits.php?export=balances&amp;t=csv">CSV</a>
    </div>
</div>

<div class="admin-card">
    <h2>Ruční úprava kreditu</h2>
    <p class="hint">Použijte pro refundace mimo rezervační systém nebo pro opravu stavu účtu. Úprava se zapíše do historie.</p>
    <form method="post" class="admin-form" id="credit-adjust-form">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="action" value="adjust">
        <div class="admin-form-row full">
            <label>Hledat uživatele
                <input type="search" id="credit-user-search" class="filter-input"
                       placeholder="Zadejte jméno nebo e-mail…"
                       autocomplete="off" spellcheck="false">
            </label>
        </div>
        <div class="admin-form-row">
            <label>Uživatel
                <select name="user_id" id="credit-user-select" required size="8">
                    <option value="">— vyberte —</option>
                    <?php foreach ($users as $u):
                        $haystack = mb_strtolower(($u['display_name'] ?? '') . ' ' . ($u['email'] ?? ''));
                    ?>
                        <option value="<?= (int)$u['id'] ?>" data-search="<?= e($haystack) ?>">
                            <?= e((string)$u['display_name']) ?> · <?= e((string)$u['email']) ?> ·
                            <?= number_format((int)$u['credit_balance_kc'], 0, ',', ' ') ?> Kč
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="hint hint-inline" id="credit-user-count"><?= count($users) ?> uživatelů</small>
            </label>
            <label>Operace
                <select name="sign">
                    <option value="+">+ připsat</option>
                    <option value="-">− odepsat</option>
                </select>
            </label>
            <label>Částka (Kč)
                <input type="number" name="amount" min="1" step="1" required>
            </label>
        </div>
        <div class="admin-form-row full">
            <label>Důvod úpravy
                <input type="text" name="note" maxlength="200" required placeholder="např. refundace hotovostní platby">
            </label>
        </div>
        <div class="row form-actions">
            <button class="btn btn-primary" type="submit">Uložit úpravu</button>
        </div>
    </form>
</div>
<script>
(function () {
    var search  = document.getElementById('credit-user-search');
    var select  = document.getElementById('credit-user-select');
    var countEl = document.getElementById('credit-user-count');
    if (!search || !select) return;

    var options = Array.prototype.slice.call(select.options).filter(function (o) {
        return o.value !== '';
    });
    var placeholder = select.options[0];
    var totalLabel  = countEl ? countEl.textContent : '';

    function normalize(s) {
        return (s || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function apply() {
        var q = normalize(search.value.trim());
        var shown = 0;
        options.forEach(function (opt) {
            var hay = normalize(opt.getAttribute('data-search') || opt.textContent);
            var match = q === '' || hay.indexOf(q) !== -1;
            opt.hidden = !match;
            opt.disabled = !match;
            if (match) shown++;
        });
        if (countEl) {
            countEl.textContent = q === ''
                ? totalLabel
                : shown + ' z ' + options.length + ' uživatelů';
        }
        // If the currently selected option got hidden, drop the selection.
        if (select.selectedIndex > 0 && select.options[select.selectedIndex].hidden) {
            placeholder.selected = true;
        }
    }

    search.addEventListener('input', apply);
    search.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        // Auto-select the only visible option when the user hits Enter.
        var visible = options.filter(function (o) { return !o.hidden; });
        if (visible.length === 1) {
            visible[0].selected = true;
            select.focus();
        }
    });
})();
</script>

<div class="admin-card">
    <h2>Posledních 30 žádostí o dobití <span class="hint count-tag">(všechny stavy)</span></h2>
    <?php if (!$recent): ?>
        <p class="hint">Zatím žádné žádosti.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr>
                <th>Žádost</th>
                <th>Uživatel</th>
                <th>Částka</th>
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
                    default    => 'badge',
                };
                $label = match ($r['status']) {
                    'approved' => 'Schváleno',
                    'rejected' => 'Zamítnuto',
                    default    => 'Čeká',
                };
            ?>
                <tr>
                    <td data-label="Žádost"><?= e($rq->format('j. n. Y H:i')) ?></td>
                    <td data-label="Uživatel"><?= e((string)$r['display_name']) ?><br><small class="hint"><?= e((string)$r['email']) ?></small></td>
                    <td data-label="Částka"><?= number_format((int)$r['amount_kc'], 0, ',', ' ') ?> Kč</td>
                    <td data-label="Stav"><span class="<?= e($badge) ?>"><?= e($label) ?></span></td>
                    <td data-label="Vyřízeno"><?= $dc ? e($dc->format('j. n. Y H:i')) : '—' ?></td>
                    <td data-label="Poznámky">
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
        <?php
        $kindLabels = [
            'topup'       => 'Dobití',
            'reservation' => 'Rezervace',
            'refund'      => 'Vrácení',
            'adjustment'  => 'Ruční úprava',
        ];
        ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
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
                    <td data-label="Datum"><?= e($dt->format('j. n. Y H:i')) ?></td>
                    <td data-label="Uživatel"><?= e((string)$l['display_name']) ?><br><small class="hint"><?= e((string)$l['email']) ?></small></td>
                    <td data-label="Typ"><span class="badge"><?= e($label) ?></span><?php if ($ref !== ''): ?><br><small class="hint mono"><?= e($ref) ?></small><?php endif; ?></td>
                    <td data-label="Změna" class="text-right"><strong style="color: <?= $delta >= 0 ? 'var(--success, #2a7a2a)' : 'var(--danger, #a33)' ?>"><?= $delta > 0 ? '+' : '' ?><?= number_format($delta, 0, ',', ' ') ?> Kč</strong></td>
                    <td data-label="Zůstatek po" class="text-right"><?= number_format((int)$l['balance_kc'], 0, ',', ' ') ?> Kč</td>
                    <td data-label="Poznámka"><?= e((string)$l['note']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php ny_admin_render_footer();
