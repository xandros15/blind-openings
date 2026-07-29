<?php

declare(strict_types=1);

$pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}", $_ENV['DB_USER'], $_ENV['DB_PASS']);

foreach ($pdo->query("SELECT external_id, theme_id FROM resource WHERE site = 'anidb'") as $resource) {
    if (file_exists('/image/' . $resource['theme_id'] . '.jpg') || file_exists('/image/' . $resource['theme_id'] . '.webp')) {
        continue;
    }

    $image = @file_get_contents($_ENV['API_IMAGES'] . '/' . $resource['external_id'] . '.jpg');
    if (!$image) {
        $stmt = $pdo->prepare('SELECT name FROM theme WHERE id = ?');
        $stmt->execute([$resource['theme_id']]);
        echo 'Nie moge pobrać plakatu z anime: https://anidb.net/a' . $resource['external_id'] . ' - ' . $stmt->fetchColumn() . "\n";

        continue;
    }

    file_put_contents('/image/' . $resource['theme_id'] . '.jpg', $image);
}
