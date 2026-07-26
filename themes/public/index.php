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
        SELECT DISTINCT t.id, t.name, t.paths, ta.account_name AS accountName, a.image, a.url, t.year
        FROM theme t
        JOIN resource r on t.id = r.theme_id
        JOIN anime a on a.external_id = r.external_id
        JOIN team_account ta on a.team_account_id = ta.id AND ta.service = r.site
        WHERE ta.id IN (:listIds)
    SQL;
    if ($excludedIds !== []) {
        $query .= ' AND t.id NOT IN (:excludeIds)';
    }

    $themes = getDb()->fetchAllAssociative($query, [
        'listIds' => $listIds,
        'excludeIds' => $excludedIds,
    ], [
        'listIds' => ArrayParameterType::STRING,
        'excludeIds' => ArrayParameterType::INTEGER,
    ]);

    $response->getBody()->write(
        \json_encode(
            array_map(static function (array $theme) {
                return [...$theme, 'paths' => explode(',', $theme['paths'])];
            }, $themes)
        )
    );


    return $response;
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
