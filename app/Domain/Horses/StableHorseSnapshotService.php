<?php

declare(strict_types=1);

namespace App\Domain\Horses;

use App\Domain\Horses\Snapshots\BoardingServiceSnapshot;
use App\Domain\Horses\Snapshots\BoxAssignmentSnapshot;
use App\Domain\Horses\Snapshots\HorseSnapshot;
use App\Models\Central\Tenant;
use App\Models\Tenant\Horse;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Cross-tenant reader — wczytuje pełne dane konia ze stable DB i mapuje
 * do `HorseSnapshot` DTO. Używa `TenantManager::execute` do tymczasowego
 * przepięcia connection 'tenant' na DB stajni, po czym restore'uje
 * poprzedni stan.
 *
 * Owner panel pyta tu o snapshot przy mount'cie HorseDetail page'a; w
 * przyszłych fazach (timeline, invoices, messages) podobne service'y
 * (HorseTimelineService, OwnerInvoiceFeedService) będą analogicznie
 * używać `execute()` switcha.
 *
 * UWAGA: zwracamy DTO, nie Eloquent — po wyjściu z `execute()` connection
 * 'tenant' wskazuje na owner DB i query'e wybuchłyby na cudzym schemacie.
 * DTO są self-contained.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Architektura".
 */
class StableHorseSnapshotService
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Snapshot konia widzianego przez stajnię — wszystkie pola Horse +
     * aktualny box + aktywne boarding services.
     *
     * @throws RuntimeException jeśli konia nie ma w stable DB (sync rift)
     */
    public function forCentralHorse(string $centralHorseId, Tenant $stableTenant): HorseSnapshot
    {
        return $this->tenants->execute($stableTenant, function () use ($centralHorseId, $stableTenant): HorseSnapshot {
            // Eager load relacji potrzebnych do snapshot'u — w jednym
            // round-tripie do DB. boxAssignments() już ma orderByDesc'em
            // assigned_at, pierwszy z whereNull('vacated_at') = aktualny.
            $horse = Horse::query()
                ->where('central_horse_id', $centralHorseId)
                ->with([
                    'boxAssignments' => fn ($q) => $q->whereNull('vacated_at')->with('box.building'),
                    'boardingServices' => fn ($q) => $q->withPivot(['price_override_cents', 'quantity', 'starts_at', 'ends_at', 'notes']),
                ])
                ->first();

            if ($horse === null) {
                // Może się zdarzyć jeśli stable usunął rekord ale assignment
                // ciągle 'active' (sync rift). Rzucamy żeby caller zalogował
                // i pokazał user'owi że trzeba odświeżyć.
                throw new RuntimeException(
                    "Horse central_id={$centralHorseId} nie istnieje w stable DB (tenant={$stableTenant->id})"
                );
            }

            return $this->mapToSnapshot($horse, $stableTenant);
        });
    }

    /**
     * Mapowanie Horse → HorseSnapshot. Wewnątrz execute() więc Eloquent
     * jeszcze działa; po return wszystko musi być zwykłymi typami.
     */
    private function mapToSnapshot(Horse $horse, Tenant $stableTenant): HorseSnapshot
    {
        $currentBoxAssignment = $horse->boxAssignments->first();
        $currentBox = $currentBoxAssignment !== null && $currentBoxAssignment->box !== null
            ? new BoxAssignmentSnapshot(
                boxId: (string) $currentBoxAssignment->box->id,
                boxName: (string) $currentBoxAssignment->box->name,
                buildingName: $currentBoxAssignment->box->building?->name,
                monthlyRateCents: $currentBoxAssignment->box->monthly_rate_cents !== null
                    ? (int) $currentBoxAssignment->box->monthly_rate_cents
                    : null,
                assignedAt: $currentBoxAssignment->assigned_at,
            )
            : null;

        $boardingServices = [];
        foreach ($horse->boardingServices as $service) {
            $pivot = $service->pivot;
            $boardingServices[] = new BoardingServiceSnapshot(
                serviceId: (string) $service->id,
                name: (string) $service->name,
                description: $service->description !== null ? (string) $service->description : null,
                unit: (string) $service->unit,
                frequency: $service->frequency->value,
                effectivePriceCents: $pivot->price_override_cents !== null
                    ? (int) $pivot->price_override_cents
                    : (int) $service->price_cents,
                quantity: (float) ($pivot->quantity ?? 1),
                currency: 'PLN', // Faza 1: hard-coded; pull z stable settings w przyszłości
                startsAt: $pivot->starts_at !== null
                    ? Carbon::parse((string) $pivot->starts_at)
                    : null,
                endsAt: $pivot->ends_at !== null
                    ? Carbon::parse((string) $pivot->ends_at)
                    : null,
                notes: $pivot->notes !== null ? (string) $pivot->notes : null,
            );
        }

        return new HorseSnapshot(
            centralHorseId: (string) $horse->central_horse_id,
            stableHorseId: (string) $horse->id,
            stableTenantId: (string) $stableTenant->id,
            name: (string) $horse->name,
            breed: $horse->breed,
            sex: $horse->sex,
            color: $horse->color,
            birthDate: $horse->birth_date,
            passportNumber: $horse->passport_number,
            microchip: $horse->microchip,
            ueln: $horse->ueln,
            coverImagePath: $horse->cover_image_path,
            notes: $horse->notes,
            currentBox: $currentBox,
            boardingServices: $boardingServices,
            estimatedMonthlyCostCents: $horse->estimatedMonthlyCostCents(),
        );
    }
}
