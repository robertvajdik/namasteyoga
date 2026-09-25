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
        $error = 'Ochrana proti robotům selhala, zkuste to prosím znovu.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vyplňte prosím jméno a platný e-mail.';
    } elseif (strlen($pass) < 8) {
        $error = 'Heslo musí mít alespoň 8 znaků.';
    } elseif ($pass !== $pass2) {
        $error = 'Hesla se neshodují.';
    } else {
        $pdo = ny_db();
        $stmt = $pdo->prepare('SELECT id, is_guest FROM ny_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing && (int)$existing['is_guest'] === 0) {
            $error = 'Účet s tímto e-mailem již existuje. <a href="login.php">Přihlaste se</a>.';
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
            ny_flash_set('ok', 'Registrace hotova. Vítejte!');
            ny_redirect('rezervace.php');
        }
    }
}

ny_render_header('Registrace', 'register');
?>
<section class="section-title-block">
    <div class="eyebrow">Nový účet</div>
    <h1 class="page-title">Registrace</h1>
    <p class="page-lead">Vytvořte si účet a rezervujte lekce jedním kliknutím.</p>
</section>

<section class="card narrow">
    <?php if ($error): ?>
        <div class="flash flash-err"><?= $error /* may contain safe link markup */ ?></div>
    <?php endif; ?>
    <form method="post" novalidate data-recaptcha="register">
        <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
        <label>Jméno
            <input type="text" name="name" value="<?= e($name) ?>" required>
        </label>
        <label>E-mail
            <input type="email" name="email" value="<?= e($email) ?>" required>
        </label>
        <label>Telefon (nepovinné)
            <input type="tel" name="phone" value="<?= e($phone) ?>">
        </label>
        <label>Heslo (min. 8 znaků)
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>Heslo znovu
            <input type="password" name="password2" required autocomplete="new-password">
        </label>
        <button class="btn btn-primary btn-form" type="submit">Zaregistrovat</button>
    </form>
    <p class="hint hint-form">Už máte účet? <a href="login.php">Přihlaste se</a>.</p>
</section>
<?php ny_render_footer();
