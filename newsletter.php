<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ny_redirect('index.php');
}
ny_csrf_check($_POST['csrf'] ?? null);

$email  = (string)($_POST['email'] ?? '');
$name   = trim((string)($_POST['name'] ?? ''));
$source = (string)($_POST['source'] ?? 'footer');

$ok = false;
if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'newsletter')) {
    ny_flash_set('err', 'Ochrana proti robotům selhala, zkuste to prosím znovu.');
} else {
    try {
        ny_newsletter_subscribe($email, $name, $source);
        $ok = true;
        ny_flash_set('ok', 'Děkujeme! E-mail jsme přidali do odběru novinek.');
    } catch (Throwable $e) {
        ny_flash_set('err', $e->getMessage());
    }
}

// Landing-page sign-ups get a full "thank you" confirmation page. Every other
// entry point (footer form on any page, profile toggle) keeps the inline
// flash-message flow and returns to where the user came from.
if ($ok && $source === 'landing') {
    ny_redirect('newsletter-thanks.php?e=' . rawurlencode($email));
}

$back = (string)($_POST['return'] ?? 'index.php');
if (!preg_match('~^[a-zA-Z0-9_\-./?&=%]+$~', $back)) {
    $back = 'index.php';
}
ny_redirect($back);
