<?php
declare(strict_types=1);

require __DIR__ . '/src/auth.php';

ny_logout();
header('Location: index.php');
