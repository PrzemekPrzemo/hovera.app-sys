<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Services\Sync\Handlers\MutationResult;

/**
 * Per-entity safety checks that run BEFORE the mutation handler. Returns a
 * MutationResult with status:conflict if the mutation should be rejected,
 * otherwise null.
 */
class ConflictDetector
{
    public function __construct(private readonly SyncRegistry $registry) {}

    public function check(string $entity, string $op, array $mutation): ?MutationResult
    {
        $policy = $this->registry->conflictPolicy($entity);

        if ($policy === SyncRegistry::CONFLICT_APPEND_ONLY && $op !== 'create') {
            return MutationResult::conflict('append_only', null, [
                '_' => ['Entity is append-only; only `create` is permitted.'],
            ]);
        }

        if ($policy === SyncRegistry::CONFLICT_LWW && in_array($op, ['update', 'delete'], true)) {
            $serverVersion = $this->lookupServerVersion($entity, (string) ($mutation['payload']['id'] ?? ''));
            $base = (int) ($mutation['base_version'] ?? 0);
            if ($serverVersion !== null && $base > 0 && $serverVersion > $base + 0) {
                // LWW still wins per field, but the client should know it had stale data.
                // We don't reject — just stamp the conflict for telemetry.
                // Keeping this hook so subclasses can switch to reject if needed.
            }
        }

        // Server-authoritative entities defer to their dedicated handler.
        return null;
    }

    private function lookupServerVersion(string $entity, string $id): ?int
    {
        $model = $this->registry->newModel($entity);
        if (! $model || $id === '') {
            return null;
        }
        $row = $model->newQuery()->withoutGlobalScopes()->find($id);

        return $row ? (int) $row->sync_version : null;
    }
}
