<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ny_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function ny_csrf_token(): string {
    ny_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function ny_csrf_check(?string $token): void {
    ny_session_start();
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('Neplatný CSRF token.');
    }
}

function ny_is_admin(?array $user = null): bool {
    $user = $user ?? ny_current_user();
    return $user && (int)($user['is_admin'] ?? 0) === 1;
}

function ny_require_admin(): array {
    $user = ny_current_user();
    if (!ny_is_admin($user)) {
        ny_flash_set('err', 'Přístup pouze pro administrátory.');
        ny_redirect('../login.php');
    }
    return $user;
}

function ny_current_user(): ?array {
    ny_session_start();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = ny_db()->prepare('SELECT * FROM ny_users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    return $u ?: null;
}

function ny_login_user(int $userId, bool $isGuest = false): void {
    ny_session_start();
    session_regenerate_id(true);
    $_SESSION['user_id']  = $userId;
    $_SESSION['is_guest'] = $isGuest;
    if (!$isGuest) {
        try {
            ny_db()->prepare('UPDATE ny_users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
        } catch (Throwable $e) {
            // Column may not exist yet on legacy installs; ignored.
        }
    }
}

function ny_logout(): void {
    ny_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Password verification – supports:
//   * $wp$...  – WordPress 6.8+ (bcrypt over hmac-sha384(password, 'wp-sha384'))
//   * $P$.../$H$... – PHPass portable (legacy WordPress / phpBB)
//   * $2y$.../$argon2... – native password_hash() output for new users
// ---------------------------------------------------------------------------
function ny_verify_password(string $password, string $hash): bool {
    if ($hash === '') {
        return false;
    }
    if (str_starts_with($hash, '$wp$')) {
        $bcrypt   = substr($hash, 4);
        $prepared = base64_encode(hash_hmac('sha384', trim($password), 'wp-sha384', true));
        return password_verify($prepared, $bcrypt);
    }
    if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
        return ny_phpass_verify($password, $hash);
    }
    return password_verify($password, $hash);
}

function ny_phpass_verify(string $password, string $hash): bool {
    if (strlen($hash) !== 34) {
        return false;
    }
    $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $countLog2 = strpos($itoa64, $hash[3]);
    if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
        return false;
    }
    $count = 1 << $countLog2;
    $salt  = substr($hash, 4, 8);
    if (strlen($salt) !== 8) {
        return false;
    }
    $h = md5($salt . $password, true);
    do {
        $h = md5($h . $password, true);
    } while (--$count);
    $computed = substr($hash, 0, 12) . ny_phpass_encode64($h, 16);
    return hash_equals($computed, $hash);
}

function ny_phpass_encode64(string $input, int $count): string {
    $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $output = '';
    $i = 0;
    do {
        $value = ord($input[$i++]);
        $output .= $itoa64[$value & 0x3f];
        if ($i < $count) {
            $value |= ord($input[$i]) << 8;
        }
        $output .= $itoa64[($value >> 6) & 0x3f];
        if ($i++ >= $count) {
            break;
        }
        if ($i < $count) {
            $value |= ord($input[$i]) << 16;
        }
        $output .= $itoa64[($value >> 12) & 0x3f];
        if ($i++ >= $count) {
            break;
        }
        $output .= $itoa64[($value >> 18) & 0x3f];
    } while ($i < $count);
    return $output;
}
