<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$pdo    = ny_db();
$filter = (string)($_GET['f'] ?? 'upcoming');
$search = trim((string)($_GET['q'] ?? ''));
$export = (string)($_GET['export'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("UPDATE ny_reservations SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        ny_flash_set('ok', 'Rezervace zrušena.');
    }
    ny_redirect('reservations.php?f=' . rawurlencode($filter) . ($search !== '' ? '&q=' . rawurlencode($search) : ''));
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$where  = [];
$params = [];

if ($filter === 'upcoming') {
    $where[]  = "r.class_date >= ?";
    $params[] = $today;
    $where[]  = "r.status = 'booked'";
} elseif ($filter === 'past') {
    $where[]  = "r.class_date < ?";
    $params[] = $today;
} elseif ($filter === 'cancelled') {
    $where[] = "r.status = 'cancelled'";
} // "all" – no filter

if ($search !== '') {
    $where[]  = "(u.display_name LIKE ? OR u.email LIKE ? OR c.name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sqlBase = 'SELECT r.*, c.name AS class_name, c.teacher, c.start_time, c.end_time,
               u.display_name, u.email, u.phone
          FROM ny_reservations r
          JOIN ny_classes c ON c.id = r.class_id
          JOIN ny_users   u ON u.id = r.user_id';
if ($where) $sqlBase .= ' WHERE ' . implode(' AND ', $where);
$sqlBase .= ' ORDER BY r.class_date DESC, c.start_time DESC';

if ($export === 'csv' || $export === 'xls') {
    $stmt = $pdo->prepare($sqlBase);
    $stmt->execute($params);

    $baseName = 'rezervace-' . $filter . ($search !== '' ? '-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $search) : '') . '-' . date('Y-m-d');
    $headers  = ['Datum', 'Čas od', 'Čas do', 'Lekce', 'Lektor', 'Klient', 'E-mail', 'Telefon', 'Stav', 'Vytvořeno'];

    $rowFor = function (array $r) {
        return [
            $r['class_date'] ? (new DateTimeImmutable((string)$r['class_date']))->format('Y-m-d') : '',
            substr((string)$r['start_time'], 0, 5),
            substr((string)$r['end_time'],   0, 5),
            (string)$r['class_name'],
            (string)$r['teacher'],
            (string)$r['display_name'],
            (string)$r['email'],
            (string)($r['phone'] ?? ''),
            $r['status'] === 'booked' ? 'rezervováno' : ($r['status'] === 'cancelled' ? 'zrušeno' : (string)$r['status']),
            !empty($r['created_at']) ? (new DateTimeImmutable((string)$r['created_at']))->format('Y-m-d H:i') : '',
        ];
    };

    while (ob_get_level() > 0) ob_end_clean();

    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        while ($r = $stmt->fetch()) fputcsv($out, $rowFor($r), ';');
        fclose($out);
        exit;
    }

    // Excel: SpreadsheetML 2003 XML – opens natively in Excel, no library needed.
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $baseName . '.xls"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $xmlCell = function ($v): string {
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
    echo '<Worksheet ss:Name="Rezervace"><Table>' . "\n";
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

$stmt = $pdo->prepare($sqlBase . ' LIMIT 200');
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Grouped roster: per class occurrence, list attendee full names. Uses the
// same filter/search as the flat list so admin sees a consistent view.
$rosterGroups = [];
foreach ($rows as $r) {
    if ($r['status'] !== 'booked') continue;
    $key = $r['class_date'] . '|' . $r['class_id'];
    if (!isset($rosterGroups[$key])) {
        $rosterGroups[$key] = [
            'class_date' => $r['class_date'],
            'start_time' => $r['start_time'],
            'end_time'   => $r['end_time'],
            'class_name' => $r['class_name'],
            'teacher'    => $r['teacher'],
            'attendees'  => [],
        ];
    }
    $rosterGroups[$key]['attendees'][] = [
        'name'  => (string)$r['display_name'],
        'email' => (string)$r['email'],
        'phone' => (string)$r['phone'],
    ];
}
// Sort by date + start_time ASC so the next lekce is on top.
uasort($rosterGroups, function ($a, $b) {
    return strcmp($a['class_date'] . $a['start_time'], $b['class_date'] . $b['start_time']);
});

ny_admin_render_header('Rezervace', 'reservations');
?>
<div class="admin-card">
    <form method="get" class="row row-wrap">
        <div class="week-nav week-nav--tight">
            <a href="?f=upcoming"  class="<?= $filter === 'upcoming'  ? 'is-active' : '' ?>">Nadcházející</a>
            <a href="?f=past"      class="<?= $filter === 'past'      ? 'is-active' : '' ?>">Minulé</a>
            <a href="?f=cancelled" class="<?= $filter === 'cancelled' ? 'is-active' : '' ?>">Zrušené</a>
            <a href="?f=all"       class="<?= $filter === 'all'       ? 'is-active' : '' ?>">Vše</a>
        </div>
        <input type="hidden" name="f" value="<?= e($filter) ?>">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Hledat jméno, e-mail, lekci…" class="filter-input">
        <button class="btn btn-secondary" type="submit">Hledat</button>
        <?php
            $exportQs = ['f' => $filter];
            if ($search !== '') $exportQs['q'] = $search;
            $csvUrl = 'reservations.php?' . http_build_query($exportQs + ['export' => 'csv']);
            $xlsUrl = 'reservations.php?' . http_build_query($exportQs + ['export' => 'xls']);
        ?>
        <a class="btn btn-ghost" href="<?= e($csvUrl) ?>" title="Stáhnout jako CSV (respektuje filtr a hledání)"><?= ny_icon('download', 14) ?> CSV</a>
        <a class="btn btn-ghost" href="<?= e($xlsUrl) ?>" title="Stáhnout jako Excel (.xls)"><?= ny_icon('download', 14) ?> Excel</a>
    </form>
</div>

<?php if ($rosterGroups): ?>
<div class="admin-card">
    <div class="admin-header admin-card-head">
        <h2>Docházka na lekce <span class="hint count-tag">(<?= count($rosterGroups) ?> lekcí)</span></h2>
        <span class="hint">Kompletní jména účastníků podle lekce a data.</span>
    </div>
    <div class="roster-groups">
    <?php foreach ($rosterGroups as $g):
        $d = new DateTimeImmutable($g['class_date']);
        $count = count($g['attendees']);
    ?>
        <details class="roster-group" open>
            <summary>
                <span class="roster-group-when">
                    <strong><?= e($d->format('j. n. Y')) ?></strong>
                    · <?= e(substr((string)$g['start_time'], 0, 5)) ?>–<?= e(substr((string)$g['end_time'], 0, 5)) ?>
                </span>
                <span class="roster-group-title"><?= e((string)$g['class_name']) ?></span>
                <span class="roster-group-meta"><?= e((string)$g['teacher']) ?></span>
                <span class="badge badge-success"><?= (int)$count ?> účastník<?= $count === 1 ? '' : ($count >= 5 ? 'ů' : 'ci') ?></span>
            </summary>
            <ol class="roster-attendees">
                <?php foreach ($g['attendees'] as $a): ?>
                    <li>
                        <span class="roster-attendee-name"><?= e($a['name']) ?></span>
                        <?php if ($a['email'] !== '' || $a['phone'] !== ''): ?>
                            <span class="roster-attendee-contact">
                                <?php if ($a['email'] !== ''): ?><?= e($a['email']) ?><?php endif; ?>
                                <?php if ($a['email'] !== '' && $a['phone'] !== ''): ?> · <?php endif; ?>
                                <?php if ($a['phone'] !== ''): ?><?= e($a['phone']) ?><?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </details>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="admin-card">
    <h2>Rezervace <span class="hint count-tag">(<?= count($rows) ?> položek)</span></h2>
    <?php if (!$rows): ?>
        <p class="hint">Žádné rezervace pro zvolený filtr.</p>
    <?php else: ?>
        <div class="tbl-wrap">
        <table class="admin-tbl">
            <thead><tr><th>Datum</th><th>Čas</th><th>Lekce</th><th>Lektor</th><th>Klient</th><th>Stav</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $d = new DateTimeImmutable($r['class_date']); ?>
                <tr>
                    <td><?= e($d->format('j. n. Y')) ?></td>
                    <td><?= e(substr((string)$r['start_time'], 0, 5)) ?></td>
                    <td><?= e($r['class_name']) ?></td>
                    <td><?= e($r['teacher']) ?></td>
                    <td>
                        <?= e($r['display_name']) ?><br>
                        <small class="hint"><?= e($r['email']) ?><?php if ($r['phone']): ?> · <?= e($r['phone']) ?><?php endif; ?></small>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'booked'): ?>
                            <span class="badge badge-success">rezervováno</span>
                        <?php else: ?>
                            <span class="badge badge-danger">zrušeno</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <?php if ($r['status'] === 'booked'): ?>
                            <form method="post" class="inline" onsubmit="return confirm('Zrušit rezervaci?');">
                                <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-danger" type="submit">Zrušit</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
<?php ny_admin_render_footer();
