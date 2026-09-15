<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\FlashscoreClient;

$matchId = $argv[1] ?? null;
if (!$matchId) {
    echo "Usage: php scripts/test_api.php MATCH_ID\n";
    exit(1);
}

try {
    $data = (new FlashscoreClient())->momentum($matchId);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
