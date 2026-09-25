<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$email = trim((string)($_GET['e'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

ny_render_header('Přihlášení k odběru novinek', '');
?>
<section class="section-title-block">
    <div class="eyebrow">Newsletter</div>
    <h1 class="page-title">Děkujeme za přihlášení!</h1>
    <p class="page-lead">Budeme vás občas informovat o rozvrhu, akcích a workshopech studia.</p>
</section>

<div class="card text-center empty-state newsletter-thanks">
    <div class="newsletter-thanks-icon" aria-hidden="true">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6h16v12H4z"/>
            <path d="M4 6l8 7 8-7"/>
            <path d="M9 14l2 2 4-4"/>
        </svg>
    </div>
    <div class="empty-state-title">Přihlášení bylo uloženo</div>
    <p class="text-muted empty-state-hint">
        <?php if ($email !== ''): ?>
            Adresu <strong><?= e($email) ?></strong> jsme přidali do seznamu odběratelů.
        <?php else: ?>
            Vaši e-mailovou adresu jsme přidali do seznamu odběratelů.
        <?php endif; ?>
        Odhlásit se můžete kdykoli odkazem v patičce každého e-mailu.
    </p>
    <div class="row row-center newsletter-thanks-actions">
        <a class="btn btn-primary" href="index.php">Zpět na úvod</a>
        <a class="btn btn-secondary" href="rezervace.php">Prohlédnout rozvrh</a>
    </div>
</div>
<?php ny_render_footer();
