<?php
declare(strict_types=1);

require __DIR__ . '/src/layout.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ny_redirect('rezervace.php');
}
ny_csrf_check($_POST['csrf'] ?? null);

$user = ny_current_user();
if (!$user) {
    ny_flash_set('err', 'Pro rezervaci se prosím přihlaste.');
    ny_redirect('login.php');
}

$classId   = (int)($_POST['class_id'] ?? 0);
$classDate = (string)($_POST['class_date'] ?? '');
$dateObj   = DateTimeImmutable::createFromFormat('Y-m-d', $classDate);
if (!$classId || !$dateObj || $dateObj->format('Y-m-d') !== $classDate) {
    ny_flash_set('err', 'Neplatné údaje o lekci.');
    ny_redirect('rezervace.php');
}

$today = new DateTimeImmutable('today');
if ($dateObj < $today) {
    ny_flash_set('err', 'Nelze rezervovat lekci v minulosti.');
    ny_redirect('rezervace.php');
}

$pdo = ny_db();
$pdo->beginTransaction();
try {
    $c = $pdo->prepare('SELECT * FROM ny_classes WHERE id = ? AND active = 1 FOR UPDATE');
    $c->execute([$classId]);
    $class = $c->fetch();
    if (!$class) {
        throw new RuntimeException('Lekce nenalezena.');
    }
    // Class must match the weekday.
    if ((int)$class['day_of_week'] !== (int)$dateObj->format('N')) {
        throw new RuntimeException('Datum nesouhlasí s dnem, kdy lekce probíhá.');
    }

    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM ny_reservations
          WHERE class_id = ? AND class_date = ? AND status = 'booked' FOR UPDATE"
    );
    $countStmt->execute([$classId, $classDate]);
    $taken = (int)$countStmt->fetchColumn();

    if ($taken >= (int)$class['capacity']) {
        throw new RuntimeException('Lekce je bohužel obsazená.');
    }

    // Insert (or reactivate a cancelled) reservation.
    $ins = $pdo->prepare(
        "INSERT INTO ny_reservations (user_id, class_id, class_date, status)
         VALUES (?, ?, ?, 'booked')
         ON DUPLICATE KEY UPDATE status = 'booked', created_at = CURRENT_TIMESTAMP"
    );
    $ins->execute([$user['id'], $classId, $classDate]);

    $pdo->commit();
    ny_flash_set('ok', 'Rezervace potvrzena: ' . $class['name'] . ' – ' . $dateObj->format('j. n. Y'));
} catch (Throwable $ex) {
    $pdo->rollBack();
    ny_flash_set('err', $ex->getMessage());
}

ny_redirect('rezervace.php?week=' . $classDate);
