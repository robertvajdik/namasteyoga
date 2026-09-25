<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/layout.php';

function ny_admin_render_header(string $title, string $active = ''): void {
    ny_send_security_headers();
    $user = ny_require_admin();
    ?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Admin · Namasté</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
</head>
<body class="admin-body">
<input type="checkbox" id="admin-nav-toggle" class="admin-toggle-input" aria-hidden="true" autocomplete="off">
<label for="admin-nav-toggle" class="admin-toggle" role="button" tabindex="0">
    <?= ny_icon('menu', 20) ?> Menu
</label>
<div class="admin-layout">
    <aside class="admin-sidebar" role="navigation" aria-label="Admin menu">
        <div class="admin-brand">
            <img src="../assets/logoCream.png" alt="Namasté">
            <div>
                <strong>Namasté</strong>
                <small>Administrace</small>
            </div>
        </div>
        <nav class="admin-nav">
            <a href="index.php"        class="<?= $active === 'dashboard'    ? 'is-active' : '' ?>"><?= ny_icon('calendar', 16) ?> Dashboard</a>
            <div class="sep">Provoz</div>
            <a href="reservations.php" class="<?= $active === 'reservations' ? 'is-active' : '' ?>"><?= ny_icon('calendar', 16) ?> Rezervace</a>
            <a href="classes.php"      class="<?= $active === 'classes'      ? 'is-active' : '' ?>"><?= ny_icon('menu', 16) ?> Lekce</a>
            <a href="users.php"        class="<?= $active === 'users'        ? 'is-active' : '' ?>"><?= ny_icon('user', 16) ?> Uživatelé</a>
            <div class="sep">Obsah</div>
            <a href="teachers.php"     class="<?= $active === 'teachers'     ? 'is-active' : '' ?>"><?= ny_icon('user', 16) ?> Lektoři</a>
            <a href="massages.php"     class="<?= $active === 'massages'     ? 'is-active' : '' ?>"><?= ny_icon('menu', 16) ?> Masáže</a>
            <a href="categories.php"   class="<?= $active === 'categories'   ? 'is-active' : '' ?>"><?= ny_icon('menu', 16) ?> Kategorie lekcí</a>
            <a href="gallery.php"      class="<?= $active === 'gallery'      ? 'is-active' : '' ?>"><?= ny_icon('image', 16) ?> Galerie</a>
            <a href="newsletter.php"   class="<?= $active === 'newsletter'   ? 'is-active' : '' ?>"><?= ny_icon('mail', 16) ?> Newsletter</a>
            <div class="sep">Web</div>
            <a href="settings.php"     class="<?= $active === 'settings'     ? 'is-active' : '' ?>"><?= ny_icon('settings', 16) ?> Nastavení webu</a>
            <div class="sep">Účet</div>
            <a href="../index.php"><?= ny_icon('chevron-left', 16) ?> Zpět na web</a>
            <a href="../logout.php"><?= ny_icon('log-out', 16) ?> Odhlásit</a>
        </nav>
        <div class="admin-foot">
            Přihlášen: <strong><?= e($user['display_name']) ?></strong>
        </div>
    </aside>
    <label for="admin-nav-toggle" class="admin-scrim" aria-hidden="true"></label>
    <main class="admin-main">
        <div class="admin-header">
            <h1><?= e($title) ?></h1>
            <div class="who">Studio Namasté · <?= e(date('j. n. Y')) ?></div>
        </div>
<?php foreach (ny_flash_get() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php
}

function ny_admin_render_footer(): void {
    ?>
    </main>
</div>
</body>
</html><?php
}
