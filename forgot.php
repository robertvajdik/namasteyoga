<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$error = null;
$sent  = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));

    if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'forgot')) {
        $error = t('forgot.err.recaptcha');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('forgot.err.email');
    } else {
        $pdo  = ny_db();
        $stmt = $pdo->prepare('SELECT id, display_name, is_guest FROM ny_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        // Only issue a reset for real (non-guest) users. Response is identical either way
        // so we never leak whether an e-mail exists in the DB (enumeration protection).
        if ($u && (int)$u['is_guest'] === 0) {
            ny_password_reset_send((int)$u['id'], $email, (string)$u['display_name']);
        }
        $sent = true;
    }
}

ny_render_header(t('forgot.title'), 'login', ['description' => t('forgot.meta.description'), 'noindex' => true]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('forgot.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('forgot.title')) ?></h1>
    <p class="page-lead"><?= e(t('forgot.lead')) ?></p>
</section>

<section class="card narrow">
    <?php if ($sent): ?>
        <div class="flash flash-ok"><?= e(t('forgot.sent')) ?></div>
        <p class="hint hint-form"><a href="login.php"><?= e(t('forgot.back_login')) ?></a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="flash flash-err"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate data-recaptcha="forgot">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <label><?= e(t('forgot.field.email')) ?>
                <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
            </label>
            <button class="btn btn-primary btn-form" type="submit"><?= e(t('forgot.btn.submit')) ?></button>
        </form>
        <p class="hint hint-form"><a href="login.php"><?= e(t('forgot.back_login')) ?></a></p>
    <?php endif; ?>
</section>
<?php ny_render_footer();
