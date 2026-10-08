<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ny_redirect('rezervace.php');
}
ny_csrf_check($_POST['csrf'] ?? null);

$user = ny_current_user();
if (!$user) {
    ny_flash_set('err', t('reserve.err.login_required'));
    ny_redirect('login.php');
}

$classId   = (int)($_POST['class_id'] ?? 0);
$classDate = (string)($_POST['class_date'] ?? '');
$pay       = (string)($_POST['pay'] ?? 'none');
if (!in_array($pay, ['none', 'credits'], true)) {
    $pay = 'none';
}

$result = ny_reserve_class((int)$user['id'], $classId, $classDate, $pay);
if ($result['ok']) {
    if (($result['payment_method'] ?? '') === 'credits') {
        ny_flash_set('ok', t('reserve.flash.confirmed_credits', $result['class']['name'], $result['date']->format('j. n. Y'), (int)$result['price_kc']));
    } else {
        ny_flash_set('ok', t('reserve.flash.confirmed', $result['class']['name'], $result['date']->format('j. n. Y')));
    }
} else {
    ny_flash_set('err', $result['msg']);
}

ny_redirect('rezervace.php?week=' . rawurlencode($classDate));
