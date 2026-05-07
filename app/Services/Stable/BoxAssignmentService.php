<?php

declare(strict_types=1);

namespace App\Services\Stable;

use App\Models\Tenant\Box;
use App\Models\Tenant\BoxAssignment;
use App\Models\Tenant\Horse;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Atomic operations on Horse ↔ Box relation. Always go through this
 * service when moving a horse — robi 3 rzeczy w jednej transakcji:
 *
 *   1. Zamyka poprzedni active BoxAssignment (vacated_at = now)
 *   2. Tworzy nowy BoxAssignment (assigned_at = now)
 *   3. Aktualizuje horse.box_id
 *
 * Bez tego flow można rozjechać dane (horse w boxie A wg horses.box_id,
 * ale historia mówi że jest w boxie B).
 */
class BoxAssignmentService
{
    public function __construct(
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * Przenieś konia do nowego boxa. Gdy `null` — wypisz z aktualnego boxa
     * bez przypisywania nowego (np. wyjazd na zawody, sprzedaż).
     */
    public function assign(
        Horse $horse,
        ?Box $newBox,
        ?string $reason = null,
        ?string $assignedByUserId = null,
    ): ?BoxAssignment {
        return DB::connection('tenant')->transaction(function () use ($horse, $newBox, $reason, $assignedByUserId) {
            $previous = $horse->boxAssignments()->whereNull('vacated_at')->first();

            // No-op: koń już jest w tym samym boksie
            if ($previous && $newBox && $previous->box_id === $newBox->id) {
                return $previous;
            }

            // Zamknij poprzednie active assignment
            if ($previous) {
                $previous->forceFill([
                    'vacated_at' => now(),
                    'reason' => $reason ?? $previous->reason,
                ])->save();
            }

            // Otwórz nowe (jeśli przypisujemy nowy box)
            $new = null;
            if ($newBox) {
                $new = BoxAssignment::create([
                    'id' => (string) Str::ulid(),
                    'horse_id' => $horse->id,
                    'box_id' => $newBox->id,
                    'assigned_at' => now(),
                    'reason' => $reason,
                    'assigned_by_user_id' => $assignedByUserId,
                ]);
            }

            // Update horse.box_id (denormalizacja dla szybkiego query)
            $horse->forceFill(['box_id' => $newBox?->id])->save();

            $this->audit->record(
                $newBox ? 'box.assigned' : 'box.vacated',
                'Horse',
                (string) $horse->id,
                [
                    'box_id' => $newBox?->id,
                    'box_name' => $newBox?->name,
                    'previous_box_id' => $previous?->box_id,
                    'reason' => $reason,
                ],
            );

            return $new;
        });
    }
}
