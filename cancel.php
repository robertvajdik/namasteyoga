<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ny_redirect('rezervace.php');
}
ny_csrf_check($_POST['csrf'] ?? null);

$user = ny_current_user();
if (!$user) {
    ny_redirect('login.php');
}

$classId   = (int)($_POST['class_id'] ?? 0);
$classDate = (string)($_POST['class_date'] ?? '');
$dateObj   = DateTimeImmutable::createFromFormat('Y-m-d', $classDate);
if (!$classId || !$dateObj) {
    ny_redirect('rezervace.php');
}

try {
    $res = ny_cancel_reservation((int)$user['id'], $classId, $classDate);
} catch (Throwable $e) {
    ny_flash_set('err', $e->getMessage());
    ny_redirect('rezervace.php?week=' . $classDate);
}

if (!$res['ok']) {
    ny_flash_set('err', t('cancel.flash.not_found'));
} elseif ($res['refunded_kc'] > 0) {
    ny_flash_set('ok', t('cancel.flash.ok_refund', $res['refunded_kc']));
} else {
    ny_flash_set('ok', t('cancel.flash.ok'));
}
ny_redirect('rezervace.php?week=' . $classDate);
