<?php

declare(strict_types=1);

namespace App\Services\Sync\Handlers;

use App\Http\Resources\V1\CalendarEntryResource;
use App\Models\Central\TenantMembership;
use App\Models\Tenant\CalendarEntry;
use Carbon\Carbon;

/**
 * Server-authoritative for booking overlaps. If two clients race to book
 * the same arena/instructor/horse, the second one is rejected and the
 * current server state is returned so the mobile UI can show the conflict
 * to the user.
 */
class CalendarEntryMutationHandler implements MutationHandler
{
    public function __construct(private readonly GenericMutationHandler $generic) {}

    public function handle(string $entity, string $op, array $mutation, TenantMembership $membership): MutationResult
    {
        $payload = (array) ($mutation['payload'] ?? []);

        if (in_array($op, ['create', 'update'], true) && isset($payload['starts_at'], $payload['ends_at'])) {
            $startsAt = Carbon::parse((string) $payload['starts_at']);
            $endsAt = Carbon::parse((string) $payload['ends_at']);
            $excludeId = $op === 'update' ? (string) ($payload['id'] ?? '') : null;

            $conflict = $this->findOverlap($payload, $startsAt, $endsAt, $excludeId);
            if ($conflict) {
                return MutationResult::conflict(
                    'booking_overlap',
                    (new CalendarEntryResource($conflict))->resolve(request()),
                    ['starts_at' => ['Overlaps existing entry #'.$conflict->getKey()]]
                );
            }
        }

        return $this->generic->handle($entity, $op, $mutation, $membership);
    }

    private function findOverlap(array $payload, Carbon $startsAt, Carbon $endsAt, ?string $excludeId): ?CalendarEntry
    {
        $query = CalendarEntry::query()
            ->scopes(['blockingResources'])
            ->where(function ($q) use ($startsAt, $endsAt) {
                $q->where('starts_at', '<', $endsAt)
                  ->where('ends_at', '>', $startsAt);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->where(function ($q) use ($payload) {
            $or = false;
            if (! empty($payload['arena_id'])) {
                $q->where('arena_id', $payload['arena_id']);
                $or = true;
            }
            if (! empty($payload['instructor_id'])) {
                $q->orWhere('instructor_id', $payload['instructor_id']);
                $or = true;
            }
            if (! empty($payload['horse_id'])) {
                $q->orWhere('horse_id', $payload['horse_id']);
                $or = true;
            }
            if (! $or) {
                // No resource specified — cannot overlap.
                $q->whereRaw('1=0');
            }
        });

        return $query->first();
    }
}
