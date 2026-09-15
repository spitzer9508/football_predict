<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\FootballApiException;
use App\Services\Football\HistoricalCollectionState;
use App\Services\Football\HistoricalFixtureCollector;

$timezone = 'Europe/Berlin';
$statisticsLimit = 20;
$defaultTargetDay = -365;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--timezone=')) {
        $timezone = substr($argument, strlen('--timezone='));
    } elseif (str_starts_with($argument, '--stats-limit=')) {
        $statisticsLimit = max(0, (int) substr($argument, strlen('--stats-limit=')));
    } elseif (str_starts_with($argument, '--target=')) {
        $defaultTargetDay = min(-1, (int) substr($argument, strlen('--target=')));
    }
}

$lockPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'football_predict_history.lock';
$lockHandle = fopen($lockPath, 'c+');
if ($lockHandle === false) {
    fwrite(STDERR, "Unable to create historical collector lock.\n");
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Historical collector is already running; this run was skipped.\n";
    fclose($lockHandle);
    exit(0);
}

try {
    $stateStore = new HistoricalCollectionState();
    $state = $stateStore->load($defaultTargetDay);

    if (($state['status'] ?? '') === 'completed') {
        echo "Historical collection is already complete.\n";
        echo "Target day: {$state['target_day']}\n";
        exit(0);
    }

    $fromDay = (int) $state['current_day'];
    $targetDay = (int) $state['target_day'];

    try {
        $result = (new HistoricalFixtureCollector())->collect(
            $fromDay,
            $targetDay,
            $timezone,
            $statisticsLimit
        );
    } catch (FootballApiException $e) {
        if ($e->isRateLimited()) {
            $stateStore->saveProgress($fromDay, $targetDay, 'rate_limited');
            echo "Historical collection paused by API rate limit.\n";
            echo "Progress preserved at day: {$fromDay}\n";
            if ($e->retryAfter() !== null) {
                echo "Provider retry-after: {$e->retryAfter()} seconds\n";
            }
            echo "Status: rate_limited\n";
            exit(0);
        }
        throw $e;
    }

    if ($result['stopped_by_budget']) {
        $nextDay = (int) $result['next_day'];
        $stateStore->saveProgress($nextDay, $targetDay, 'active');
    } else {
        $stateStore->saveProgress($targetDay, $targetDay, 'completed');
    }

    echo "Historical cron collection completed.\n";
    echo "Started from day: {$fromDay}\n";
    echo "Target day: {$targetDay}\n";
    echo "Days processed: {$result['days_processed']}\n";
    echo "Eligible V1 fixtures: {$result['eligible']}\n";
    echo "Statistics imported: {$result['stats_imported']}\n";
    echo "Statistics already imported: {$result['stats_already_imported']}\n";
    echo "Statistics skipped/errors: {$result['stats_skipped']}\n";
    echo "Statistics deferred: {$result['stats_deferred']}\n";
    echo "Failed fixtures: {$result['failed']}\n";

    if ($result['stopped_by_budget']) {
        echo "Progress saved at day: {$result['next_day']}\n";
        echo "Status: active\n";
    } else {
        echo "Status: completed\n";
    }

    if ($result['errors']) {
        echo "\nWarnings/errors:\n";
        foreach ($result['errors'] as $error) echo "- {$error}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
