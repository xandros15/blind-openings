<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\DriverManager;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}}';
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Hello world!');
    return $response;
});

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

$app->get('/teams', function (Request $request, Response $response) {
    $response = $response->withHeader('Content-Type', 'application/json');
    $db = getDb();

    $query = <<<SQL
        SELECT DISTINCT 
            ta.id,
            ta.team_id,
            ta.team_name, 
            ta.account_name, 
            ta.service
        FROM team_account ta
        JOIN anime a on ta.id = a.team_account_id
    SQL;
    $lists = $db->fetchAllAssociative($query);
    $grouped = [];
    foreach ($lists as $list) {
        if (!isset($grouped[$list['team_id']])) {
            $grouped[$list['team_id']] = [
                'id' => $list['team_id'],
                'team_name' => $list['team_name'],
                'lists' => [],
            ];
        }
        $grouped[$list['team_id']]['lists'][] = [
            'id' => $list['id'],
            'account_name' => $list['account_name'],
            'service' => $list['service'],
        ];
    }
    $grouped = array_values($grouped);
    $response->getBody()->write(\json_encode($grouped));

    return $response;
});

$app->delete("/teams/{teamId:{$uuid}}/lists/{listId:{$uuid}}", function (Request $request, Response $response, array $args) {
    $db = getDb();
    if (!$db->fetchOne('SELECT COUNT(*) FROM team_account WHERE team_id = :teamId AND id = :listId', [
        'teamId' => $args['teamId'],
        'listId' => $args['listId'],
    ])) {
        return $response->withStatus(404);
    }

    $db->delete('team_account', [
        'team_id' => $args['teamId'],
        'id' => $args['listId'],
    ]);
    return $response->withStatus(204);
});

$app->post("/find-themes", function (Request $request, Response $response) use ($uuid) {
    $contents = $request->getBody()->getContents();
    if (!json_validate($contents)) {
        $response->getBody()->write(\json_encode([
            'error' => 'To nie jest json',
        ]));
        return $response->withStatus(400);
    }
    $queryParams = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    $listIds = array_filter($queryParams['listIds'], static fn($id) => is_string($id));
    $excludedIds = array_filter($queryParams['excludedIds'], static fn($id) => is_int($id));

    if ($listIds === []) {
        $response->getBody()->write(\json_encode([
            'error' => 'Brakuje parametru `listIds`',
        ]));
        return $response->withStatus(400);
    }

    $query = <<<SQL
        SELECT 
            t.id, 
            t.name, 
            t.paths, 
            GROUP_CONCAT(ta.account_name, ',') AS accountNames, 
            t.year
        FROM theme t
        JOIN resource r on t.id = r.theme_id
        JOIN anime a on a.external_id = r.external_id
        JOIN team_account ta on a.team_account_id = ta.id AND ta.service = r.site
        WHERE ta.id IN (:listIds)
        GROUP BY t.id
    SQL;
    if ($excludedIds !== []) {
        $query .= ' AND t.id NOT IN (:excludeIds)';
    }

    $db = getDb();
    $themes = $db->fetchAllAssociative($query, [
        'listIds' => $listIds,
        'excludeIds' => $excludedIds,
    ], [
        'listIds' => ArrayParameterType::STRING,
        'excludeIds' => ArrayParameterType::INTEGER,
    ]);

    $indexes = array_column($themes, 'id');
    $queryIndexed = <<<SQL
        SELECT 
            theme_id, 
            external_id, 
            site 
        FROM resource 
        WHERE theme_id IN (:ids) 
          AND site in ('mal', 'anidb', 'ann', 'anilist')
    SQL;
    $grouped = [];
    $forGroup = $db->fetchAllAssociative($queryIndexed, [
        'ids' => $indexes,
    ], ['ids' => ArrayParameterType::INTEGER]);
    foreach($forGroup as $item){
        $grouped[$item['theme_id']][] = $item;
    }

    $items = array_map(static fn(array $theme) => [
        ...$theme,
        'paths' => explode(',', $theme['paths']),
        'accountNames' => explode(',', $theme['accountNames']),
        'resources' => array_map(static fn(array $resource) => [
            'site' => strtolower($resource['site']),
            'link' => match (strtolower($resource['site'])) {
                'anidb' => 'https://anidb.net/a' . $resource['external_id'],
                'mal' => 'https://myanimelist.net/anime/' . $resource['external_id'],
                'anilist' => 'https://anilist.co/anime/' . $resource['external_id'],
                'ann' => 'https://www.animenewsnetwork.com/encyclopedia/anime.php?id=' . $resource['external_id'],
            },
        ], $grouped[$theme['id']] ?? []),
    ], $themes);
    $response->getBody()->write(\json_encode($items));

    return $response;
});


$app->post('/lists', function (Request $request, Response $response) use ($uuid) {
    if (!isset($_ENV['API_LISTS'])) {
        $response->getBody()->write(\json_encode([
            'error' => 'Brak zmiennej środowiskowej API_LISTS.',
        ]));
        return $response->withStatus(400);
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
    $db = getDb();
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


    return $response->withStatus(204);
});

$app->addMiddleware(
    new class implements \Psr\Http\Server\MiddlewareInterface {

        public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
        {
            try {
                return $handler->handle($request);
            } catch (HttpNotFoundException) {
                return new Response(404);
            }
        }
    }
);

$app->addErrorMiddleware(true, true, true);
$app->setBasePath('/api');
$app->run();
