<?php

namespace App\Services\Football;

use RuntimeException;

final class MatchDetailsNormalizer
{
    public function normalize(array $payload): array
    {
        $matchId = (string) ($payload['match_id'] ?? '');
        $tournament = $payload['tournament'] ?? [];
        $home = $payload['home_team'] ?? [];
        $away = $payload['away_team'] ?? [];
        $country = $payload['country'] ?? [];
        $status = $payload['match_status'] ?? [];
        $scores = $payload['scores'] ?? [];

        if ($matchId === '' || !is_array($tournament) || !is_array($home) || !is_array($away)) {
            throw new RuntimeException('Match details payload is missing required fixture data.');
        }

        $competitionName = (string) ($tournament['name'] ?? '');
        $roundName = null;
        if (preg_match('/^(.*?)\s+-\s+(Round\s+.+)$/i', $competitionName, $matches)) {
            $competitionName = trim($matches[1]);
            $roundName = trim($matches[2]);
        }

        return [
            'provider_id' => $matchId,
            'competition' => [
                'provider_id' => (string) ($tournament['tournament_id'] ?? ''),
                'name' => $competitionName,
                'country_name' => $country['name'] ?? null,
                'country_code' => null,
                'logo' => null,
            ],
            'home_team' => $this->team($home, $country['name'] ?? null),
            'away_team' => $this->team($away, $country['name'] ?? null),
            'kickoff' => $payload['timestamp'] ?? null,
            'status' => $this->status(is_array($status) ? $status : []),
            'round_name' => $roundName,
            'venue' => is_array($payload['venue'] ?? null) ? ($payload['venue']['name'] ?? null) : null,
            'referee_name' => $payload['referee'] ?? null,
            'home_score' => $scores['home_total'] ?? $scores['home'] ?? null,
            'away_score' => $scores['away_total'] ?? $scores['away'] ?? null,
            'ht_home_score' => $scores['home_1st_half'] ?? null,
            'ht_away_score' => $scores['away_1st_half'] ?? null,
        ];
    }

    private function team(array $team, mixed $country): array
    {
        return [
            'provider_id' => (string) ($team['team_id'] ?? ''),
            'name' => (string) ($team['name'] ?? ''),
            'short_name' => $team['short_name'] ?? null,
            'country' => $country,
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
