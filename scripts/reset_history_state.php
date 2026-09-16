<?php

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\HistoricalCollectionState;

$days = 365;
$timezone = 'Europe/Berlin';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--days=')) $days = max(1, (int) substr($argument, 7));
    elseif (str_starts_with($argument, '--timezone=')) $timezone = substr($argument, 11);
    elseif (str_starts_with($argument, '--target=')) $days = max(1, abs((int) substr($argument, 9)));
}

try {
    $store = new HistoricalCollectionState();
    $store->reset($days, $timezone);
    $state = $store->load($days, $timezone);
    echo "Historical collection state reset.\n";
    echo "Cursor date: {$state['cursor_date']}\n";
    echo "End date: {$state['end_date']}\n";
    echo "Timezone: {$state['timezone']}\n";
    echo "Status: {$state['status']}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
