<?php

declare(strict_types=1);

use GuzzleHttp\Client;

require_once __DIR__ . '/../vendor/autoload.php';


function throttledRequest(Client $client, string $url, string $body): \Psr\Http\Message\ResponseInterface
{
    static $lastRequest;
    $now = microtime(true);
    if (isset($lastRequest) && ($now - $lastRequest) < 2) {
        usleep((int) ((2 - ($now - $lastRequest)) * 1000000));
    }

    $response = $client->post($url, [
        'body' => $body,
    ]);
    $lastRequest = microtime(true);

    return $response;
}


function parseResponse(array $payload): array
{
    $tosave = [];
    foreach ($payload['data']['animePagination']['data'] as $data) {
        $item = [
            'id' => $data['id'],
            'title' => $data['title']['romaji'],
            'year' => $data['year'],
            'openings' => [],
            'resources' => [],
        ];
        foreach ($data['animethemes'] as $theme) {
            foreach ($theme['animethemeentries'] as $entry) {
                foreach ($entry['videos']['nodes'] as $node) {
                    $item['openings'][] = $node['path'];
                }
            }
        }
        foreach ($data['resources']['nodes'] as $resource) {
            if (isset($resource['externalId'])) {
                $item['resources'][$resource['site']][] = $resource['externalId'];
            }
        }
        $tosave[] = $item;
    }
    return $tosave;
}

$url = 'https://graphql.animethemes.moe/';

$operationName = 'GetOpenings';
$query = <<<'GRAPHQL'
query GetOpenings($year: Int $type: ThemeType $page: Int) {
  animePagination(year: $year, first: 100, page: $page) {    
    paginatorInfo {
        count
        currentPage
        firstItem
        hasMorePages
        lastItem
        lastPage
        perPage
        total
    }
    data {
      id
      title {
        romaji
      }
      year
      animethemes(type: $type) {
        animethemeentries {
          videos {
            nodes {
              filename
              path
            }
          }
        }
      }
      resources {
        nodes { 
          site
          externalId
        }
      }
    }
  }
}
GRAPHQL;

$client = new \GuzzleHttp\Client(
    [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]
);

for ($i = 1980; $i <= (int) date('Y'); $i++) {
    $page = 1;
    do {
        $vars = [
            'year' => $i,
            'type' => 'OP',
            'page' => $page,
        ];
        $body = json_encode([
            'operationName' => $operationName,
            'query' => $query,
            'variables' => $vars,
        ]);
        $response = throttledRequest($client, $url, $body);
        $content = $response->getBody()->getContents();
        $payload = json_decode($content, true);
        $data = parseResponse($payload);
        file_put_contents(__DIR__ . '/../data/openings' . "-{$vars['year']}-{$vars['page']}.json", json_encode($data));
        echo date('[Y-m-d H:i:s]') . " Done openings-{$vars['year']}-{$vars['page']}\n";
        ++$page;
    } while ($payload['data']['animePagination']['paginatorInfo']['hasMorePages'] ?? false);
}
