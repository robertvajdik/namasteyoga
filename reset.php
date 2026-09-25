<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$row   = $token !== '' ? ny_password_reset_find($token) : null;
$error = null;
$done  = false;

if (!$row) {
    ny_render_header('Obnovení hesla', 'login');
    ?>
    <section class="section-title-block">
        <div class="eyebrow">Přihlášení</div>
        <h1 class="page-title">Odkaz je neplatný</h1>
        <p class="page-lead">Odkaz pro obnovení hesla vypršel nebo již byl použit.</p>
    </section>
    <section class="card narrow">
        <p>Vyžádejte si prosím <a href="forgot.php">nový odkaz</a> pro obnovení hesla.</p>
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
        $error = 'Ochrana proti robotům selhala, zkuste to prosím znovu.';
    } elseif (strlen($pass) < 8) {
        $error = 'Heslo musí mít alespoň 8 znaků.';
    } elseif ($pass !== $pass2) {
        $error = 'Hesla se neshodují.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        ny_password_reset_consume((int)$row['id'], (int)$row['user_id'], $hash);
        $done = true;
    }
}

ny_render_header('Obnovení hesla', 'login');
?>
<section class="section-title-block">
    <div class="eyebrow">Přihlášení</div>
    <h1 class="page-title">Nové heslo</h1>
    <?php if (!$done): ?>
        <p class="page-lead">Nastavte si nové heslo pro účet <strong><?= e($row['email']) ?></strong>.</p>
    <?php endif; ?>
</section>

<section class="card narrow">
    <?php if ($done): ?>
        <div class="flash flash-ok">Heslo bylo změněno. Nyní se můžete přihlásit.</div>
        <p class="hint hint-form"><a href="login.php">Přejít na přihlášení</a></p>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="flash flash-err"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" novalidate data-recaptcha="reset">
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <input type="hidden" name="t"    value="<?= e($token) ?>">
            <label>Nové heslo (min. 8 znaků)
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label>Heslo znovu
                <input type="password" name="password2" required autocomplete="new-password">
            </label>
            <button class="btn btn-primary btn-form" type="submit">Nastavit heslo</button>
        </form>
    <?php endif; ?>
</section>
<?php ny_render_footer();
