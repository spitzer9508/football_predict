<?php

namespace App\Services\Football;

use App\Support\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class HistoricalCollectionState
{
    private const STATE_KEY = 'v1_history';

    public function ensureTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS collector_state (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            state_key VARCHAR(100) NOT NULL,
            cursor_date DATE NULL,
            end_date DATE NULL,
            timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            last_run_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_collector_state_key (state_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM collector_state')->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[$column['Field']] = true;
        }
        if (!isset($columns['cursor_date'])) $pdo->exec('ALTER TABLE collector_state ADD COLUMN cursor_date DATE NULL AFTER state_key');
        if (!isset($columns['end_date'])) $pdo->exec('ALTER TABLE collector_state ADD COLUMN end_date DATE NULL AFTER cursor_date');
        if (!isset($columns['timezone'])) $pdo->exec("ALTER TABLE collector_state ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Berlin' AFTER end_date");
    }

    public function load(int $days = 365, string $timezone = 'Europe/Berlin'): array
    {
        $this->ensureTable();
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT * FROM collector_state WHERE state_key = ? LIMIT 1');
        $statement->execute([self::STATE_KEY]);
        $state = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$state) {
            $this->reset($days, $timezone);
            return $this->load($days, $timezone);
        }

        if (empty($state['cursor_date']) || empty($state['end_date'])) {
            $this->reset($days, $timezone);
            $statement->execute([self::STATE_KEY]);
            return $statement->fetch(PDO::FETCH_ASSOC);
        }
        return $state;
    }

    public function saveProgress(string $cursorDate, string $endDate, string $timezone, string $status = 'active'): void
    {
        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $statement = Database::connection()->prepare(
            'UPDATE collector_state SET cursor_date=?, end_date=?, timezone=?, status=?, last_run_at=NOW(), completed_at=? WHERE state_key=?'
        );
        $statement->execute([$cursorDate, $endDate, $timezone, $status, $completedAt, self::STATE_KEY]);
    }

    public function reset(int $days = 365, string $timezone = 'Europe/Berlin'): void
    {
        $this->ensureTable();
        $days = max(1, $days);
        $tz = new DateTimeZone($timezone);
        $today = new DateTimeImmutable('today', $tz);
        $cursor = $today->modify('-1 day')->format('Y-m-d');
        $end = $today->modify('-' . $days . ' days')->format('Y-m-d');
        $statement = Database::connection()->prepare(
            "INSERT INTO collector_state (state_key,cursor_date,end_date,timezone,status,last_run_at,completed_at)
             VALUES (?,?,?,?, 'active',NULL,NULL)
             ON DUPLICATE KEY UPDATE cursor_date=VALUES(cursor_date),end_date=VALUES(end_date),timezone=VALUES(timezone),status='active',last_run_at=NULL,completed_at=NULL"
        );
        $statement->execute([self::STATE_KEY, $cursor, $end, $timezone]);
    }

    public function dayOffset(string $date, string $timezone): int
    {
        $tz = new DateTimeZone($timezone);
        $today = new DateTimeImmutable('today', $tz);
        $target = new DateTimeImmutable($date, $tz);
        return (int) $today->diff($target)->format('%r%a');
    }
}
