<?php

namespace App\Services\Football;

use App\Support\Database;
use RuntimeException;

final class MatchStatisticsService
{
    public function __construct(
        private readonly FlashscoreClient $client = new FlashscoreClient(),
        private readonly ApiSnapshotStore $snapshots = new ApiSnapshotStore(),
        private readonly StatisticsImporter $importer = new StatisticsImporter()
    ) {
    }

    public function importByProviderId(string $providerMatchId): array
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'SELECT id, home_team_id, away_team_id FROM fixtures WHERE provider_id = :provider_id LIMIT 1'
        );
        $statement->execute(['provider_id' => $providerMatchId]);
        $fixture = $statement->fetch();

        if (!$fixture) {
            throw new RuntimeException(
                'Fixture ' . $providerMatchId . ' is not in the local database yet. Import the fixture before its statistics.'
            );
        }

        $payload = $this->client->matchStatistics($providerMatchId);

        $this->snapshots->save(
            'matches/match/stats',
            $payload,
            'fixture',
            $providerMatchId,
            200
        );

        $this->importer->import(
            (int) $fixture['id'],
            (int) $fixture['home_team_id'],
            (int) $fixture['away_team_id'],
            $payload
        );

        $count = $pdo->prepare('SELECT COUNT(*) FROM team_match_stats WHERE fixture_id = ?');
        $count->execute([(int) $fixture['id']]);

        return [
            'fixture_id' => (int) $fixture['id'],
            'provider_match_id' => $providerMatchId,
            'statistics_rows' => (int) $count->fetchColumn(),
        ];
    }
}
