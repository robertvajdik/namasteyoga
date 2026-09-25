<?php
// Copy to config.local.php and fill in real credentials, or just edit here.

return [
    'db' => [
        'host'    => 'md428.wedos.net',
        'port'    => 3306,
        'name'    => 'd210718_ny',
        'user'    => 'a210718_ny',
        'pass'    => 'KGLXeQWv',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name'         => 'Namasté Yoga – Rezervace',
        'timezone'     => 'Europe/Prague',
        // Legacy WordPress table (source for migration).
        'wp_users_tbl' => 'w8dEp_users',
    ],
    // Outgoing mail. When `smtp_host` is filled in, the site sends via
    // authenticated SMTP; otherwise it falls back to PHP mail().
    'mail' => [
        'smtp_host'    => 'wes1-smtp.wedos.net',
        'smtp_port'    => 587,
        'smtp_user'    => 'no-reply@namasteyoga.cz',
        'smtp_pass'    => 'SilaJeVrovnovaze@2025',
        'smtp_secure'  => 'tls',  // '', 'tls' (STARTTLS on 587), or 'ssl' (implicit on 465)
        'from'         => 'no-reply@namasteyoga.cz',
        'from_name'    => '',     // empty = use site_name setting
        'admin_notify' => 'studio@namasteyoga.cz', // where new-registration alerts, etc. are sent
    ],
];
