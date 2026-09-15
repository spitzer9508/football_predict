<?php

namespace App\Services\Football;

use App\Support\Database;

final class ApiSnapshotStore
{
    public function save(string $endpoint, array $payload, ?string $entityType = null, ?string $entityId = null, ?int $httpStatus = 200): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        $sql = 'INSERT INTO api_snapshots (provider, endpoint, entity_type, entity_id, response_json, response_hash, http_status, fetched_at)
                VALUES (:provider, :endpoint, :entity_type, :entity_id, :response_json, :response_hash, :http_status, NOW())';

        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'provider' => 'flashscore4',
            'endpoint' => $endpoint,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'response_json' => $json,
            'response_hash' => hash('sha256', $json),
            'http_status' => $httpStatus,
        ]);
    }
}
