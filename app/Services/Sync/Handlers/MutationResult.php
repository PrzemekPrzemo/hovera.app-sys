<?php

declare(strict_types=1);

namespace App\Services\Sync\Handlers;

class MutationResult
{
    /**
     * @param  array<string,array<int,string>>  $errors
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $serverId = null,
        public readonly ?int $syncVersion = null,
        public readonly ?string $conflictType = null,
        public readonly ?array $currentServerState = null,
        public readonly array $errors = [],
    ) {}

    public static function applied(string $serverId, int $syncVersion): self
    {
        return new self('applied', $serverId, $syncVersion);
    }

    public static function conflict(string $type, ?array $currentServerState = null, array $errors = []): self
    {
        return new self('conflict', conflictType: $type, currentServerState: $currentServerState, errors: $errors);
    }

    public function toArray(string $clientUuid): array
    {
        $base = ['client_uuid' => $clientUuid, 'status' => $this->status];

        if ($this->status === 'applied') {
            return $base + ['server_id' => $this->serverId, 'sync_version' => $this->syncVersion];
        }

        return $base + array_filter([
            'conflict_type' => $this->conflictType,
            'current_server_state' => $this->currentServerState,
            'errors' => $this->errors ?: null,
        ]);
    }
}
