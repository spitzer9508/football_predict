<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\FlashscoreClient;

$templateId = (string) ($argv[1] ?? '');
$seasonId = isset($argv[2]) ? (int) $argv[2] : 0;
$page = isset($argv[3]) ? (int) $argv[3] : 1;

if ($templateId === '' || $seasonId < 1 || $page < 1) {
    fwrite(STDERR, "Usage: php scripts/test_tournament_results.php <tournament_template_id> <season_id> [page]\n");
    fwrite(STDERR, "Example: php scripts/test_tournament_results.php QVmLl54o 187 2\n");
    exit(1);
}

try {
    $client = new FlashscoreClient();
    $results = $client->tournamentResults($templateId, $seasonId, $page);

    $count = count($results);
    $first = $count > 0 && is_array($results[0] ?? null) ? ($results[0]['match_id'] ?? null) : null;
    $last = $count > 0 && is_array($results[$count - 1] ?? null) ? ($results[$count - 1]['match_id'] ?? null) : null;

    echo "Tournament results test completed.\n";
    echo "Tournament template ID: {$templateId}\n";
    echo "Season ID: {$seasonId}\n";
    echo "Page: {$page}\n";
    echo "Matches returned: {$count}\n";
    echo 'First match ID: ' . ($first ?? '(none)') . "\n";
    echo 'Last match ID: ' . ($last ?? '(none)') . "\n";

    if ($count === 0) {
        echo "Result: empty page\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Tournament results test failed.\n");
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
