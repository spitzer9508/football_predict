<?php

namespace App\Services\Football;

use App\Support\Database;
use PDO;
use RuntimeException;

final class SeasonHistoricalCollectionState
{
    public function ensureTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS season_collector_state (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tournament_template_id VARCHAR(100) NOT NULL,
            season_id BIGINT NOT NULL,
            cursor_page INT NOT NULL DEFAULT 1,
            cursor_match_index INT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            last_run_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_season_collector (tournament_template_id, season_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function load(string $templateId, int $seasonId): array
    {
        if ($templateId === '' || $seasonId < 1) {
            throw new RuntimeException('Tournament template ID and season ID are required.');
        }

        $this->ensureTable();
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT * FROM season_collector_state WHERE tournament_template_id=? AND season_id=? LIMIT 1');
        $statement->execute([$templateId, $seasonId]);
        $state = $statement->fetch(PDO::FETCH_ASSOC);
        if ($state) {
            return $state;
        }

        $this->reset($templateId, $seasonId);
        $statement->execute([$templateId, $seasonId]);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function save(string $templateId, int $seasonId, int $page, int $matchIndex, string $status = 'active'): void
    {
        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $statement = Database::connection()->prepare(
            'UPDATE season_collector_state SET cursor_page=?,cursor_match_index=?,status=?,last_run_at=NOW(),completed_at=? WHERE tournament_template_id=? AND season_id=?'
        );
        $statement->execute([max(1, $page), max(0, $matchIndex), $status, $completedAt, $templateId, $seasonId]);
    }

    public function reset(string $templateId, int $seasonId): void
    {
        $this->ensureTable();
        $statement = Database::connection()->prepare(
            "INSERT INTO season_collector_state (tournament_template_id,season_id,cursor_page,cursor_match_index,status,last_run_at,completed_at)
             VALUES (?,?,1,0,'active',NULL,NULL)
             ON DUPLICATE KEY UPDATE cursor_page=1,cursor_match_index=0,status='active',last_run_at=NULL,completed_at=NULL"
        );
        $statement->execute([$templateId, $seasonId]);
    }
}
