<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tenant\Box;
use App\Models\Tenant\BoxAssignment;
use App\Models\Tenant\Horse;
use Illuminate\Support\Str;

/**
 * Pilnuje że historia BoxAssignment zostaje zsynchronizowana z polem
 * horses.box_id. Zwykły flow przez BoxAssignmentService::assign() obsługuje
 * to atomic-w-transakcji, ale jeśli ktoś zmieni box_id przez Eloquent
 * (Filament resource form save) to obserwer dorzuca brakujący wpis.
 *
 * Idempotentny — gdy historia już ma matching active assignment,
 * nic nie robi.
 */
class HorseObserver
{
    public function created(Horse $horse): void
    {
        if ($horse->box_id) {
            $this->openAssignment($horse, $horse->box_id);
        }
    }

    public function updated(Horse $horse): void
    {
        if (! $horse->wasChanged('box_id')) {
            return;
        }

        $original = (string) ($horse->getOriginal('box_id') ?? '');
        $current = (string) ($horse->box_id ?? '');

        // Zamknij ostatnie active assignment do poprzedniego boxa
        if ($original !== '') {
            BoxAssignment::query()
                ->where('horse_id', $horse->id)
                ->where('box_id', $original)
                ->whereNull('vacated_at')
                ->update(['vacated_at' => now()]);
        }

        // Otwórz nowe gdy jest nowy box
        if ($current !== '') {
            // Jeśli już jest active assignment dla current box (np.
            // BoxAssignmentService::assign() sam to dodał) — nic nie rób
            $exists = BoxAssignment::query()
                ->where('horse_id', $horse->id)
                ->where('box_id', $current)
                ->whereNull('vacated_at')
                ->exists();
            if (! $exists) {
                $this->openAssignment($horse, $current);
            }
        }
    }

    private function openAssignment(Horse $horse, string $boxId): void
    {
        // Sanity check — nie tworzymy nieprawidłowych referencji
        if (! Box::query()->whereKey($boxId)->exists()) {
            return;
        }

        BoxAssignment::create([
            'id' => (string) Str::ulid(),
            'horse_id' => $horse->id,
            'box_id' => $boxId,
            'assigned_at' => now(),
        ]);
    }
}
