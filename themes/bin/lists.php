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

if(!isset($_ENV['API_LISTS'])){
    throw new RuntimeException('Brak zmiennej środowiskowej API_LISTS.');
}

$client = new \GuzzleHttp\Client(
    [
        'base_uri' => $_ENV['API_LISTS'],
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]
);

$teams = json_decode(
    $client->get('/api/lists')->getBody()->getContents(),
    true,
);

$db->delete('team_account');
foreach ($teams as $team) {
    $db->beginTransaction();
    foreach ($team['lists'] as $list) {
        $db->insert('team_account', [
            'team_id' => $team['id'],
            'team_name' => $team['team_name'],
            'id' => $list['id'],
            'account_name' => $list['account_name'],
            'service' => $list['service'],
        ]);

        $animeList = json_decode(
            $client->get('/api/lists/' . $list['id'])->getBody()->getContents(),
            true,
        );
        foreach ($animeList as $anime) {
            $db->insert('anime', [
                'team_account_id' => $list['id'],
                'url' => $anime['url'],
                'image' => $anime['image'],
                'name' => $anime['name'],
                'external_id' => $anime['id'],
            ]);
        }
    }
    $db->commit();
}
