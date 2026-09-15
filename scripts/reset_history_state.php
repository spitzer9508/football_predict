<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\HistoricalCollectionState;

$targetDay = -365;
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--target=')) {
        $targetDay = min(-1, (int) substr($argument, strlen('--target=')));
    }
}

try {
    (new HistoricalCollectionState())->reset($targetDay);
    echo "Historical collection state reset.\n";
    echo "Start day: -1\n";
    echo "Target day: {$targetDay}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
