<?php
declare(strict_types=1);

/**
 * One-shot admin bootstrap.
 *
 * Purpose: allow the very first admin to be created safely before anyone can
 * reach the /admin/ area. As soon as at least one admin exists, this page
 * refuses to run — from that point on, promotions happen via /admin/users.php.
 *
 * Usage:
 *   1. Register a normal user via /register.php.
 *   2. Open /admin_setup.php in the browser.
 *   3. Enter that user's e-mail; they become admin and can log into /admin/.
 */

require __DIR__ . '/src/layout.php';

$pdo = ny_db();

// Ensure the is_admin column exists on legacy installs.
$hasCol = $pdo->query("SHOW COLUMNS FROM ny_users LIKE 'is_admin'")->fetch();
if (!$hasCol) {
    $pdo->exec('ALTER TABLE ny_users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_guest');
}

$hasAdmin = (int)$pdo->query('SELECT COUNT(*) FROM ny_users WHERE is_admin = 1')->fetchColumn() > 0;

$msg = null;

if ($hasAdmin) {
    $msg = ['type' => 'err', 'text' => 'Administrátor už existuje. Další oprávnění spravujte v /admin/users.php.'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ny_csrf_check($_POST['csrf'] ?? null);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = ['type' => 'err', 'text' => 'Zadejte platný e-mail.'];
    } else {
        $stmt = $pdo->prepare('SELECT id, display_name, is_guest FROM ny_users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u) {
            $msg = ['type' => 'err', 'text' => 'Uživatel nenalezen. Nejdřív se zaregistrujte.'];
        } elseif ((int)$u['is_guest'] === 1) {
            $msg = ['type' => 'err', 'text' => 'Host nemůže být admin. Zaregistrujte se s heslem.'];
        } else {
            $pdo->prepare('UPDATE ny_users SET is_admin = 1 WHERE id = ?')->execute([$u['id']]);
            $msg = ['type' => 'ok', 'text' => 'Hotovo. ' . $u['display_name'] . ' je nyní administrátorem – přihlaste se a otevřete /admin/.'];
            $hasAdmin = true;
        }
    }
}

ny_render_header('Bootstrap adminu', '');
?>
<section class="section-title-block">
    <div class="eyebrow">První spuštění</div>
    <h1 class="page-title">Bootstrap administrátora</h1>
    <p class="page-lead">Jednorázově nastavte prvního admina. Poté tento soubor smažte nebo přejmenujte.</p>
</section>

<section class="card narrow">
    <?php if ($msg): ?>
        <div class="flash flash-<?= e($msg['type']) ?>"><?= e($msg['text']) ?></div>
    <?php endif; ?>

    <?php if ($hasAdmin): ?>
        <p class="hint">Pro bezpečnost už tento formulář neběží. Přejděte na <a href="login.php">přihlášení</a>.</p>
    <?php else: ?>
        <form method="post" novalidate>
            <input type="hidden" name="csrf" value="<?= e(ny_csrf_token()) ?>">
            <label>E-mail existujícího uživatele
                <input type="email" name="email" required autocomplete="email">
            </label>
            <button class="btn btn-primary btn-form" type="submit">Nastavit jako admina</button>
        </form>
        <p class="hint hint-form">Uživatel musí být nejdřív zaregistrovaný v <a href="register.php">Registraci</a>.</p>
    <?php endif; ?>
</section>
<?php ny_render_footer();
