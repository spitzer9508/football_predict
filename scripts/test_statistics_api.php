<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\FlashscoreClient;
use App\Services\Football\FlashscoreNormalizer;

$matchId = $argv[1] ?? null;
if (!$matchId) {
    echo "Usage: php scripts/test_statistics_api.php MATCH_ID\n";
    exit(1);
}

try {
    $payload = (new FlashscoreClient())->matchStatistics($matchId);
    $normalized = (new FlashscoreNormalizer())->normalizeStatistics($payload);

    echo "Match statistics fetched successfully.\n";
    echo json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
