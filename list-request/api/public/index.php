<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use Ramsey\Uuid\Uuid;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

const CREATE_SCHEMA = <<<'JSON'
       {
            "$schema": "https://json-schema.org/draft/2020-12/schema",
            "$id": "https://twoja-domena.pl/schemas/import-payload.json",
            "title": "Import payload",
            "type": "object",
            "properties": {
                "name": {"type": "string", "minLength": 3, "maxLength": 64, "$error": {"type": "Pole 'name' musi być tekstem", "minLength": "Pole 'name' musi mieć co najmniej 3 znaki", "maxLength": "Pole 'name' może mieć maksymalnie 64 znaki"}},
                "lists": {
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {
                            "service": {"type": "string", "enum": ["mal", "anidb", "kitsu", "anilist"], "$error": {"type": "Pole 'service' musi być tekstem", "enum": "Nieprawidłowy serwis. Dozwolone wartości: mal, anidb, kitsu, anilist"}},
                            "name": {"type": "string", "minLength": 1, "maxLength": 64, "$error": {"type": "Nazwa listy musi być tekstem", "minLength": "Nazwa listy musi mieć co najmniej 1 znak", "maxLength": "Nazwa listy może mieć maksymalnie 64 znaki"}},
                            "items": {
                                "type": "array",
                                "items": {
                                    "type": "object",
                                    "properties": {
                                        "id": {"type": "integer", "minimum": 1, "$error": {"type": "ID musi być liczbą całkowitą", "minimum": "ID musi być większe od zera"}},
                                        "name": {"type": "string", "minLength": 1, "maxLength": 1024, "$error": {"type": "Nazwa musi być tekstem", "minLength": "Nazwa musi mieć co najmniej 1 znak", "maxLength": "Nazwa może mieć maksymalnie 1024 znaki"}},
                                        "url": {"type": "string", "format": "uri", "minLength": 1, "maxLength": 128, "$error": {"type": "Pole 'url' musi być tekstem", "format": "Pole 'url' musi być prawidłowym adresem URL", "minLength": "Pole 'url' musi mieć co najmniej 1 znak", "maxLength": "Pole 'url' może mieć maksymalnie 128 znaków"}},
                                        "image": {"type": "string", "format": "uri", "minLength": 1, "maxLength": 128, "$error": {"type": "Pole 'image' musi być tekstem", "format": "Pole 'image' musi być prawidłowym adresem URL", "minLength": "Pole 'image' musi mieć co najmniej 1 znak", "maxLength": "Pole 'image' może mieć maksymalnie 128 znaków"}}
                                    },
                                    "required": ["id", "name", "url"],
                                    "$error": {
                                        "required": {
                                            "id": "Pole 'id' jest wymagane",
                                            "name": "Pole 'name' jest wymagane",
                                            "url": "Pole 'url' jest wymagane"
                                        }
                                    }
                                }
                            }
                        },
                        "required": ["service", "name", "items"],
                        "$error": {
                            "required": {
                                "service": "Pole 'service' jest wymagane",
                                "name": "Pole 'name' jest wymagane",
                                "items": "Pole 'items' jest wymagane"
                            }
                        }
                    }
                }
            },
            "required": ["name", "lists"],
            "$error": {
                "required": {
                    "name": "Pole 'name' jest wymagane",
                    "lists": "Pole 'lists' jest wymagane"
                }
            }
        }
    JSON;

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

$app->get('/mal/{username:\w+}', function (Request $request, Response $response, $args) {
    $response = $response->withHeader('Content-Type', 'application/json');
    $allowedStatuses = [
        'watching',
        'completed',
        'on_hold',
    ];
    $url = "https://api.myanimelist.net/v2/users/{$args['username']}/animelist?fields=list_status&limit=1000";
    $animelist = [];

    while (true) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['X-MAL-CLIENT-ID: ' . $_ENV['X_MAL_CLIENT_ID']]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $body = curl_exec($curl);

        $httpCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($httpCode !== 200) {
            return $response->withStatus($httpCode);
        }

        if ($body === false) {
            $response->getBody()->write(\json_encode(['error' => 'invalid response body']));;
            return $response->withStatus(503);
        }

        try {
            $data = json_decode($body, true, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $response->getBody()->write(\json_encode(['error' => 'invalid json response']));;
            return $response->withStatus(503);
        }

        if (($data['error'] ?? '') === 'forbidden') {
            return $response->withStatus(403);
        }

        foreach ($data['data'] as $item) {
            if (!in_array($item['list_status']['status'], $allowedStatuses)) {
                continue;
            }

            $animelist[] = [
                'id' => $item['node']['id'],
                'name' => $item['node']['title'],
                'url' => 'https://myanimelist.net/anime/' . $item['node']['id'],
                'image' => $item['node']['main_picture']['medium'],
                'status' => $item['list_status']['status'],
            ];
        }
        if (!isset($data['paging']['next'])) {
            break;
        }
        if (!filter_var($data['paging']['next'], FILTER_VALIDATE_URL)) {
            $response->getBody()->write(\json_encode(['error' => 'invalid next page url']));
            return $response->withStatus(503);
        }
        $url = $data['paging']['next'];
    }

    $response->getBody()->write(\json_encode($animelist));

    return $response->withStatus(200);
});
$app->post('/lists', function (Request $request, Response $response) {
    $response = $response->withHeader('Content-Type', 'application/json');
    $contents = $request->getBody()->getContents();
    if (!json_validate($contents)) {
        $response->getBody()->write(\json_encode([
            'error' => 'To nie jest json',
        ]));
        return $response->withStatus(400);
    }
    $team = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    $validator = new Validator();
    $result = $validator->validate(Helper::toJSON($team), CREATE_SCHEMA);

    if (!$result->isValid()) {
        $response->getBody()->write(\json_encode([
            'error' => 'Błąd walidacji',
            'errors' => (new ErrorFormatter())->format($result->error()),
        ]));
        return $response->withStatus(400);
    }
    $team = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    $db = getDb();

    if ($db->fetchOne('SELECT COUNT(*) FROM team_account WHERE team_name = :name', ['name' => $team['name']]) > 0) {
        $response->getBody()->write(\json_encode([
            'error' => 'Team już istnieje',
        ]));
        return $response->withStatus(400);
    }

    $db->beginTransaction();
    $teamId = Uuid::uuid7();
    foreach ($team['lists'] as $list) {
        $accountId = Uuid::uuid7();
        $db->insert('team_account', [
            'id' => $accountId,
            'team_id' => $teamId,
            'team_name' => $team['name'],
            'account_name' => $list['name'],
            'service' => $list['service'],

        ]);
        foreach ($list['items'] as $item) {
            $db->insert('anime', [
                'team_account_id' => $accountId,
                'url' => $item['url'],
                'image' => $item['image'] ?? null,
                'name' => $item['name'],
                'external_id' => (int) $item['id'],
            ]);
        }
    }
    $db->commit();
    $response->getBody()->write(\json_encode(['id' => $team['name']]));

    return $response->withStatus(201);
});
$app->addMiddleware(
    new class implements \Psr\Http\Server\MiddlewareInterface {

        public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
        {
            try {
                return $handler->handle($request);
            } catch (\Slim\Exception\HttpNotFoundException) {
                return new Response(404);
            }
        }
    }
);
$app->addErrorMiddleware(true, true, true);
$app->setBasePath('/api');
$app->run();
