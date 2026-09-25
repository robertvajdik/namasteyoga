<?php
declare(strict_types=1);

/**
 * Minimal SMTP client — enough to speak EHLO / STARTTLS / AUTH LOGIN / DATA
 * against a hosted mail relay (WeDOS, ForpsiCloud, etc.). No external
 * dependencies. Returns true on 2xx after DATA, false on any protocol error.
 *
 * $cfg keys:
 *   host   – SMTP server hostname
 *   port   – TCP port (587 = STARTTLS, 465 = implicit TLS, 25 = plain)
 *   user   – SMTP AUTH username (usually the full mailbox address)
 *   pass   – SMTP AUTH password
 *   secure – '', 'tls' (STARTTLS), or 'ssl' (implicit TLS)
 *   from   – envelope MAIL FROM address (bare, no display name)
 */
function ny_smtp_send(string $to, string $subject, string $body, array $headers, array $cfg): bool {
    $host    = (string)($cfg['host']   ?? '');
    $port    = (int)   ($cfg['port']   ?? 587);
    $user    = (string)($cfg['user']   ?? '');
    $pass    = (string)($cfg['pass']   ?? '');
    $secure  = strtolower((string)($cfg['secure'] ?? 'tls'));
    $from    = (string)($cfg['from']   ?? '');
    $timeout = 20;
    if ($host === '' || $from === '' || $to === '') return false;

    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno  = 0; $errstr = '';
    $fp = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) return false;
    stream_set_timeout($fp, $timeout);

    $read = static function ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // Continuation lines have '-' at pos 3; final line has ' '.
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $write = static function ($fp, string $cmd): void { fwrite($fp, $cmd . "\r\n"); };
    $ok    = static function (string $data, int $code): bool { return substr($data, 0, 3) === (string)$code; };

    try {
        if (!$ok($read($fp), 220)) return false;

        $hostname = gethostname() ?: 'localhost';
        $write($fp, 'EHLO ' . $hostname);
        if (!$ok($read($fp), 250)) return false;

        if ($secure === 'tls') {
            $write($fp, 'STARTTLS');
            if (!$ok($read($fp), 220)) return false;
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT
                    | (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT : 0)
                    | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) return false;
            $write($fp, 'EHLO ' . $hostname);
            if (!$ok($read($fp), 250)) return false;
        }

        if ($user !== '' && $pass !== '') {
            $write($fp, 'AUTH LOGIN');
            if (!$ok($read($fp), 334)) return false;
            $write($fp, base64_encode($user));
            if (!$ok($read($fp), 334)) return false;
            $write($fp, base64_encode($pass));
            if (!$ok($read($fp), 235)) return false;
        }

        $write($fp, 'MAIL FROM:<' . $from . '>');
        if (!$ok($read($fp), 250)) return false;

        $write($fp, 'RCPT TO:<' . $to . '>');
        $resp = $read($fp);
        if (!$ok($resp, 250) && !$ok($resp, 251)) return false;

        $write($fp, 'DATA');
        if (!$ok($read($fp), 354)) return false;

        $fromHost = preg_replace('/^.*@/', '', $from);
        $lines    = array_merge(
            [
                'To: ' . $to,
                'Subject: ' . $subject,
                'Date: ' . date('r'),
                'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $fromHost . '>',
            ],
            $headers
        );
        $msg = implode("\r\n", $lines) . "\r\n\r\n" . $body;
        // Normalise line endings and dot-stuff (RFC 5321 §4.5.2).
        $msg = preg_replace('/\r?\n/', "\r\n", $msg);
        $msg = preg_replace('/^\./m', '..', $msg);

        fwrite($fp, $msg . "\r\n.\r\n");
        if (!$ok($read($fp), 250)) return false;

        $write($fp, 'QUIT');
        return true;
    } finally {
        @fclose($fp);
    }
}
