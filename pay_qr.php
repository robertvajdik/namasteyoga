<?php
declare(strict_types=1);

// Tags an existing booking as "paying by QR" when the member opens the QR
// modal for it, so admin can see who intends to pay by bank transfer.
require __DIR__ . '/src/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
ny_csrf_check($_POST['csrf'] ?? null);

$user = ny_current_user();
if (!$user) {
    http_response_code(403);
    exit;
}

$classId   = (int)($_POST['class_id'] ?? 0);
$classDate = (string)($_POST['class_date'] ?? '');
if (!$classId || !DateTimeImmutable::createFromFormat('Y-m-d', $classDate)) {
    http_response_code(400);
    exit;
}

// Only upgrade unpaid bookings – never overwrite a credit payment.
ny_db()->prepare(
    "UPDATE ny_reservations SET payment_method = 'qr'
      WHERE user_id = ? AND class_id = ? AND class_date = ?
        AND status = 'booked' AND payment_method = 'none'"
)->execute([(int)$user['id'], $classId, $classDate]);

http_response_code(204);
