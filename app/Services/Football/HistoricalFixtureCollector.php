<?php

namespace App\Services\Football;

final class HistoricalFixtureCollector
{
    public function __construct(
        private readonly DailyFixtureCollector $daily = new DailyFixtureCollector()
    ) {
    }

    public function collect(
        int $fromDay = -1,
        int $toDay = -30,
        string $timezone = 'Europe/Berlin',
        int $statisticsLimit = 50
    ): array {
        $statisticsLimit = max(0, $statisticsLimit);
        $step = $fromDay >= $toDay ? -1 : 1;
        $result = [
            'from_day' => $fromDay,
            'to_day' => $toDay,
            'days_processed' => 0,
            'discovered' => 0,
            'eligible' => 0,
            'filtered' => 0,
            'imported' => 0,
            'stats_imported' => 0,
            'stats_already_imported' => 0,
            'stats_skipped' => 0,
            'stats_deferred' => 0,
            'failed' => 0,
            'stopped_by_budget' => false,
            'next_day' => null,
            'errors' => [],
        ];

        for ($day = $fromDay; ; $day += $step) {
            $remainingBudget = max(0, $statisticsLimit - $result['stats_imported'] - $result['stats_skipped']);
            $daily = $this->daily->collect($day, $timezone, true, true, $remainingBudget);

            $result['days_processed']++;
            $result['discovered'] += $daily['discovered'];
            $result['eligible'] += $daily['eligible'];
            $result['filtered'] += $daily['filtered'];
            $result['imported'] += $daily['imported'];
            $result['stats_imported'] += $daily['stats_imported'];
            $result['stats_already_imported'] += $daily['stats_already_imported'];
            $result['stats_skipped'] += $daily['stats_skipped'];
            $result['stats_deferred'] += $daily['stats_budget_exhausted'];
            $result['failed'] += $daily['failed'];
            array_push($result['errors'], ...$daily['errors']);

            $budgetUsed = $result['stats_imported'] + $result['stats_skipped'];
            if ($daily['stats_budget_exhausted'] > 0 || ($statisticsLimit > 0 && $budgetUsed >= $statisticsLimit)) {
                $result['stopped_by_budget'] = true;
                $result['next_day'] = $day;
                break;
            }

            if ($day === $toDay) {
                break;
            }
        }

        return $result;
    }
}
