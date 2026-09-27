<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ny_redirect('rezervace.php');
}
ny_csrf_check($_POST['csrf'] ?? null);
if (!ny_recaptcha_verify($_POST['g-recaptcha-response'] ?? null, 'reserve')) {
    ny_flash_set('err', t('reserve.err.recaptcha'));
    ny_redirect('rezervace.php');
}

$user = ny_current_user();
if (!$user) {
    ny_flash_set('err', t('reserve.err.login_required'));
    ny_redirect('login.php');
}

$classId   = (int)($_POST['class_id'] ?? 0);
$classDate = (string)($_POST['class_date'] ?? '');

$result = ny_reserve_class((int)$user['id'], $classId, $classDate);
if ($result['ok']) {
    ny_flash_set('ok', t('reserve.flash.confirmed', $result['class']['name'], $result['date']->format('j. n. Y')));
} else {
    ny_flash_set('err', $result['msg']);
}

ny_redirect('rezervace.php?week=' . rawurlencode($classDate));
