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

$pdo = ny_db();
$upd = $pdo->prepare(
    "UPDATE ny_reservations
        SET status = 'cancelled'
      WHERE user_id = ? AND class_id = ? AND class_date = ? AND status = 'booked'"
);
$upd->execute([$user['id'], $classId, $classDate]);

if ($upd->rowCount() > 0) {
    ny_flash_set('ok', 'Rezervace byla zrušena.');
} else {
    ny_flash_set('err', 'Rezervace nenalezena.');
}
ny_redirect('rezervace.php?week=' . $classDate);
