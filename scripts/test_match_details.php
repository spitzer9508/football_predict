<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\FlashscoreClient;

$matchId = $argv[1] ?? null;
if (!$matchId) {
    echo "Usage: php scripts/test_match_details.php MATCH_ID\n";
    exit(1);
}

try {
    $data = (new FlashscoreClient())->matchDetails($matchId);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
