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

    public function normalizeStatistics(array $payload): array
    {
        $periods = [
            'match' => 'MATCH',
            '1st-half' => 'FIRST_HALF',
            '2nd-half' => 'SECOND_HALF',
        ];

        $result = [];

        foreach ($periods as $sourcePeriod => $period) {
            $home = ['period' => $period];
            $away = ['period' => $period];

            foreach (($payload[$sourcePeriod] ?? []) as $stat) {
                $label = $stat['name'] ?? null;
                if (!$label || !isset(self::STAT_MAP[$label])) {
                    continue;
                }

                $field = self::STAT_MAP[$label];
                if (array_key_exists($field, $home)) {
                    continue;
                }

                $home[$field] = $this->parseScalar($stat['home_team'] ?? null);
                $away[$field] = $this->parseScalar($stat['away_team'] ?? null);
            }

            $result[$period] = ['home' => $home, 'away' => $away];
        }

        return $result;
    }

    public function parsePercentageCount(mixed $value): array
    {
        if (!is_string($value)) {
            return ['accuracy' => null, 'completed' => null, 'attempted' => null];
        }

        if (!preg_match('/([0-9.]+)%\\s*\\((\\d+)\\/(\\d+)\\)/', $value, $matches)) {
            return ['accuracy' => null, 'completed' => null, 'attempted' => null];
        }

        return [
            'accuracy' => (float) $matches[1],
            'completed' => (int) $matches[2],
            'attempted' => (int) $matches[3],
        ];
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
