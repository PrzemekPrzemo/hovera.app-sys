<?php

declare(strict_types=1);

namespace App\Services\Sync\Handlers;

use App\Models\Central\TenantMembership;
use App\Services\Sync\SyncRegistry;
use Illuminate\Database\Eloquent\Model;

class GenericMutationHandler implements MutationHandler
{
    public function __construct(private readonly SyncRegistry $registry) {}

    public function handle(string $entity, string $op, array $mutation, TenantMembership $membership): MutationResult
    {
        $payload = (array) ($mutation['payload'] ?? []);
        $id = (string) ($payload['id'] ?? '');
        unset($payload['id'], $payload['sync_version'], $payload['updated_at'], $payload['created_at'], $payload['deleted_at']);

        $model = $this->registry->newModel($entity);
        if (! $model) {
            return MutationResult::conflict('unsupported_entity');
        }

        return match ($op) {
            'create' => $this->doCreate($model, $payload, $id),
            'update' => $this->doUpdate($model, $id, $payload),
            'delete' => $this->doDelete($model, $id),
            default => MutationResult::conflict('invalid_op'),
        };
    }

    private function doCreate(Model $model, array $payload, string $clientId): MutationResult
    {
        $payload = $this->filterFillable($model, $payload);
        if ($clientId !== '') {
            $payload['id'] = $clientId;
        }
        $row = $model->newQuery()->create($payload);

        return MutationResult::applied((string) $row->getKey(), (int) $row->sync_version);
    }

    private function doUpdate(Model $model, string $id, array $payload): MutationResult
    {
        $row = $model->newQuery()->find($id);
        if (! $row) {
            return MutationResult::conflict('not_found');
        }
        $row->fill($this->filterFillable($model, $payload));
        $row->save();

        return MutationResult::applied((string) $row->getKey(), (int) $row->sync_version);
    }

    private function doDelete(Model $model, string $id): MutationResult
    {
        $row = $model->newQuery()->find($id);
        if (! $row) {
            return MutationResult::conflict('not_found');
        }
        $row->delete();

        return MutationResult::applied((string) $row->getKey(), (int) $row->sync_version);
    }

    private function filterFillable(Model $model, array $payload): array
    {
        $allowed = $model->getFillable();
        if ($allowed === []) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip($allowed));
    }
}
