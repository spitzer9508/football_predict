<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\FlashscoreClient;
use App\Services\Football\FootballApiException;
use App\Services\Football\MatchImportService;
use App\Services\Football\SeasonHistoricalCollectionState;

$options = getopt('', ['template:', 'season:', 'matches-per-run::', 'max-pages::']);
$templateId = trim((string) ($options['template'] ?? ''));
$seasonId = (int) ($options['season'] ?? 0);
$matchesPerRun = max(1, (int) ($options['matches-per-run'] ?? 20));
$maxPages = max(1, (int) ($options['max-pages'] ?? 50));

if ($templateId === '' || $seasonId < 1) {
    fwrite(STDERR, "Usage: php scripts/collect_season_history_cron.php --template=QVmLl54o --season=187 [--matches-per-run=20] [--max-pages=50]\n");
    exit(1);
}

$lockPath = sys_get_temp_dir() . '/puntly-season-history-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $templateId) . '-' . $seasonId . '.lock';
$lock = fopen($lockPath, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Season historical collector is already running.\n";
    exit(0);
}

$client = new FlashscoreClient();
$importer = new MatchImportService();
$stateStore = new SeasonHistoricalCollectionState();
$state = $stateStore->load($templateId, $seasonId);

if (($state['status'] ?? '') === 'completed') {
    echo "Season historical collection is already complete.\n";
    echo "Tournament template ID: {$templateId}\nSeason ID: {$seasonId}\n";
    exit(0);
}

$page = max(1, (int) ($state['cursor_page'] ?? 1));
$index = max(0, (int) ($state['cursor_match_index'] ?? 0));
$startPage = $page;
$processed = 0;
$imported = 0;
$failed = 0;
$pagesFetched = 0;

try {
    while ($processed < $matchesPerRun && $pagesFetched < $maxPages) {
        $results = $client->tournamentResults($templateId, $seasonId, $page);
        $pagesFetched++;

        if ($results === []) {
            $stateStore->save($templateId, $seasonId, $page, 0, 'completed');
            echo "Season historical collection completed.\n";
            echo "Tournament template ID: {$templateId}\nSeason ID: {$seasonId}\n";
            echo "Started at page: {$startPage}\nEmpty page: {$page}\nMatches processed this run: {$processed}\nImported/updated: {$imported}\nFailed: {$failed}\nStatus: completed\n";
            exit(0);
        }

        $count = count($results);
        if ($index >= $count) {
            $page++;
            $index = 0;
            $stateStore->save($templateId, $seasonId, $page, $index);
            continue;
        }

        for (; $index < $count && $processed < $matchesPerRun; $index++) {
            $row = $results[$index] ?? null;
            $matchId = is_array($row) ? trim((string) ($row['match_id'] ?? '')) : '';

            if ($matchId === '') {
                $failed++;
                $processed++;
                $stateStore->save($templateId, $seasonId, $page, $index + 1);
                continue;
            }

            try {
                $importer->import($matchId, true);
                $imported++;
            } catch (FootballApiException $e) {
                if ($e->isRateLimited()) {
                    $stateStore->save($templateId, $seasonId, $page, $index, 'rate_limited');
                    echo "Season historical collection paused by API rate limit.\n";
                    echo "Page: {$page}\nMatch index: {$index}\n";
                    if ($e->retryAfter() !== null) echo 'Retry after: ' . $e->retryAfter() . " seconds\n";
                    echo $e->getMessage() . "\n";
                    exit(0);
                }
                $failed++;
                fwrite(STDERR, "Match {$matchId} failed: {$e->getMessage()}\n");
            } catch (Throwable $e) {
                $failed++;
                fwrite(STDERR, "Match {$matchId} failed: {$e->getMessage()}\n");
            }

            $processed++;
            $stateStore->save($templateId, $seasonId, $page, $index + 1, 'active');
        }

        if ($index >= $count) {
            $page++;
            $index = 0;
            $stateStore->save($templateId, $seasonId, $page, 0, 'active');
        }
    }

    echo "Season historical cron collection completed.\n";
    echo "Tournament template ID: {$templateId}\nSeason ID: {$seasonId}\n";
    echo "Started at page: {$startPage}\nPages fetched: {$pagesFetched}\nMatches processed: {$processed}\nImported/updated: {$imported}\nFailed: {$failed}\nNext page: {$page}\nNext match index: {$index}\nStatus: active\n";
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
