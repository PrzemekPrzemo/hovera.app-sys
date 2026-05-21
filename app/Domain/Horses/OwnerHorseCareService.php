<?php

declare(strict_types=1);

namespace App\Domain\Horses;

use App\Domain\Horses\Snapshots\HorseFeedingPlanItemSnapshot;
use App\Domain\Horses\Snapshots\HorseWeightSnapshot;
use App\Models\Central\Tenant;
use App\Models\Tenant\Horse;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;

/**
 * Cross-tenant reader dla owner-side widoków wagi + planu żywienia.
 * Live query (jak StableHorseSnapshotService) bo dane zmieniają się
 * codziennie — snapshot byłby stale.
 *
 * Trend wagi: liczymy `deltaKg` per pomiar wewnątrz execute() bo musimy
 * mieć poprzedni rekord w pamięci. Sort: ASC po measured_at (rosnąco)
 * żeby delta porównywała "do poprzedniego" — przy renderze blade odwraca
 * kolejność dla "najnowsze na górze".
 */
class OwnerHorseCareService
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * @return list<HorseWeightSnapshot>
     */
    public function weightsForHorse(string $centralHorseId, Tenant $stableTenant): array
    {
        return $this->tenants->execute($stableTenant, function () use ($centralHorseId): array {
            $horse = Horse::query()
                ->where('central_horse_id', $centralHorseId)
                ->with(['weightMeasurements' => fn ($q) => $q->reorder('measured_at', 'asc')])
                ->first();

            if ($horse === null) {
                return [];
            }

            $snapshots = [];
            $previousWeight = null;
            foreach ($horse->weightMeasurements as $m) {
                $current = (float) $m->weight_kg;
                $delta = $previousWeight !== null ? $current - $previousWeight : null;
                $previousWeight = $current;

                $snapshots[] = new HorseWeightSnapshot(
                    id: (string) $m->id,
                    measuredAt: Carbon::parse((string) $m->measured_at),
                    weightKg: $current,
                    girthCm: $m->girth_cm !== null ? (float) $m->girth_cm : null,
                    notes: $m->notes !== null ? (string) $m->notes : null,
                    deltaKg: $delta,
                );
            }

            return $snapshots;
        });
    }

    /**
     * @return list<HorseFeedingPlanItemSnapshot>
     */
    public function feedingPlanForHorse(string $centralHorseId, Tenant $stableTenant): array
    {
        return $this->tenants->execute($stableTenant, function () use ($centralHorseId): array {
            $horse = Horse::query()
                ->where('central_horse_id', $centralHorseId)
                ->with(['feedingPlanItems' => fn ($q) => $q->active()])
                ->first();

            if ($horse === null) {
                return [];
            }

            $snapshots = [];
            foreach ($horse->feedingPlanItems as $item) {
                $snapshots[] = new HorseFeedingPlanItemSnapshot(
                    id: (string) $item->id,
                    meal: $item->meal->value,
                    feedType: (string) $item->feed_type,
                    amountFormatted: $item->amountFormatted(),
                    notes: $item->notes !== null ? (string) $item->notes : null,
                    sortOrder: (int) $item->sort_order,
                );
            }

            return $snapshots;
        });
    }
}
