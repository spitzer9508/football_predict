<?php

namespace App\Services\Football;

final class DailyFixtureCollector
{
    public function __construct(
        private readonly FlashscoreClient $client = new FlashscoreClient(),
        private readonly MatchListNormalizer $normalizer = new MatchListNormalizer(),
        private readonly FixtureImporter $fixtures = new FixtureImporter(),
        private readonly ApiSnapshotStore $snapshots = new ApiSnapshotStore(),
        private readonly MatchStatisticsService $statistics = new MatchStatisticsService()
    ) {
    }

    public function collect(int $day = 0, string $timezone = 'Europe/Berlin', bool $importFinishedStats = false): array
    {
        $payload = $this->client->matchList($day, $timezone);
        $this->snapshots->save('matches/list', $payload, 'day', (string) $day, 200);

        $fixtures = $this->normalizer->normalize($payload);
        $result = [
            'discovered' => count($fixtures),
            'imported' => 0,
            'stats_imported' => 0,
            'stats_skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($fixtures as $fixture) {
            try {
                $this->fixtures->import($fixture);
                $result['imported']++;

                if ($importFinishedStats && ($fixture['_is_finished'] ?? false)) {
                    try {
                        $stats = $this->statistics->importByProviderId($fixture['provider_id']);
                        if ($stats['statistics_rows'] > 0) $result['stats_imported']++;
                        else $result['stats_skipped']++;
                    } catch (\Throwable $e) {
                        $result['stats_skipped']++;
                        $result['errors'][] = $fixture['provider_id'] . ' stats: ' . $e->getMessage();
                    }
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = ($fixture['provider_id'] ?? 'unknown') . ': ' . $e->getMessage();
            }
        }

        return $result;
    }
}
