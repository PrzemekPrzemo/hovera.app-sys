<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\Central\TenantMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Reads config/sync.php and gives the rest of the sync layer a single
 * place to ask "is this entity syncable?", "can role X mutate it?",
 * "what conflict policy applies?".
 */
class SyncRegistry
{
    public const CONFLICT_LWW = 'lww';                       // last-write-wins per field
    public const CONFLICT_APPEND_ONLY = 'append_only';       // create only, no overwrite
    public const CONFLICT_SERVER_AUTHORITATIVE = 'server';   // overlap/stock checks reject
    public const CONFLICT_SERVER_ONLY = 'server_only';       // mobile cannot mutate

    /** @return array<string,array<string,mixed>> */
    public function entities(): array
    {
        return (array) config('sync.entities', []);
    }

    public function entity(string $name): ?array
    {
        return $this->entities()[$name] ?? null;
    }

    public function modelClass(string $entity): ?string
    {
        $cfg = $this->entity($entity);

        return $cfg['model'] ?? null;
    }

    public function newModel(string $entity): ?Model
    {
        $class = $this->modelClass($entity);
        if (! $class || ! class_exists($class)) {
            Log::warning('sync.unknown_model', ['entity' => $entity, 'class' => $class]);

            return null;
        }

        /** @var Model $instance */
        $instance = new $class;

        return $instance;
    }

    public function tableFor(string $entity): ?string
    {
        $model = $this->newModel($entity);

        return $model?->getTable();
    }

    public function conflictPolicy(string $entity): string
    {
        return $this->entity($entity)['conflict'] ?? self::CONFLICT_LWW;
    }

    public function canMutate(string $entity, TenantMembership $membership): bool
    {
        $cfg = $this->entity($entity);
        if (! $cfg || ! array_key_exists('mutate_roles', $cfg) || $cfg['mutate_roles'] === null) {
            return false;
        }

        $roles = $cfg['mutate_roles'];
        if ($roles === 'any') {
            return true;
        }

        return in_array((string) $membership->role, (array) $roles, true);
    }

    public function mutationHandler(string $entity): string
    {
        return $this->entity($entity)['mutation_handler']
            ?? \App\Services\Sync\Handlers\GenericMutationHandler::class;
    }

    public function resourceClass(string $entity): ?string
    {
        return $this->entity($entity)['resource'] ?? null;
    }
}
