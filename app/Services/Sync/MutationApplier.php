<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Central\TenantMembership;
use App\Models\Tenant\IdempotencyKey;
use App\Services\Sync\Handlers\MutationHandler;
use App\Services\Sync\Handlers\MutationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies a batch of client mutations against the active tenant DB.
 * Each mutation is wrapped in a per-row transaction so a single failure
 * never aborts the rest of the batch.
 *
 * Idempotency: if the same (idempotency_key) was already processed within
 * the retention window, the cached response is returned instead of
 * re-running the mutation.
 */
class MutationApplier
{
    public function __construct(
        private readonly SyncRegistry $registry,
        private readonly ConflictDetector $conflicts,
    ) {}

    /**
     * @param  array<int,array<string,mixed>>  $mutations
     * @return array<int,array<string,mixed>>
     */
    public function apply(array $mutations, TenantMembership $membership): array
    {
        $results = [];
        foreach ($mutations as $mutation) {
            $results[] = $this->applyOne($mutation, $membership);
        }

        return $results;
    }

    private function applyOne(array $mutation, TenantMembership $membership): array
    {
        $clientUuid = (string) ($mutation['client_uuid'] ?? '');
        $idempotency = (string) ($mutation['idempotency_key'] ?? '');
        $entity = (string) ($mutation['entity'] ?? '');
        $op = (string) ($mutation['op'] ?? '');

        if ($idempotency !== '' && ($cached = IdempotencyKey::query()->find($idempotency))) {
            $payload = json_decode((string) $cached->response_json, true) ?: [];
            $payload['client_uuid'] = $clientUuid;
            $payload['status'] = 'duplicate';

            return $payload;
        }

        if (! $this->registry->canMutate($entity, $membership)) {
            return $this->failure($clientUuid, 'forbidden', "Role may not mutate '$entity'.");
        }

        $handlerClass = $this->registry->mutationHandler($entity);
        if (! class_exists($handlerClass)) {
            return $this->failure($clientUuid, 'unsupported_entity', "No handler for '$entity'.");
        }

        /** @var MutationHandler $handler */
        $handler = app($handlerClass);

        try {
            $result = DB::connection('tenant')->transaction(function () use ($handler, $entity, $op, $mutation, $membership) {
                $conflict = $this->conflicts->check($entity, $op, $mutation);
                if ($conflict !== null) {
                    return $conflict;
                }

                return $handler->handle($entity, $op, $mutation, $membership);
            });

            $payload = $result->toArray($clientUuid);

            if ($idempotency !== '') {
                IdempotencyKey::query()->updateOrCreate(['key' => $idempotency], [
                    'user_central_id' => (string) $membership->user_id,
                    'entity' => $entity,
                    'op' => $op,
                    'response_json' => json_encode($payload),
                    'created_at' => now(),
                ]);
            }

            return $payload;
        } catch (Throwable $e) {
            Log::error('sync.mutation_failed', [
                'entity' => $entity,
                'op' => $op,
                'client_uuid' => $clientUuid,
                'exception' => $e->getMessage(),
            ]);

            return $this->failure($clientUuid, 'server_error', $e->getMessage());
        }
    }

    private function failure(string $clientUuid, string $code, string $message): array
    {
        return [
            'client_uuid' => $clientUuid,
            'status' => 'conflict',
            'conflict_type' => $code,
            'errors' => ['_' => [$message]],
        ];
    }
}
