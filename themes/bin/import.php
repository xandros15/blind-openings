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

$db->delete('theme', ['1' => '1']);
$db->beginTransaction();
foreach (glob(__DIR__ . '/../data/*.json') as $filename) {
    $data = json_decode(
        file_get_contents($filename),
        true,
    );

    foreach ($data as $theme) {
        if ($theme['openings'] === []) {
            continue;
        }
        $db->insert('theme', [
            'id' => $theme['id'],
            'name' => $theme['title'],
            'year' => $theme['year'],
            'paths' => implode(',', $theme['openings']),
            'has_video' => 0,
        ]);
        foreach ($theme['resources'] as $service => $externalIds) {
            foreach ($externalIds as $externalId) {
                $db->insert('resource', [
                    'site' => mb_strtolower($service),
                    'external_id' => $externalId,
                    'theme_id' => $theme['id'],
                ]);
            }
        }
    }
}
$db->commit();
