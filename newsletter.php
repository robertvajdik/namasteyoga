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
        ny_flash_set('err', t('nl.err.recaptcha'));
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

ny_render_header(t('nl.title'), '', ['description' => t('nl.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('nl.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('nl.title')) ?></h1>
    <p class="page-lead"><?= e(t('nl.lead')) ?></p>
</section>

<div class="card newsletter-page">
    <form method="post" class="newsletter-page-form" data-recaptcha="newsletter" novalidate>
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="source" value="page">
        <label><?= e(t('nl.field.name')) ?>
            <input type="text" name="name" value="<?= e($prefName) ?>" autocomplete="name">
        </label>
        <label><?= e(t('nl.field.email')) ?>
            <input type="email" name="email" value="<?= e($prefEmail) ?>" required autocomplete="email">
        </label>
        <button class="btn btn-primary" type="submit"><?= e(t('nl.btn.submit')) ?></button>
        <p class="hint"><?= e(t('nl.hint')) ?></p>
    </form>
</div>
<?php ny_render_footer();
