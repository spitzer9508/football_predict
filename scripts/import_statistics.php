<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\MatchStatisticsService;

$matchId = $argv[1] ?? null;
if (!$matchId) {
    echo "Usage: php scripts/import_statistics.php MATCH_ID\n";
    exit(1);
}

try {
    $result = (new MatchStatisticsService())->importByProviderId($matchId);

    echo "Statistics imported successfully.\n";
    echo "Provider match ID: {$result['provider_match_id']}\n";
    echo "Local fixture ID: {$result['fixture_id']}\n";
    echo "Statistics rows: {$result['statistics_rows']}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
