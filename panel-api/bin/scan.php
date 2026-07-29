<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;

require_once __DIR__ . '/../vendor/autoload.php';


function getDb(): \Doctrine\DBAL\Connection
{
    return DriverManager::getConnection([
        'dbname' => $_ENV['DB_NAME'] ?? null,
        'user' => $_ENV['DB_USER'] ?? null,
        'password' => $_ENV['DB_PASS'] ?? null,
        'host' => $_ENV['DB_HOST'] ?? null,
        'driver' => 'pdo_mysql',
    ]);
}

$db = getDb();

foreach ($db->fetchAllAssociative('SELECT id, name, paths FROM theme') as $theme) {
    foreach (explode(',', $theme['paths']) as $path) {
        if (file_exists('/video/' . $path)) {
            $db->update('theme', [
                'has_video' => 1,
            ], ['id' => $theme['id']]);
            continue 2;
        }
    }
    echo "{$theme['name']}\n";
}
