<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\HistoricalFixtureCollector;

$options = [
    'from' => -1,
    'to' => -30,
    'timezone' => 'Europe/Berlin',
    'stats-limit' => 50,
];

foreach ($argv as $argument) {
    if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) continue;
    [$key, $value] = explode('=', substr($argument, 2), 2);
    if (array_key_exists($key, $options)) $options[$key] = $value;
}

$from = (int) $options['from'];
$to = (int) $options['to'];
$timezone = (string) $options['timezone'];
$statsLimit = max(0, (int) $options['stats-limit']);

if ($from === 0 || $to === 0) {
    fwrite(STDERR, "Historical ranges should use negative day offsets. Use collect_fixtures.php for today.\n");
    exit(1);
}

try {
    $result = (new HistoricalFixtureCollector())->collect($from, $to, $timezone, $statsLimit);

    echo "Historical collection completed.\n";
    echo "Range: {$from} to {$to}\n";
    echo "Timezone: {$timezone}\n";
    echo "Statistics API budget: {$statsLimit}\n";
    echo "Days processed: {$result['days_processed']}\n";
    echo "Discovered: {$result['discovered']}\n";
    echo "Eligible V1 fixtures: {$result['eligible']}\n";
    echo "Filtered out: {$result['filtered']}\n";
    echo "Imported/updated: {$result['imported']}\n";
    echo "Statistics imported: {$result['stats_imported']}\n";
    echo "Statistics already imported: {$result['stats_already_imported']}\n";
    echo "Statistics skipped/errors: {$result['stats_skipped']}\n";
    echo "Statistics deferred: {$result['stats_deferred']}\n";
    echo "Failed fixtures: {$result['failed']}\n";
    echo "Stopped by budget: " . ($result['stopped_by_budget'] ? 'yes' : 'no') . "\n";

    if ($result['next_day'] !== null) {
        echo "Resume from day: {$result['next_day']}\n";
    }

    if ($result['errors']) {
        echo "\nWarnings/errors:\n";
        foreach ($result['errors'] as $error) echo "- {$error}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
