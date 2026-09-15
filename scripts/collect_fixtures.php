<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\DailyFixtureCollector;

$day = isset($argv[1]) ? (int) $argv[1] : 0;
$timezone = $argv[2] ?? 'Europe/Berlin';
$withStats = in_array('--with-stats', $argv, true);
$allCompetitions = in_array('--all-competitions', $argv, true);
$statsLimit = null;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--stats-limit=')) {
        $statsLimit = max(0, (int) substr($argument, strlen('--stats-limit=')));
    }
}

try {
    $result = (new DailyFixtureCollector())->collect(
        $day,
        $timezone,
        $withStats,
        !$allCompetitions,
        $statsLimit
    );

    echo "Fixture collection completed.\n";
    echo "Day offset: {$day}\n";
    echo "Timezone: {$timezone}\n";
    echo "Competition scope: " . ($allCompetitions ? 'all' : 'configured V1 competitions') . "\n";
    echo "Discovered: {$result['discovered']}\n";
    echo "Eligible: {$result['eligible']}\n";
    echo "Filtered out: {$result['filtered']}\n";
    echo "Imported/updated: {$result['imported']}\n";
    echo "Statistics imported: {$result['stats_imported']}\n";
    echo "Statistics already imported: {$result['stats_already_imported']}\n";
    echo "Statistics skipped/errors: {$result['stats_skipped']}\n";
    echo "Statistics deferred by budget: {$result['stats_budget_exhausted']}\n";
    echo "Failed fixtures: {$result['failed']}\n";

    if ($result['errors']) {
        echo "\nWarnings/errors:\n";
        foreach ($result['errors'] as $error) echo "- {$error}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
