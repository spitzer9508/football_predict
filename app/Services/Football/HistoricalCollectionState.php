<?php

namespace App\Services\Football;

use App\Support\Database;
use PDO;

final class HistoricalCollectionState
{
    private const STATE_KEY = 'v1_history';

    public function ensureTable(): void
    {
        Database::connection()->exec(
            "CREATE TABLE IF NOT EXISTS collector_state (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                state_key VARCHAR(100) NOT NULL,
                current_day INT NOT NULL DEFAULT -1,
                target_day INT NOT NULL DEFAULT -365,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                last_run_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_collector_state_key (state_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function load(int $targetDay = -365): array
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT * FROM collector_state WHERE state_key = ? LIMIT 1');
        $statement->execute([self::STATE_KEY]);
        $state = $statement->fetch(PDO::FETCH_ASSOC);

        if ($state) return $state;

        $insert = $pdo->prepare(
            "INSERT INTO collector_state (state_key, current_day, target_day, status) VALUES (?, -1, ?, 'active')"
        );
        $insert->execute([self::STATE_KEY, $targetDay]);
        return $this->load($targetDay);
    }

    public function saveProgress(int $currentDay, int $targetDay, string $status = 'active'): void
    {
        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $statement = Database::connection()->prepare(
            'UPDATE collector_state SET current_day = ?, target_day = ?, status = ?, last_run_at = NOW(), completed_at = ? WHERE state_key = ?'
        );
        $statement->execute([$currentDay, $targetDay, $status, $completedAt, self::STATE_KEY]);
    }

    public function reset(int $targetDay = -365): void
    {
        $this->ensureTable();
        $statement = Database::connection()->prepare(
            "INSERT INTO collector_state (state_key, current_day, target_day, status, last_run_at, completed_at)
             VALUES (?, -1, ?, 'active', NULL, NULL)
             ON DUPLICATE KEY UPDATE current_day = -1, target_day = VALUES(target_day), status = 'active', last_run_at = NULL, completed_at = NULL"
        );
        $statement->execute([self::STATE_KEY, $targetDay]);
    }
}
