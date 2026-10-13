<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

ny_ensure_content_tables();

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
if ($code === '') {
    http_response_code(404);
    ny_render_header('Dárkový poukaz', '');
    echo '<div class="container page"><p>Chybí kód poukazu.</p></div>';
    ny_render_footer();
    return;
}

$pdo  = ny_db();
$stmt = $pdo->prepare('SELECT * FROM ny_vouchers WHERE code = ? LIMIT 1');
$stmt->execute([$code]);
$v = $stmt->fetch();

if (!$v) {
    http_response_code(404);
    ny_render_header('Dárkový poukaz', '');
    echo '<div class="container page"><p>Poukaz s tímto kódem nebyl nalezen.</p></div>';
    ny_render_footer();
    return;
}

$user    = ny_current_user();
$isAdmin = ny_is_admin($user);

$s        = ny_settings_all();
$siteName = $s['site_name'] ?: 'Studio Namasté';
$amount   = (int)$v['amount_czk'] > 0
    ? number_format((int)$v['amount_czk'], 0, ',', ' ') . ' Kč'
    : (string)$v['amount_raw'];
$valid    = $v['valid_until']
    ? (new DateTimeImmutable($v['valid_until']))->format('j. n. Y')
    : '';
$bgVer    = (string)(@filemtime(__DIR__ . '/assets/darkovy-poukaz.jpg') ?: time());
?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dárkový poukaz <?= e((string)$v['code']) ?> · <?= e($siteName) ?></title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Playfair+Display:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?= e((string)(@filemtime(__DIR__ . '/style.css') ?: time())) ?>">
</head>
<body class="voucher-page">
<div class="voucher-toolbar">
    <button type="button" class="btn btn-primary" onclick="window.print()">Vytisknout</button>
    <?php if ($isAdmin): ?>
        <a class="btn btn-ghost" href="admin/vouchers.php">Zpět do adminu</a>
    <?php else: ?>
        <a class="btn btn-ghost" href="index.php">Na úvod</a>
    <?php endif; ?>
</div>

<div class="voucher-sheet" style="--vo-bg: url('assets/darkovy-poukaz.jpg?v=<?= e($bgVer) ?>')" role="img" aria-label="Dárkový poukaz <?= e((string)$v['code']) ?>">
    <?php if (in_array((string)$v['status'], ['pending','redeemed','cancelled'], true)):
        $sLabel = [
            'pending'   => 'Nová objednávka',
            'redeemed'  => 'Uplatněno',
            'cancelled' => 'Zrušeno',
        ][(string)$v['status']];
    ?>
        <span class="vo-status <?= e((string)$v['status']) ?>"><?= e($sLabel) ?></span>
    <?php endif; ?>

    <?php if ($amount !== ''): ?>
        <div class="vo-price">
            <small>Hodnota</small>
            <strong><?= e($amount) ?></strong>
        </div>
    <?php endif; ?>

    <div class="vo-middle">
        <?php if (!empty($v['for_whom'])): ?>
            <div class="vo-for">pro <strong><?= e((string)$v['for_whom']) ?></strong></div>
        <?php endif; ?>
        <?php if (trim((string)($v['message'] ?? '')) !== ''): ?>
            <div class="vo-msg">„<?= nl2br(e((string)$v['message'])) ?>"</div>
        <?php endif; ?>
        <div class="vo-code">
            <small>Kód poukazu</small>
            <strong><?= e((string)$v['code']) ?></strong>
        </div>
        <?php if ($valid !== ''): ?>
            <div class="vo-valid">Platnost do <?= e($valid) ?></div>
        <?php endif; ?>
    </div>
</div>

<p class="voucher-caption">
    Poukaz uplatníte na recepci studia nebo při rezervaci lekce – stačí uvést kód poukazu uvedený výše.
</p>
</body>
</html>
