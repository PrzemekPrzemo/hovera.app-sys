<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pulls a single ordered batch of changes for a list of entities since a
 * cursor. Soft-deleted rows surface as `op:delete` tombstones.
 */
class ChangeFeedService
{
    public function __construct(private readonly SyncRegistry $registry) {}

    /**
     * @param  list<string>  $entities
     * @return array{cursor:string,has_more:bool,changes:array<int,array<string,mixed>>}
     */
    public function pull(int $sinceVersion, array $entities, int $limit): array
    {
        $entities = array_values(array_filter(array_unique($entities)));
        if ($entities === []) {
            $entities = array_keys($this->registry->entities());
        }

        $changes = [];
        $maxVersionInBatch = $sinceVersion;

        foreach ($entities as $name) {
            $cfg = $this->registry->entity($name);
            if (! $cfg) {
                continue;
            }

            $model = $this->registry->newModel($name);
            if (! $model) {
                continue;
            }

            $query = $model->newQuery();
            // Include soft-deleted to emit tombstones.
            if (in_array(SoftDeletes::class, class_uses_recursive($model::class), true)) {
                $query = $query->withTrashed();
            }

            $rows = $query->where('sync_version', '>', $sinceVersion)
                ->orderBy('sync_version')
                ->limit($limit + 1)
                ->get();

            foreach ($rows as $row) {
                $isDeleted = method_exists($row, 'trashed') && $row->trashed();
                $changes[] = [
                    'entity' => $name,
                    'op' => $isDeleted ? 'delete' : 'upsert',
                    'id' => (string) $row->getKey(),
                    'sync_version' => (int) $row->sync_version,
                    'updated_at' => optional($row->updated_at)->toIso8601String(),
                    'payload' => $isDeleted ? null : $this->serialize($name, $row),
                ];
                $maxVersionInBatch = max($maxVersionInBatch, (int) $row->sync_version);
            }
        }

        // Order across all entities by sync_version then trim to limit.
        usort($changes, fn ($a, $b) => $a['sync_version'] <=> $b['sync_version']);
        $hasMore = count($changes) > $limit;
        $changes = array_slice($changes, 0, $limit);

        $newCursor = $changes === [] ? $sinceVersion : (int) end($changes)['sync_version'];

        return [
            'cursor' => CursorCodec::encode((int) request()->attributes->get('tenant')?->id ?: 0, $newCursor),
            'has_more' => $hasMore,
            'changes' => $changes,
        ];
    }

    private function serialize(string $entity, mixed $row): array
    {
        $resource = $this->registry->resourceClass($entity);
        if ($resource && class_exists($resource)) {
            return (new $resource($row))->resolve(request());
        }

        // Fallback: model attributes as-is.
        return $row->attributesToArray();
    }
}
