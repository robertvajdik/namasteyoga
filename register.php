<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$error = null;
$name = $email = $phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'register')) {
        $error = t('register.err.recaptcha');
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('register.err.name_email');
    } elseif (strlen($pass) < 8) {
        $error = t('register.err.password_short');
    } elseif ($pass !== $pass2) {
        $error = t('register.err.password_mismatch');
    } else {
        $pdo = ny_db();
        $stmt = $pdo->prepare('SELECT id, is_guest FROM ny_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing && (int)$existing['is_guest'] === 0) {
            $error = t('register.err.email_taken');
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            if ($existing) {
                $pdo->prepare(
                    'UPDATE ny_users SET display_name = ?, phone = ?, password_hash = ?, is_guest = 0 WHERE id = ?'
                )->execute([$name, $phone ?: null, $hash, $existing['id']]);
                $id = (int)$existing['id'];
            } else {
                $pdo->prepare(
                    'INSERT INTO ny_users (email, display_name, phone, password_hash, is_guest)
                     VALUES (?, ?, ?, ?, 0)'
                )->execute([$email, $name, $phone ?: null, $hash]);
                $id = (int)$pdo->lastInsertId();
            }

            $adminEmail = ny_admin_notify_email();
            if ($adminEmail !== '') {
                $siteName = ny_setting('site_name', 'Studio Namasté');
                $body = "V administraci byl vytvořen nový uživatelský účet.\n\n"
                      . "Jméno:   " . $name . "\n"
                      . "E-mail:  " . $email . "\n"
                      . "Telefon: " . ($phone !== '' ? $phone : '—') . "\n"
                      . "Čas:     " . date('d.m.Y H:i') . "\n\n"
                      . "Detail: " . ny_base_url() . "/admin/users.php\n\n"
                      . "-- \n" . $siteName;
                ny_mail($adminEmail, 'Nová registrace: ' . $name, $body);
            }

            ny_login_user($id, false);
            ny_flash_set('ok', t('register.flash.welcome'));
            ny_redirect('rezervace.php');
        }
    }
}

ny_render_header(t('register.title'), 'register', ['description' => t('register.meta.description'), 'noindex' => true]);
?>
<section class="section-title-block">
    <div class="eyebrow"><?= e(t('register.eyebrow')) ?></div>
    <h1 class="page-title"><?= e(t('register.title')) ?></h1>
    <p class="page-lead"><?= e(t('register.lead')) ?></p>
</section>

<section class="card narrow">
    <?php if ($error): ?>
        <div class="flash flash-err"><?= $error /* may contain safe link markup */ ?></div>
    <?php endif; ?>
    <form method="post" novalidate data-recaptcha="register">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <label><?= e(t('register.field.name')) ?>
            <input type="text" name="name" value="<?= e($name) ?>" required>
        </label>
        <label><?= e(t('register.field.email')) ?>
            <input type="email" name="email" value="<?= e($email) ?>" required>
        </label>
        <label><?= e(t('register.field.phone_optional')) ?>
            <input type="tel" name="phone" value="<?= e($phone) ?>">
        </label>
        <label><?= e(t('register.field.password')) ?>
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label><?= e(t('register.field.password2')) ?>
            <input type="password" name="password2" required autocomplete="new-password">
        </label>
        <button class="btn btn-primary btn-form" type="submit"><?= e(t('register.btn.submit')) ?></button>
    </form>
    <p class="hint hint-form"><?= e(t('register.have_account')) ?> <a href="login.php"><?= e(t('register.login_link')) ?></a>.</p>
</section>
<?php ny_render_footer();
