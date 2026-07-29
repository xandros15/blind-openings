<?php

declare(strict_types=1);

$host = 'https://v.animethemes.moe';
$pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}", $_ENV['DB_USER'], $_ENV['DB_PASS']);

function downloadLargeFile(string $url, string $destination): void
{
    $dir = dirname($destination);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Nie mogę utworzyć katalogu: {$dir}");
    };
    $tmpPath = $destination . '.part';
    $fp = fopen($tmpPath, 'wb');
    if ($fp === false) {
        throw new RuntimeException("Nie mogę otworzyć pliku do zapisu: {$tmpPath}");
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FAILONERROR => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64; rv:152.0) Gecko/20100101 Firefox/152.0',
        CURLOPT_NOPROGRESS => false,
    ]);

    $ok = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    fclose($fp);
    fwrite(STDERR, PHP_EOL);

    if ($ok === false || $curlErrno !== 0) {
        unlink($tmpPath);
        throw new RuntimeException("Błąd pobierania (curl): {$curlError}");
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        unlink($tmpPath);
        throw new RuntimeException("Serwer zwrócił błędny kod HTTP: {$httpCode} | {$url}");
    }

    if (!rename($tmpPath, $destination)) {
        throw new RuntimeException('Nie mogę zmienić nazwy pliku tymczasowego na docelowy.');
    }
}

foreach ($pdo->query('SELECT id, name, paths FROM theme WHERE has_video = 0') as $theme) {
    foreach (explode(',', $theme['paths']) as $path) {
        if (!file_exists('/video/' . $path)) {
            downloadLargeFile($host . '/' . basename($path), '/video/' . $path);
            echo 'Pobrano video ' . $path . ' z ' . $theme['name'];
        }

        $pdo->prepare('UPDATE theme SET has_video = 1 WHERE id = :id')->execute(['id' => $theme['id']]);

        continue 2;
    }
}
