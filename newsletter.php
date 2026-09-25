<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);

    $email  = (string)($_POST['email'] ?? '');
    $name   = trim((string)($_POST['name'] ?? ''));
    $source = (string)($_POST['source'] ?? 'page');

    $ok = false;
    if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'newsletter')) {
        ny_flash_set('err', 'Ochrana proti robotům selhala, zkuste to prosím znovu.');
    } else {
        try {
            ny_newsletter_subscribe($email, $name, $source);
            $ok = true;
        } catch (Throwable $e) {
            ny_flash_set('err', $e->getMessage());
        }
    }

    if ($ok) {
        ny_redirect('newsletter-thanks.php?e=' . rawurlencode($email));
    }
    ny_redirect('newsletter.php');
}

$user      = ny_current_user();
$prefEmail = $user ? (string)$user['email'] : '';
$prefName  = $user ? (string)$user['display_name'] : '';

ny_render_header('Odběr novinek', '');
?>
<section class="section-title-block">
    <div class="eyebrow">Newsletter</div>
    <h1 class="page-title">Odběr novinek</h1>
    <p class="page-lead">Občasné novinky o rozvrhu, akcích a workshopech studia. Odhlásit se můžete kdykoli.</p>
</section>

<div class="card newsletter-page">
    <form method="post" class="newsletter-page-form" data-recaptcha="newsletter" novalidate>
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="source" value="page">
        <label>Jméno (nepovinné)
            <input type="text" name="name" value="<?= e($prefName) ?>" autocomplete="name">
        </label>
        <label>E-mail
            <input type="email" name="email" value="<?= e($prefEmail) ?>" required autocomplete="email">
        </label>
        <button class="btn btn-primary" type="submit">Přihlásit k odběru</button>
        <p class="hint">Odesláním souhlasíte se zasíláním e-mailů o dění ve studiu. Odhlásit se můžete kdykoli odkazem v každém e-mailu.</p>
    </form>
</div>
<?php ny_render_footer();
