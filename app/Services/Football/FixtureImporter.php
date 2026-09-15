<?php

namespace App\Services\Football;

use App\Support\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class FixtureImporter
{
    public function import(array $fixture): int
    {
        $providerId = (string) ($fixture['provider_id'] ?? '');
        $competition = $fixture['competition'] ?? null;
        $home = $fixture['home_team'] ?? null;
        $away = $fixture['away_team'] ?? null;

        if ($providerId === '' || !is_array($competition) || !is_array($home) || !is_array($away)) {
            throw new RuntimeException('Fixture provider ID, competition, home team and away team are required.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $competitionId = $this->upsertCompetition($pdo, $competition);
            $homeTeamId = $this->upsertTeam($pdo, $home);
            $awayTeamId = $this->upsertTeam($pdo, $away);
            $kickoff = $this->normalizeKickoff($fixture['kickoff'] ?? null);

            $sql = 'INSERT INTO fixtures
                (provider_id, competition_id, home_team_id, away_team_id, kickoff, status, round_name, venue, referee_name,
                 home_score, away_score, ht_home_score, ht_away_score, last_synced_at)
                VALUES
                (:provider_id, :competition_id, :home_team_id, :away_team_id, :kickoff, :status, :round_name, :venue, :referee_name,
                 :home_score, :away_score, :ht_home_score, :ht_away_score, NOW())
                ON DUPLICATE KEY UPDATE
                 competition_id = VALUES(competition_id), home_team_id = VALUES(home_team_id), away_team_id = VALUES(away_team_id),
                 kickoff = VALUES(kickoff), status = VALUES(status), round_name = VALUES(round_name), venue = VALUES(venue),
                 referee_name = VALUES(referee_name), home_score = VALUES(home_score), away_score = VALUES(away_score),
                 ht_home_score = VALUES(ht_home_score), ht_away_score = VALUES(ht_away_score), last_synced_at = NOW()';

            $pdo->prepare($sql)->execute([
                'provider_id' => $providerId,
                'competition_id' => $competitionId,
                'home_team_id' => $homeTeamId,
                'away_team_id' => $awayTeamId,
                'kickoff' => $kickoff,
                'status' => (string) ($fixture['status'] ?? 'scheduled'),
                'round_name' => $fixture['round_name'] ?? null,
                'venue' => $fixture['venue'] ?? null,
                'referee_name' => $fixture['referee_name'] ?? null,
                'home_score' => $fixture['home_score'] ?? null,
                'away_score' => $fixture['away_score'] ?? null,
                'ht_home_score' => $fixture['ht_home_score'] ?? null,
                'ht_away_score' => $fixture['ht_away_score'] ?? null,
            ]);

            $statement = $pdo->prepare('SELECT id FROM fixtures WHERE provider_id = ?');
            $statement->execute([$providerId]);
            $fixtureId = (int) $statement->fetchColumn();
            $pdo->commit();

            return $fixtureId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function upsertCompetition(PDO $pdo, array $data): int
    {
        $providerId = (string) ($data['provider_id'] ?? '');
        $name = (string) ($data['name'] ?? '');
        if ($providerId === '' || $name === '') {
            throw new RuntimeException('Competition provider ID and name are required.');
        }

        $pdo->prepare('INSERT INTO competitions (provider_id, name, country_name, country_code, logo)
            VALUES (:provider_id, :name, :country_name, :country_code, :logo)
            ON DUPLICATE KEY UPDATE name=VALUES(name), country_name=VALUES(country_name), country_code=VALUES(country_code), logo=VALUES(logo)')
            ->execute([
                'provider_id' => $providerId, 'name' => $name,
                'country_name' => $data['country_name'] ?? null, 'country_code' => $data['country_code'] ?? null,
                'logo' => $data['logo'] ?? null,
            ]);

        $statement = $pdo->prepare('SELECT id FROM competitions WHERE provider_id = ?');
        $statement->execute([$providerId]);
        return (int) $statement->fetchColumn();
    }

    private function upsertTeam(PDO $pdo, array $data): int
    {
        $providerId = (string) ($data['provider_id'] ?? '');
        $name = (string) ($data['name'] ?? '');
        if ($providerId === '' || $name === '') {
            throw new RuntimeException('Team provider ID and name are required.');
        }

        $pdo->prepare('INSERT INTO teams (provider_id, name, short_name, country, logo)
            VALUES (:provider_id, :name, :short_name, :country, :logo)
            ON DUPLICATE KEY UPDATE name=VALUES(name), short_name=VALUES(short_name), country=VALUES(country), logo=VALUES(logo)')
            ->execute([
                'provider_id' => $providerId, 'name' => $name, 'short_name' => $data['short_name'] ?? null,
                'country' => $data['country'] ?? null, 'logo' => $data['logo'] ?? null,
            ]);

        $statement = $pdo->prepare('SELECT id FROM teams WHERE provider_id = ?');
        $statement->execute([$providerId]);
        return (int) $statement->fetchColumn();
    }

    private function normalizeKickoff(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new RuntimeException('Fixture kickoff is required.');
        }

        if (is_numeric($value)) {
            return (new DateTimeImmutable('@' . (int) $value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        return (new DateTimeImmutable((string) $value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
