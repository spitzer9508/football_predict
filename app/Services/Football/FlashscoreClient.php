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

        $url = rtrim($this->config['base_url'], '/') . '/' . ltrim($endpoint, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) $this->config['timeout']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $this->config['connect_timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'x-rapidapi-host: ' . $this->config['host'],
            'x-rapidapi-key: ' . $key,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Football API connection failed: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Football API returned HTTP ' . $status);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('Football API returned invalid JSON');
        }

        return $data;
    }

    public function momentum(string $matchId): array
    {
        return $this->get('matches/momentum', ['match_id' => $matchId]);
    }
}
