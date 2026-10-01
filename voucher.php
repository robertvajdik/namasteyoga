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
<style>
    :root {
        --tc-terra: #c67a5c;
        --tc-cream: #f3e3cc;
        --tc-cream-soft: rgba(243, 227, 204, .78);
    }
    body.voucher-page {
        background: #efe6d4;
        margin: 0;
        padding: 24px 16px;
        font-family: 'Open Sans', sans-serif;
    }
    .voucher-toolbar {
        max-width: 820px;
        margin: 0 auto 16px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    .voucher-sheet {
        position: relative;
        max-width: 820px;
        aspect-ratio: 1 / 1;
        margin: 0 auto;
        border-radius: 14px;
        overflow: hidden;
        background: var(--tc-terra) url('assets/darkovy-poukaz.jpg?v=<?= e($bgVer) ?>') center/cover no-repeat;
        box-shadow: 0 10px 36px rgba(99, 54, 36, .22);
        color: var(--tc-cream);
    }
    .vo-status {
        position: absolute;
        top: 18px;
        right: 20px;
        padding: 5px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .18);
        color: var(--tc-cream);
        font-size: 11px;
        letter-spacing: .18em;
        text-transform: uppercase;
        backdrop-filter: blur(4px);
    }
    .vo-status.redeemed { background: rgba(63, 111, 102, .55); }
    .vo-status.cancelled { background: rgba(165, 58, 58, .55); }
    .vo-middle {
        position: absolute;
        top: 61%;
        left: 50%;
        transform: translateX(-50%);
        width: 86%;
        max-width: 620px;
        text-align: center;
    }
    .vo-for {
        font-family: 'Playfair Display', serif;
        font-size: clamp(14px, 1.9vw, 18px);
        font-style: italic;
        letter-spacing: .02em;
        line-height: 1.2;
    }
    .vo-for strong { font-weight: 700; font-style: normal; }
    .vo-msg {
        margin: 6px auto 0;
        max-width: 86%;
        font-style: italic;
        font-size: clamp(11px, 1.3vw, 13px);
        line-height: 1.4;
        color: var(--tc-cream-soft);
        max-height: 2.8em;
        overflow: hidden;
    }
    .vo-code {
        margin-top: 10px;
        display: inline-block;
        padding: 7px 20px;
        border: 1.2px dashed rgba(243, 227, 204, .7);
        border-radius: 10px;
        min-width: 210px;
    }
    .vo-code small {
        display: block;
        font-size: 9px;
        letter-spacing: .3em;
        text-transform: uppercase;
        opacity: .78;
    }
    .vo-code strong {
        display: block;
        margin-top: 2px;
        font-family: 'Playfair Display', serif;
        font-size: clamp(16px, 2.1vw, 22px);
        letter-spacing: .22em;
    }
    .vo-amount {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
        font-size: clamp(20px, 3vw, 30px);
        letter-spacing: .04em;
        line-height: 1;
        margin-top: 8px;
        text-shadow: 0 1px 2px rgba(99, 54, 36, .25);
    }
    .vo-valid {
        margin-top: 6px;
        font-size: clamp(10px, 1.2vw, 12px);
        letter-spacing: .08em;
        color: var(--tc-cream-soft);
    }
    .voucher-caption {
        max-width: 820px;
        margin: 14px auto 0;
        text-align: center;
        color: #6b5a46;
        font-size: 13px;
        line-height: 1.5;
    }
    @media print {
        @page { size: A4; margin: 10mm; }
        body.voucher-page {
            background: #fff;
            padding: 0;
        }
        .voucher-toolbar, .voucher-caption { display: none !important; }
        .voucher-sheet {
            box-shadow: none;
            border-radius: 0;
            max-width: none;
            width: 190mm;
            height: 190mm;
            aspect-ratio: auto;
            margin: 0 auto;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .vo-status { display: none; }
    }
    @media (max-width: 540px) {
        .vo-code { min-width: 180px; padding: 8px 16px; }
    }
</style>
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

<div class="voucher-sheet" role="img" aria-label="Dárkový poukaz <?= e((string)$v['code']) ?>">
    <?php if (in_array((string)$v['status'], ['pending','redeemed','cancelled'], true)):
        $sLabel = [
            'pending'   => 'Nová objednávka',
            'redeemed'  => 'Uplatněno',
            'cancelled' => 'Zrušeno',
        ][(string)$v['status']];
    ?>
        <span class="vo-status <?= e((string)$v['status']) ?>"><?= e($sLabel) ?></span>
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
        <?php if ($amount !== ''): ?>
            <div class="vo-amount"><?= e($amount) ?></div>
        <?php endif; ?>
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
