<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$row   = $token !== '' ? ny_password_reset_find($token) : null;
$error = null;
$done  = false;

if (!$row) {
    ny_render_header(t('reset.title'), 'login', ['description' => t('reset.meta.description')]);
    ?>
    <section class="section-title-block">
        <div class="eyebrow"><?= e(t('reset.eyebrow')) ?></div>
        <h1 class="page-title"><?= e(t('reset.invalid.h')) ?></h1>
        <p class="page-lead"><?= e(t('reset.invalid.lead')) ?></p>
    </section>
    <section class="card narrow">
        <p><?= t('reset.invalid.request_new') ?></p>
    </section>
    <?php
    ny_render_footer();
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'reset')) {
        $error = t('reset.err.recaptcha');
    } elseif (strlen($pass) < 8) {
        $error = t('reset.err.password_short');
    } elseif ($pass !== $pass2) {
        $error = t('reset.err.password_mismatch');
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        ny_password_reset_consume((int)$row['id'], (int)$row['user_id'], $hash);
        $done = true;
    }
}

ny_render_header(t('reset.title'), 'login', ['description' => t('reset.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('reset.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('reset.new.h')) ?></h1>
    <?php if (!$done): ?>
        <p class="page-lead"><?= sprintf(e(t('reset.new.lead')), '<strong>' . e($row['email']) . '</strong>') ?></p>
    <?php endif; ?>
</section>

<section class="card narrow">
    <?php if ($done): ?>
        <div class="flash flash-ok"><?= e(t('reset.done')) ?></div>
        <p class="hint hint-form"><a href="login.php"><?= e(t('reset.go_login')) ?></a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="flash flash-err"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate data-recaptcha="reset">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="t"    value="<?= e($token) ?>">
            <label><?= e(t('reset.field.password')) ?>
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label><?= e(t('reset.field.password2')) ?>
                <input type="password" name="password2" required autocomplete="new-password">
            </label>
            <button class="btn btn-primary btn-form" type="submit"><?= e(t('reset.btn.submit')) ?></button>
        </form>
    <?php endif; ?>
</section>
<?php ny_render_footer();
