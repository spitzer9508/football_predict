<?php

namespace App\Services\Football;

use App\Support\Database;

final class DailyFixtureCollector
{
    private array $competitionConfig;

    public function __construct(
        private readonly FlashscoreClient $client = new FlashscoreClient(),
        private readonly MatchListNormalizer $normalizer = new MatchListNormalizer(),
        private readonly FixtureImporter $fixtures = new FixtureImporter(),
        private readonly ApiSnapshotStore $snapshots = new ApiSnapshotStore(),
        private readonly MatchStatisticsService $statistics = new MatchStatisticsService()
    ) {
        $this->competitionConfig = require dirname(__DIR__, 3) . '/config/competitions.php';
    }

    public function collect(
        int $day = 0,
        string $timezone = 'Europe/Berlin',
        bool $importFinishedStats = false,
        bool $onlyConfiguredCompetitions = true,
        ?int $statisticsLimit = null
    ): array {
        $payload = $this->client->matchList($day, $timezone);
        $this->snapshots->save('matches/list', $payload, 'day', (string) $day, 200);

        $allFixtures = $this->normalizer->normalize($payload);
        $fixtures = $onlyConfiguredCompetitions
            ? array_values(array_filter($allFixtures, fn(array $fixture): bool => $this->isConfiguredCompetition($fixture)))
            : $allFixtures;

        $statisticsLimit ??= (int) ($this->competitionConfig['statistics_per_run'] ?? 20);
        $statisticsLimit = max(0, $statisticsLimit);

        $result = [
            'discovered' => count($allFixtures),
            'eligible' => count($fixtures),
            'filtered' => count($allFixtures) - count($fixtures),
            'imported' => 0,
            'stats_imported' => 0,
            'stats_skipped' => 0,
            'stats_already_imported' => 0,
            'stats_budget_exhausted' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $statsCalls = 0;
        foreach ($fixtures as $fixture) {
            try {
                $fixtureId = $this->fixtures->import($fixture);
                $result['imported']++;

                if (!$importFinishedStats || !($fixture['_is_finished'] ?? false)) {
                    continue;
                }

                if ($this->statisticsAlreadyImported($fixtureId)) {
                    $result['stats_already_imported']++;
                    continue;
                }

                if ($statsCalls >= $statisticsLimit) {
                    $result['stats_budget_exhausted']++;
                    continue;
                }

                $statsCalls++;
                try {
                    $stats = $this->statistics->importByProviderId($fixture['provider_id']);
                    if ($stats['statistics_rows'] > 0) {
                        $result['stats_imported']++;
                    } else {
                        $result['stats_skipped']++;
                    }
                } catch (\Throwable $e) {
                    $result['stats_skipped']++;
                    $result['errors'][] = $fixture['provider_id'] . ' stats: ' . $e->getMessage();
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = ($fixture['provider_id'] ?? 'unknown') . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    private function isConfiguredCompetition(array $fixture): bool
    {
        $competition = $fixture['competition'] ?? [];
        $name = $this->normalizeName((string) ($competition['name'] ?? ''));
        $country = $this->normalizeName((string) ($competition['country_name'] ?? ''));

        foreach (($this->competitionConfig['v1'] ?? []) as $allowed) {
            if ($country !== $this->normalizeName((string) ($allowed['country'] ?? ''))) {
                continue;
            }
            foreach (($allowed['names'] ?? []) as $allowedName) {
                $allowedNormalized = $this->normalizeName((string) $allowedName);
                if ($name === $allowedNormalized || str_starts_with($name, $allowedNormalized . ' - ')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function normalizeName(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    private function statisticsAlreadyImported(int $fixtureId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT stats_imported FROM fixtures WHERE id = ? LIMIT 1'
        );
        $statement->execute([$fixtureId]);
        return (int) $statement->fetchColumn() === 1;
    }
}
