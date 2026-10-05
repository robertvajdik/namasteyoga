<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$error   = null;
$regEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'login')) {
        ny_flash_set('err', t('login.err.recaptcha'));
        ny_redirect('login.php');
    }

    $regEmail  = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass      = (string)($_POST['password'] ?? '');
    $classId   = (int)($_POST['return_class_id'] ?? 0);
    $classDate = (string)($_POST['return_class_date'] ?? '');

    $stmt = ny_db()->prepare('SELECT * FROM ny_users WHERE email = ? LIMIT 1');
    $stmt->execute([$regEmail]);
    $u = $stmt->fetch();

    if ($u && (int)$u['is_guest'] === 0 && $u['password_hash'] && ny_verify_password($pass, $u['password_hash'])) {
        if (!password_verify($pass, $u['password_hash'])) {
            ny_db()->prepare('UPDATE ny_users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
        }
        ny_login_user((int)$u['id'], false);
        ny_flash_set('ok', t('login.welcome', $u['display_name']));

        // Came from a class card → book that class now.
        if ($classId > 0 && $classDate !== '') {
            $r = ny_reserve_class((int)$u['id'], $classId, $classDate);
            ny_flash_set($r['ok'] ? 'ok' : 'err', $r['ok']
                ? t('reserve.flash.confirmed', $r['class']['name'], $r['date']->format('j. n. Y'))
                : $r['msg']);
            ny_redirect('rezervace.php?week=' . rawurlencode($classDate));
        }
        ny_redirect('rezervace.php');
    }
    $error = t('login.err.bad_credentials');
}

$returnClassDate = (string)($_GET['class_date'] ?? '');
$returnClassId   = (int)($_GET['class_id'] ?? 0);

ny_render_header(t('login.title'), 'login', ['description' => t('login.meta.description'), 'noindex' => true]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('login.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('login.title')) ?></h1>
    <p class="page-lead"><?= e(t('login.lead')) ?></p>
</section>

<section class="card narrow">
    <?php if ($error): ?>
        <div class="flash flash-err"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" novalidate data-recaptcha="login">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <input type="hidden" name="return_class_date" value="<?= e($returnClassDate) ?>">
        <input type="hidden" name="return_class_id" value="<?= $returnClassId ?>">
        <label><?= e(t('login.field.email')) ?>
            <input type="email" name="email" value="<?= e($regEmail) ?>" required autocomplete="email">
        </label>
        <label><?= e(t('login.field.password')) ?>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary btn-form" type="submit"><?= e(t('login.btn.login')) ?></button>
    </form>
    <p class="hint hint-form"><?= e(t('auth.no.account')) ?> <a href="register.php"><?= e(t('auth.register.link')) ?></a>. · <a href="forgot.php"><?= e(t('auth.forgot')) ?></a></p>
</section>
<?php ny_render_footer();
