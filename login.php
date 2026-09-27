<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$errors = ['registered' => null, 'guest' => null];
$mode   = $_POST['mode'] ?? '';

function _returnTo(): string {
    $classDate = (string)($_GET['class_date'] ?? $_POST['return_class_date'] ?? '');
    if ($classDate && DateTimeImmutable::createFromFormat('Y-m-d', $classDate)) {
        return 'rezervace.php?week=' . rawurlencode($classDate);
    }
    return 'rezervace.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'login')) {
        ny_flash_set('err', t('login.err.recaptcha'));
        ny_redirect('login.php');
    }
    $pdo = ny_db();

    if ($mode === 'registered') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');
        $stmt  = $pdo->prepare('SELECT * FROM ny_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if ($u && (int)$u['is_guest'] === 0 && $u['password_hash'] && ny_verify_password($pass, $u['password_hash'])) {
            if (!password_verify($pass, $u['password_hash'])) {
                $new = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE ny_users SET password_hash = ? WHERE id = ?')
                    ->execute([$new, $u['id']]);
            }
            ny_login_user((int)$u['id'], false);
            ny_flash_set('ok', t('login.welcome', $u['display_name']));
            ny_redirect(_returnTo());
        }
        $errors['registered'] = t('login.err.bad_credentials');
    } elseif ($mode === 'guest') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $name  = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            $errors['guest'] = t('login.err.name_email');
        } else {
            $stmt = $pdo->prepare('SELECT * FROM ny_users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u && (int)$u['is_guest'] === 0) {
                $errors['guest'] = t('login.err.email_has_account');
            } else {
                if ($u) {
                    $pdo->prepare('UPDATE ny_users SET display_name = ?, phone = ? WHERE id = ?')
                        ->execute([$name, $phone ?: null, $u['id']]);
                    $id = (int)$u['id'];
                } else {
                    $pdo->prepare(
                        'INSERT INTO ny_users (email, display_name, phone, is_guest) VALUES (?, ?, ?, 1)'
                    )->execute([$email, $name, $phone ?: null]);
                    $id = (int)$pdo->lastInsertId();
                }
                ny_login_user($id, true);
                ny_flash_set('ok', t('login.welcome_guest'));
                ny_redirect(_returnTo());
            }
        }
    }
}

$returnClassDate = (string)($_GET['class_date'] ?? '');

ny_render_header(t('login.title'), 'login', ['description' => t('login.meta.description')]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('login.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('login.title')) ?></h1>
    <p class="page-lead"><?= e(t('login.lead')) ?></p>
</section>

<div class="cols">
    <section class="card">
        <h2><?= e(t('login.section.registered.h')) ?></h2>
        <p class="card-lead"><?= e(t('login.section.registered.lead')) ?></p>
        <?php if ($errors['registered']): ?>
            <div class="flash flash-err"><?= e($errors['registered']) ?></div>
        <?php endif; ?>
        <form method="post" novalidate data-recaptcha="login">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="mode" value="registered">
            <input type="hidden" name="return_class_date" value="<?= e($returnClassDate) ?>">
            <label><?= e(t('login.field.email')) ?>
                <input type="email" name="email" required autocomplete="email">
            </label>
            <label><?= e(t('login.field.password')) ?>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="btn btn-primary btn-form" type="submit"><?= e(t('login.btn.login')) ?></button>
        </form>
        <p class="hint hint-form"><?= e(t('auth.no.account')) ?> <a href="register.php"><?= e(t('auth.register.link')) ?></a>. · <a href="forgot.php"><?= e(t('auth.forgot')) ?></a></p>
    </section>

    <section class="card muted" id="guest">
        <h2><?= e(t('login.section.guest.h')) ?></h2>
        <p class="card-lead"><?= e(t('login.section.guest.lead')) ?></p>
        <?php if ($errors['guest']): ?>
            <div class="flash flash-err"><?= e($errors['guest']) ?></div>
        <?php endif; ?>
        <form method="post" novalidate data-recaptcha="login">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="mode" value="guest">
            <input type="hidden" name="return_class_date" value="<?= e($returnClassDate) ?>">
            <label><?= e(t('login.field.name')) ?>
                <input type="text" name="name" required>
            </label>
            <label><?= e(t('login.field.email')) ?>
                <input type="email" name="email" required>
            </label>
            <label><?= e(t('login.field.phone_optional')) ?>
                <input type="tel" name="phone">
            </label>
            <button class="btn btn-sand btn-form" type="submit"><?= e(t('login.btn.continue')) ?></button>
        </form>
    </section>
</div>
<?php ny_render_footer();
