<?php

namespace App\Services\Football;

final class MatchImportService
{
    public function __construct(
        private readonly FlashscoreClient $client = new FlashscoreClient(),
        private readonly MatchDetailsNormalizer $normalizer = new MatchDetailsNormalizer(),
        private readonly FixtureImporter $fixtures = new FixtureImporter(),
        private readonly ApiSnapshotStore $snapshots = new ApiSnapshotStore(),
        private readonly MatchStatisticsService $statistics = new MatchStatisticsService()
    ) {
    }

    public function import(string $providerMatchId, bool $includeStatistics = true): array
    {
        $details = $this->client->matchDetails($providerMatchId);
        $this->snapshots->save('matches/details', $details, 'fixture', $providerMatchId, 200);

        $fixture = $this->normalizer->normalize($details);
        $fixtureId = $this->fixtures->import($fixture);

        $result = [
            'fixture_id' => $fixtureId,
            'provider_match_id' => $providerMatchId,
            'home_team' => $fixture['home_team']['name'],
            'away_team' => $fixture['away_team']['name'],
            'status' => $fixture['status'],
            'statistics_rows' => null,
        ];

        if ($includeStatistics) {
            $stats = $this->statistics->importByProviderId($providerMatchId);
            $result['statistics_rows'] = $stats['statistics_rows'];
        }

        return $result;
    }
}
