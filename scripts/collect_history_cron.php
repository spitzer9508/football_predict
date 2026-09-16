<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\FootballApiException;
use App\Services\Football\HistoricalCollectionState;
use App\Services\Football\HistoricalFixtureCollector;

$timezone = 'Europe/Berlin';
$statisticsLimit = 20;
$days = 365;
$daysPerRun = 3;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--timezone=')) $timezone = substr($argument, 11);
    elseif (str_starts_with($argument, '--stats-limit=')) $statisticsLimit = max(0, (int) substr($argument, 14));
    elseif (str_starts_with($argument, '--days=')) $days = max(1, (int) substr($argument, 7));
    elseif (str_starts_with($argument, '--days-per-run=')) $daysPerRun = max(1, (int) substr($argument, 15));
    elseif (str_starts_with($argument, '--target=')) $days = max(1, abs((int) substr($argument, 9)));
}

$lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'football_predict_history.lock';
$lockHandle = fopen($lockPath, 'c+');
if ($lockHandle === false) { fwrite(STDERR, "Unable to create historical collector lock.\n"); exit(1); }
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) { echo "Historical collector is already running; this run was skipped.\n"; fclose($lockHandle); exit(0); }

try {
    $store = new HistoricalCollectionState();
    $state = $store->load($days, $timezone);
    $timezone = (string) $state['timezone'];
    if (($state['status'] ?? '') === 'completed') {
        echo "Historical collection is already complete.\nEnd date: {$state['end_date']}\n";
        exit(0);
    }

    $cursorDate = (string) $state['cursor_date'];
    $endDate = (string) $state['end_date'];
    $fromDay = $store->dayOffset($cursorDate, $timezone);
    $endOffset = $store->dayOffset($endDate, $timezone);
    $toDay = max($endOffset, $fromDay - ($daysPerRun - 1));

    try {
        $result = (new HistoricalFixtureCollector())->collect($fromDay, $toDay, $timezone, $statisticsLimit);
    } catch (FootballApiException $e) {
        if ($e->isRateLimited()) {
            $store->saveProgress($cursorDate, $endDate, $timezone, 'rate_limited');
            echo "Historical collection paused by API rate limit.\nProgress preserved at date: {$cursorDate}\n";
            if ($e->retryAfter() !== null) echo "Provider retry-after: {$e->retryAfter()} seconds\n";
            echo "Status: rate_limited\n";
            exit(0);
        }
        throw $e;
    }

    if ($result['stopped_by_budget']) {
        $nextOffset = (int) $result['next_day'];
        $nextDate = (new DateTimeImmutable('today', new DateTimeZone($timezone)))->modify($nextOffset . ' days')->format('Y-m-d');
        $store->saveProgress($nextDate, $endDate, $timezone, 'active');
    } else {
        $processedTo = (new DateTimeImmutable('today', new DateTimeZone($timezone)))->modify($toDay . ' days');
        $nextDate = $processedTo->modify('-1 day')->format('Y-m-d');
        if ($nextDate < $endDate) $store->saveProgress($endDate, $endDate, $timezone, 'completed');
        else $store->saveProgress($nextDate, $endDate, $timezone, 'active');
    }

    echo "Historical cron collection completed.\n";
    echo "Started at date: {$cursorDate}\nEnd date: {$endDate}\n";
    echo "Days processed: {$result['days_processed']}\nEligible V1 fixtures: {$result['eligible']}\n";
    echo "Statistics imported: {$result['stats_imported']}\nStatistics already imported: {$result['stats_already_imported']}\n";
    echo "Statistics skipped/errors: {$result['stats_skipped']}\nStatistics deferred: {$result['stats_deferred']}\nFailed fixtures: {$result['failed']}\n";
    $newState = $store->load($days, $timezone);
    echo "Next cursor date: {$newState['cursor_date']}\nStatus: {$newState['status']}\n";
    if ($result['errors']) { echo "\nWarnings/errors:\n"; foreach ($result['errors'] as $error) echo "- {$error}\n"; }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1);
} finally {
    flock($lockHandle, LOCK_UN); fclose($lockHandle);
}
