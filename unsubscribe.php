<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$token = (string)($_GET['t'] ?? '');
$done  = $token !== '' && ny_newsletter_unsubscribe_by_token($token);

ny_render_header('Odhlášení z newsletteru', '');
?>
<section class="section-title-block">
    <div class="eyebrow">Newsletter</div>
    <h1 class="page-title">Odhlášení z odběru novinek</h1>
</section>

<div class="card text-center empty-state">
    <?php if ($done): ?>
        <div class="empty-state-title">Odhlášení proběhlo</div>
        <p class="text-muted empty-state-hint">Tento e-mail už od nás nebude dostávat žádné novinky.</p>
    <?php elseif ($token === ''): ?>
        <div class="empty-state-title">Chybí odhlašovací odkaz</div>
        <p class="text-muted empty-state-hint">Použijte odkaz z e-mailu, který jsme vám zaslali.</p>
    <?php else: ?>
        <div class="empty-state-title">Odkaz nebyl rozpoznán</div>
        <p class="text-muted empty-state-hint">Možná jste už dříve odhlášeni, nebo je odkaz neplatný.</p>
    <?php endif; ?>
    <a class="btn btn-primary" href="index.php">Zpět na úvod</a>
</div>
<?php ny_render_footer();
