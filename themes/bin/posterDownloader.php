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
foreach ($db->fetchAllAssociative("SELECT external_id, theme_id FROM resource WHERE site = 'anidb'") as $resource) {
    if (file_exists('/image/' . $resource['theme_id'] . '.jpg')
        || file_exists('/image/' . $resource['theme_id'] . '.webp')
    ) {
        continue;
    }
    $image = @file_get_contents($_ENV['API_IMAGES'] . '/' . $resource['external_id'] . '.jpg');
    if ($image) {
        file_put_contents('/image/' . $resource['theme_id'] . '.jpg', $image);
    } else {
        echo 'Nie moge pobrać plakatu z anime: https://anidb.net/a' . $resource['external_id'] . ' - ' .
            $db->fetchOne('SELECT name FROM theme WHERE id = ?', [$resource['theme_id']]) . "\n";
    }
}
