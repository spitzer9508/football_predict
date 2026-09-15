<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\FlashscoreClient;

$day = isset($argv[1]) ? (int) $argv[1] : 0;
$timezone = $argv[2] ?? 'Europe/Berlin';

try {
    $data = (new FlashscoreClient())->matchList($day, $timezone);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
