<?php

namespace App\Services\Football;

final class FlashscoreNormalizer
{
    private const STAT_MAP = [
        'Expected goals (xG)' => 'xg',
        'xG on target (xGOT)' => 'xgot',
        'Expected assists (xA)' => 'expected_assists',
        'Ball possession' => 'possession',
        'Total shots' => 'shots',
        'Shots on target' => 'shots_on_target',
        'Shots off target' => 'shots_off_target',
        'Blocked shots' => 'blocked_shots',
        'Shots inside the box' => 'shots_inside_box',
        'Shots outside the box' => 'shots_outside_box',
        'Hit the woodwork' => 'woodwork',
        'Big chances' => 'big_chances',
        'Corner kicks' => 'corners',
        'Touches in opposition box' => 'touches_opposition_box',
        'Offsides' => 'offsides',
        'Free kicks' => 'free_kicks',
        'Fouls' => 'fouls',
        'Yellow cards' => 'yellow_cards',
        'Red cards' => 'red_cards',
        'Duels won' => 'duels_won',
        'Clearances' => 'clearances',
        'Interceptions' => 'interceptions',
        'Errors leading to shot' => 'errors_leading_to_shot',
        'Errors leading to goal' => 'errors_leading_to_goal',
        'Goalkeeper saves' => 'goalkeeper_saves',
        'xGOT faced' => 'xgot_faced',
        'Goals prevented' => 'goals_prevented',
    ];

    private const RATIO_MAP = [
        'Passes' => ['pass_accuracy', 'passes_completed', 'passes_attempted'],
        'Long passes' => ['long_pass_accuracy', 'long_passes_completed', 'long_passes_attempted'],
        'Final third passes' => ['final_third_pass_accuracy', 'final_third_passes_completed', 'final_third_passes_attempted'],
        'Crosses' => ['cross_accuracy', 'crosses_completed', 'crosses_attempted'],
        'Tackles' => [null, 'tackles_won', 'tackles_attempted'],
    ];

    public function normalizeStatistics(array $payload): array
    {
        $periods = ['match' => 'MATCH', '1st-half' => 'FIRST_HALF', '2nd-half' => 'SECOND_HALF'];
        $result = [];

        foreach ($periods as $sourcePeriod => $period) {
            $home = ['period' => $period];
            $away = ['period' => $period];
            $stats = [];
            $this->collectStats($payload[$sourcePeriod] ?? [], $stats);

            foreach ($stats as $stat) {
                $label = $stat['name'];
                if (isset(self::STAT_MAP[$label])) {
                    $field = self::STAT_MAP[$label];
                    if (!array_key_exists($field, $home)) {
                        $home[$field] = $this->parseScalar($stat['home_team'] ?? null);
                        $away[$field] = $this->parseScalar($stat['away_team'] ?? null);
                    }
                    continue;
                }

                if (isset(self::RATIO_MAP[$label])) {
                    [$accuracy, $completed, $attempted] = self::RATIO_MAP[$label];
                    $this->applyRatio($home, $stat['home_team'] ?? null, $accuracy, $completed, $attempted);
                    $this->applyRatio($away, $stat['away_team'] ?? null, $accuracy, $completed, $attempted);
                }
            }

            $result[$period] = ['home' => $home, 'away' => $away];
        }

        return $result;
    }

    private function collectStats(mixed $node, array &$stats): void
    {
        if (!is_array($node)) {
            return;
        }

        if (isset($node['name']) && (array_key_exists('home_team', $node) || array_key_exists('away_team', $node))) {
            $stats[] = $node;
            return;
        }

        foreach ($node as $child) {
            $this->collectStats($child, $stats);
        }
    }

    private function applyRatio(array &$side, mixed $value, ?string $accuracy, string $completed, string $attempted): void
    {
        if (array_key_exists($attempted, $side)) {
            return;
        }

        $parsed = $this->parsePercentageCount($value);
        if ($accuracy !== null) {
            $side[$accuracy] = $parsed['accuracy'];
        }
        $side[$completed] = $parsed['completed'];
        $side[$attempted] = $parsed['attempted'];
    }

    public function parsePercentageCount(mixed $value): array
    {
        if (!is_string($value) || !preg_match('/([0-9.]+)%\s*\((\d+)\/(\d+)\)/', trim($value), $matches)) {
            return ['accuracy' => null, 'completed' => null, 'attempted' => null];
        }

        return ['accuracy' => (float) $matches[1], 'completed' => (int) $matches[2], 'attempted' => (int) $matches[3]];
    }

    private function parseScalar(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && str_ends_with(trim($value), '%')) {
            return (float) rtrim(trim($value), '%');
        }
        return is_numeric($value) ? (float) $value : null;
    }
}
