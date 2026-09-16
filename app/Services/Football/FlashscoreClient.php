<?php

namespace App\Services\Football;

use RuntimeException;

final class FlashscoreClient
{
    private array $config;

    public function __construct()
    {
        $football = require dirname(__DIR__, 3) . '/config/football.php';
        $this->config = $football['flashscore4'];
    }

    public function get(string $endpoint, array $query = []): array
    {
        $key = (string) ($this->config['api_key'] ?? '');
        if ($key === '') {
            throw new RuntimeException('FLASHSCORE_API_KEY is missing from .env');
        }

        $endpoint = ltrim($endpoint, '/');
        $url = rtrim($this->config['base_url'], '/') . '/' . $endpoint;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) $this->config['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $this->config['connect_timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'x-rapidapi-host: ' . $this->config['host'],
            'x-rapidapi-key: ' . $key,
        ]);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $header) use (&$responseHeaders): int {
            $length = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $length;
        });

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Football API connection failed: ' . $error);
        }

        if ($status === 429) {
            $retryAfter = isset($responseHeaders['retry-after']) && ctype_digit($responseHeaders['retry-after'])
                ? (int) $responseHeaders['retry-after']
                : null;
            throw new FootballApiException(
                $this->buildHttpErrorMessage($status, $endpoint, $query, $body),
                429,
                $retryAfter
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new FootballApiException(
                $this->buildHttpErrorMessage($status, $endpoint, $query, $body),
                $status
            );
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Football API returned invalid JSON');
        }

        return $data;
    }

    private function buildHttpErrorMessage(int $status, string $endpoint, array $query, string $body): string
    {
        $safeQuery = $this->sanitizeQuery($query);
        $message = sprintf(
            'Football API returned HTTP %d. Endpoint: /%s',
            $status,
            ltrim($endpoint, '/')
        );

        if ($safeQuery !== []) {
            $message .= '. Parameters: ' . http_build_query($safeQuery);
        }

        $providerMessage = $this->extractProviderMessage($body);
        if ($providerMessage !== null) {
            $message .= '. Provider message: ' . $providerMessage;
        }

        return $message;
    }

    private function sanitizeQuery(array $query): array
    {
        $sensitiveKeys = [
            'api_key', 'apikey', 'api-key', 'key', 'token', 'access_token',
            'authorization', 'x-rapidapi-key',
        ];

        foreach ($query as $name => &$value) {
            if (in_array(strtolower((string) $name), $sensitiveKeys, true)) {
                $value = '[REDACTED]';
            }
        }
        unset($value);

        return $query;
    }

    private function extractProviderMessage(string $body): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            foreach (['message', 'error', 'detail', 'description'] as $key) {
                if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
                    return $this->cleanProviderMessage((string) $decoded[$key]);
                }
            }

            foreach (['errors', 'data'] as $container) {
                if (!isset($decoded[$container])) {
                    continue;
                }

                $candidate = $decoded[$container];
                if (is_string($candidate)) {
                    return $this->cleanProviderMessage($candidate);
                }
                if (is_array($candidate)) {
                    $encoded = json_encode($candidate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (is_string($encoded)) {
                        return $this->cleanProviderMessage($encoded);
                    }
                }
            }
        }

        return $this->cleanProviderMessage($body);
    }

    private function cleanProviderMessage(string $message): ?string
    {
        $message = preg_replace('/[\r\n\t]+/', ' ', trim($message));
        if (!is_string($message) || $message === '') {
            return null;
        }

        $apiKey = (string) ($this->config['api_key'] ?? '');
        if ($apiKey !== '') {
            $message = str_replace($apiKey, '[REDACTED]', $message);
        }

        $message = preg_replace(
            '/((?:x-rapidapi-key|api[_-]?key|access[_-]?token|authorization)\s*[=:]\s*)[^\s,;]+/i',
            '$1[REDACTED]',
            $message
        );

        if (!is_string($message)) {
            return null;
        }

        if (strlen($message) > 500) {
            $message = substr($message, 0, 500) . '...';
        }

        return $message;
    }

    public function matchList(int $day = 0, string $timezone = 'Europe/Berlin', int $sportId = 1): array
    {
        return $this->get('matches/list', [
            'sport_id' => $sportId,
            'day' => $day,
            'timezone' => $timezone,
        ]);
    }

    public function tournamentResults(string $tournamentTemplateId, int $seasonId, int $page = 1): array
    {
        if ($tournamentTemplateId === '') {
            throw new RuntimeException('Tournament template ID cannot be empty.');
        }
        if ($seasonId < 1) {
            throw new RuntimeException('Season ID must be greater than zero.');
        }
        if ($page < 1) {
            throw new RuntimeException('Page must be greater than zero.');
        }

        return $this->get('tournaments/results', [
            'tournament_template_id' => $tournamentTemplateId,
            'season_id' => $seasonId,
            'page' => $page,
        ]);
    }

    public function momentum(string $matchId): array
    {
        return $this->get('matches/momentum', ['match_id' => $matchId]);
    }

    public function matchDetails(string $matchId): array
    {
        return $this->get('matches/details', ['match_id' => $matchId]);
    }

    public function matchStatistics(string $matchId): array
    {
        return $this->get('matches/match/stats', ['match_id' => $matchId]);
    }
}
