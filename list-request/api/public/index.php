<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Hello world!');
    return $response;
});

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
$app->setBasePath('/api');
$app->run();
