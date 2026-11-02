<?php
declare(strict_types=1);

// Admin-only: block / release places in one class occurrence from the
// public schedule (rezervace.php).
require __DIR__ . '/src/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ny_redirect('rezervace.php');
}
ny_csrf_check($_POST['csrf'] ?? null);

$user = ny_current_user();
if (!ny_is_admin($user)) {
    http_response_code(403);
    exit('Forbidden');
}

$classId   = (int)($_POST['class_id'] ?? 0);
$classDate = (string)($_POST['class_date'] ?? '');
$delta     = (int)($_POST['delta'] ?? 0) >= 0 ? 1 : -1;

$dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $classDate);
if ($classId <= 0 || !$dateObj || $dateObj->format('Y-m-d') !== $classDate) {
    ny_flash_set('err', t('reserve.err.not_found'));
    ny_redirect('rezervace.php');
}

$res = ny_class_block_change($classId, $classDate, $delta, (int)$user['id']);
if (!$res['ok']) {
    ny_flash_set('err', $res['msg']);
}

ny_redirect(ny_booking_return_url('', $classDate));
