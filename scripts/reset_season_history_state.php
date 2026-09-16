<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Services\Football\SeasonHistoricalCollectionState;

$options = getopt('', ['template:', 'season:']);
$templateId = trim((string) ($options['template'] ?? ''));
$seasonId = (int) ($options['season'] ?? 0);

if ($templateId === '' || $seasonId < 1) {
    fwrite(STDERR, "Usage: php scripts/reset_season_history_state.php --template=QVmLl54o --season=187\n");
    exit(1);
}

$state = new SeasonHistoricalCollectionState();
$state->reset($templateId, $seasonId);

echo "Season historical state reset.\n";
echo "Tournament template ID: {$templateId}\n";
echo "Season ID: {$seasonId}\n";
echo "Next page: 1\nNext match index: 0\n";
