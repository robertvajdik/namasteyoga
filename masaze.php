<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$massages = ny_massages_active();

ny_render_header('Masáže', 'masaze');
?>
<section class="section-title-block">
    <div class="eyebrow">Regenerace</div>
    <h1 class="page-title">Masáže</h1>
    <p class="page-lead">
        Klidný prostor, jemné světlo, zkušené ruce. Vyberte si masáž podle toho, co vaše tělo právě potřebuje.
    </p>
</section>

<?php if (!$massages): ?>
    <p class="hint">Nabídka masáží se aktualizuje.</p>
<?php else: ?>
<div class="massage-grid">
    <?php foreach ($massages as $m): ?>
        <article class="massage-card">
            <div class="massage-head">
                <h3><?= e((string)$m['name']) ?></h3>
                <?php if (!empty($m['duration'])): ?>
                    <span class="badge"><?= e((string)$m['duration']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($m['description'])): ?>
                <p><?= e((string)$m['description']) ?></p>
            <?php endif; ?>
            <div class="massage-foot">
                <div class="price-amount price-sm"><?= e((string)$m['price']) ?></div>
                <a class="btn btn-secondary btn-sm" href="kontakt.php">Objednat</a>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="cta-band">
    <div class="cta-inner">
        <h2>Rezervace masáže</h2>
        <p>Ozvěte se telefonicky nebo e-mailem, domluvíme si termín podle vás.</p>
        <a class="btn btn-primary btn-lg" href="kontakt.php">Napsat nám</a>
    </div>
</section>

<?php ny_render_footer();
