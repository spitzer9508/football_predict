<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\MatchImportService;

$matchId = $argv[1] ?? null;
if (!$matchId) {
    echo "Usage: php scripts/import_fixture.php MATCH_ID [--no-stats]\n";
    exit(1);
}

$includeStatistics = !in_array('--no-stats', $argv, true);

try {
    $result = (new MatchImportService())->import($matchId, $includeStatistics);

    echo "Match imported successfully.\n";
    echo "Provider match ID: {$result['provider_match_id']}\n";
    echo "Local fixture ID: {$result['fixture_id']}\n";
    echo "Match: {$result['home_team']} vs {$result['away_team']}\n";
    echo "Status: {$result['status']}\n";
    if ($result['statistics_rows'] !== null) {
        echo "Statistics rows: {$result['statistics_rows']}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
