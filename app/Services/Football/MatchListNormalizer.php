<?php

namespace App\Services\Football;

final class MatchListNormalizer
{
    public function normalize(array $payload): array
    {
        $fixtures = [];

        foreach ($payload as $tournament) {
            if (!is_array($tournament)) continue;

            $competitionName = (string) ($tournament['name'] ?? '');
            $countryName = (string) ($tournament['country_name'] ?? '');
            if (str_contains($competitionName, ':')) {
                [, $competitionName] = array_pad(explode(':', $competitionName, 2), 2, '');
                $competitionName = trim($competitionName);
            }

            foreach (($tournament['matches'] ?? []) as $match) {
                if (!is_array($match) || empty($match['match_id'])) continue;

                $status = is_array($match['match_status'] ?? null) ? $match['match_status'] : [];
                $scores = is_array($match['scores'] ?? null) ? $match['scores'] : [];

                $fixtures[] = [
                    'provider_id' => (string) $match['match_id'],
                    'competition' => [
                        'provider_id' => (string) ($tournament['tournament_id'] ?? ''),
                        'name' => $competitionName,
                        'country_name' => $countryName !== '' ? $countryName : null,
                        'country_code' => null,
                        'logo' => $tournament['image_path'] ?? null,
                    ],
                    'home_team' => $this->team($match['home_team'] ?? [], $countryName),
                    'away_team' => $this->team($match['away_team'] ?? [], $countryName),
                    'kickoff' => $match['timestamp'] ?? null,
                    'status' => $this->status($status),
                    'round_name' => null,
                    'venue' => null,
                    'referee_name' => null,
                    'home_score' => $scores['home'] ?? null,
                    'away_score' => $scores['away'] ?? null,
                    'ht_home_score' => null,
                    'ht_away_score' => null,
                    '_is_finished' => ($status['is_finished'] ?? false) === true,
                    '_is_cancelled' => ($status['is_cancelled'] ?? false) === true,
                    '_is_postponed' => ($status['is_postponed'] ?? false) === true,
                ];
            }
        }

        return $fixtures;
    }

    private function team(mixed $team, string $country): array
    {
        $team = is_array($team) ? $team : [];
        return [
            'provider_id' => (string) ($team['team_id'] ?? ''),
            'name' => (string) ($team['name'] ?? ''),
            'short_name' => $team['short_name'] ?? null,
            'country' => $country !== '' ? $country : null,
            'logo' => $team['image_path'] ?? $team['small_image_path'] ?? null,
        ];
    }

    private function status(array $status): string
    {
        if (($status['is_cancelled'] ?? false) === true) return 'cancelled';
        if (($status['is_postponed'] ?? false) === true) return 'postponed';
        if (($status['is_finished'] ?? false) === true) return 'finished';
        if (($status['is_in_progress'] ?? false) === true) return 'live';
        if (($status['is_started'] ?? false) === true) return 'started';
        return 'scheduled';
    }
}
