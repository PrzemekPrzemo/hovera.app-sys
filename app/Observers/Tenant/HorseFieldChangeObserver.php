<?php

declare(strict_types=1);

namespace App\Observers\Tenant;

use App\Domain\Horses\HorseFieldChangeRequestService;
use App\Models\Central\HorseFieldChangeRequest;
use App\Models\Tenant\Horse;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Faza 6 PR 6.3 — wykrywa zmiany kluczowych pól konia (name /
 * passport_number / microchip) w stable Horse rekordzie i propaguje
 * jako pending request w central HorseFieldChangeRequest. Owner widzi
 * w panel'u i może accept/reject.
 *
 * Dlaczego po `updated` a nie `updating`:
 *   - Pozwalamy save'owi przejść (mniej inwazyjne UX dla stable).
 *   - getOriginal() ma starą wartość w `updated` event (bo Eloquent
 *     trzyma snapshot przed save).
 *   - Reject → revert robi service w cross-tenant context.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 6 PR 6.3" + Q4.
 */
class HorseFieldChangeObserver
{
    public function updated(Horse $horse): void
    {
        $centralHorseId = $horse->central_horse_id;
        if ($centralHorseId === null) {
            return; // legacy horse bez central registry — pomijamy
        }

        $changedFields = [];
        foreach (HorseFieldChangeRequest::ALL_FIELDS as $field) {
            if ($horse->wasChanged($field)) {
                $changedFields[$field] = [
                    'old' => $horse->getOriginal($field),
                    'new' => $horse->getAttribute($field),
                ];
            }
        }

        if ($changedFields === []) {
            return;
        }

        $stableTenant = app(TenantManager::class)->current();
        if ($stableTenant === null) {
            // Update poza tenant context (rzadko — np. command artisan).
            // Pomijamy żeby nie crashować, ale logujemy.
            Log::warning(
                'Horse field change without tenant context — skipping approval flow',
                ['horse_id' => $horse->id, 'fields' => array_keys($changedFields)],
            );

            return;
        }

        $service = app(HorseFieldChangeRequestService::class);
        $userId = Auth::id();

        foreach ($changedFields as $field => $values) {
            $service->propose(
                stableTenant: $stableTenant,
                centralHorseId: (string) $centralHorseId,
                field: $field,
                oldValue: $values['old'] !== null ? (string) $values['old'] : null,
                newValue: $values['new'] !== null ? (string) $values['new'] : null,
                proposedByUserId: $userId !== null ? (string) $userId : null,
            );
        }
    }
}
