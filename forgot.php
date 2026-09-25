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
        $error = 'Ochrana proti robotům selhala, zkuste to prosím znovu.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Zadejte prosím platnou e-mailovou adresu.';
    } else {
        $pdo  = ny_db();
        $stmt = $pdo->prepare('SELECT id, display_name, is_guest FROM ny_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        // Only issue a reset for real (non-guest) users. Response is identical either way
        // so we never leak whether an e-mail exists in the DB (enumeration protection).
        if ($u && (int)$u['is_guest'] === 0) {
            $token    = ny_password_reset_create((int)$u['id']);
            $link     = ny_base_url() . '/reset.php?t=' . rawurlencode($token);
            $siteName = ny_setting('site_name', 'Studio Namasté');
            $body     = "Ahoj " . ($u['display_name'] ?: 'jogíne') . ",\n\n"
                      . "obdrželi jsme žádost o obnovení hesla k účtu na webu " . $siteName . ".\n\n"
                      . "Nové heslo si můžete nastavit tímto odkazem (platí 60 minut):\n"
                      . $link . "\n\n"
                      . "Pokud jste o obnovení hesla nežádali, tento e-mail ignorujte – k účtu se nikdo nedostane.\n\n"
                      . "-- \n" . $siteName;
            ny_mail($email, 'Obnovení hesla · ' . $siteName, $body);
        }
        $sent = true;
    }
}

ny_render_header('Zapomenuté heslo', 'login');
?>
<section class="section-title-block">
    <div class="eyebrow">Přihlášení</div>
    <h1 class="page-title">Zapomenuté heslo</h1>
    <p class="page-lead">Zadejte e-mail účtu a pošleme vám odkaz pro nastavení nového hesla.</p>
</section>

<section class="card narrow">
    <?php if ($sent): ?>
        <div class="flash flash-ok">Pokud e-mail patří k účtu, odeslali jsme na něj odkaz pro obnovení hesla. Zkontrolujte prosím i složku spam.</div>
        <p class="hint hint-form"><a href="login.php">Zpět na přihlášení</a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="flash flash-err"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate data-recaptcha="forgot">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <label>E-mail
                <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
            </label>
            <button class="btn btn-primary btn-form" type="submit">Odeslat odkaz</button>
        </form>
        <p class="hint hint-form"><a href="login.php">Zpět na přihlášení</a></p>
    <?php endif; ?>
</section>
<?php ny_render_footer();
