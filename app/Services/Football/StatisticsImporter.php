<?php

namespace App\Services\Football;

use App\Support\Database;
use PDO;
use RuntimeException;

final class StatisticsImporter
{
    public function __construct(
        private readonly FlashscoreNormalizer $normalizer = new FlashscoreNormalizer()
    ) {
    }

    public function import(int $fixtureId, int $homeTeamId, int $awayTeamId, array $payload): void
    {
        $periods = $this->normalizer->normalizeStatistics($payload);
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            foreach ($periods as $sides) {
                $this->upsert($pdo, $fixtureId, $homeTeamId, $sides['home']);
                $this->upsert($pdo, $fixtureId, $awayTeamId, $sides['away']);
            }

            $pdo->prepare('UPDATE fixtures SET stats_imported = 1, last_synced_at = NOW() WHERE id = ?')->execute([$fixtureId]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function upsert(PDO $pdo, int $fixtureId, int $teamId, array $stats): void
    {
        $allowed = [
            'period','xg','xgot','expected_assists','possession','shots','shots_on_target',
            'shots_off_target','blocked_shots','shots_inside_box','shots_outside_box','woodwork',
            'big_chances','corners','touches_opposition_box','offsides','free_kicks',
            'passes_attempted','passes_completed','pass_accuracy',
            'long_passes_attempted','long_passes_completed','long_pass_accuracy',
            'final_third_passes_attempted','final_third_passes_completed','final_third_pass_accuracy',
            'crosses_attempted','crosses_completed','cross_accuracy',
            'fouls','yellow_cards','red_cards','tackles_attempted','tackles_won','duels_won',
            'clearances','interceptions','errors_leading_to_shot','errors_leading_to_goal',
            'goalkeeper_saves','xgot_faced','goals_prevented'
        ];

        $data = ['fixture_id' => $fixtureId, 'team_id' => $teamId];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $stats)) {
                $data[$field] = $stats[$field];
            }
        }

        if (!isset($data['period'])) {
            throw new RuntimeException('Statistics period is missing.');
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn(string $column): string => ':' . $column, $columns);
        $updates = array_values(array_filter($columns, fn(string $column): bool => !in_array($column, ['fixture_id','team_id','period'], true)));
        $updateSql = implode(', ', array_map(fn(string $column): string => $column . ' = VALUES(' . $column . ')', $updates));

        $sql = 'INSERT INTO team_match_stats (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ') '
             . 'ON DUPLICATE KEY UPDATE ' . ($updateSql !== '' ? $updateSql . ', ' : '') . 'updated_at = NOW()';

        $pdo->prepare($sql)->execute($data);
    }
}
