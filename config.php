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
];
