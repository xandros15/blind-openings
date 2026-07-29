<?php

declare(strict_types=1);

return [
    'dbname' => $_ENV['DB_NAME'] ?? null,
    'user' => $_ENV['DB_USER'] ?? null,
    'password' => $_ENV['DB_PASS'] ?? null,
    'host' => $_ENV['DB_HOST'] ?? null,
    'driver' => 'pdo_mysql',
];
