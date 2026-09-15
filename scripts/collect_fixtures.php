<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\DailyFixtureCollector;

$day = isset($argv[1]) ? (int) $argv[1] : 0;
$timezone = $argv[2] ?? 'Europe/Berlin';
$withStats = in_array('--with-stats', $argv, true);

try {
    $result = (new DailyFixtureCollector())->collect($day, $timezone, $withStats);

    echo "Fixture collection completed.\n";
    echo "Day offset: {$day}\n";
    echo "Timezone: {$timezone}\n";
    echo "Discovered: {$result['discovered']}\n";
    echo "Imported/updated: {$result['imported']}\n";
    echo "Statistics imported: {$result['stats_imported']}\n";
    echo "Statistics skipped: {$result['stats_skipped']}\n";
    echo "Failed fixtures: {$result['failed']}\n";

    if ($result['errors']) {
        echo "\nWarnings/errors:\n";
        foreach ($result['errors'] as $error) echo "- {$error}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
