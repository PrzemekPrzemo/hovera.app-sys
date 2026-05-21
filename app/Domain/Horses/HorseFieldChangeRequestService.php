<?php

declare(strict_types=1);

namespace App\Domain\Horses;

use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseFieldChangeRequest;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Horse;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Faza 6 PR 6.3 — orchestracja approval flow dla kluczowych pól konia.
 *
 *   propose(stable, horse, field, oldValue, newValue) — wywoływane z
 *     observer'a po `updated` event. Tworzy pending request albo
 *     aktualizuje istniejący (gdy stable robi kilka zmian zanim
 *     owner zdecyduje).
 *
 *   accept(owner, request) — markuje accepted. Change już jest w
 *     Horse rekordzie (observer dopuścił save'a), więc no-op poza
 *     zaznaczeniem statusu.
 *
 *   reject(owner, request, reason) — markuje rejected I REVERT'uje
 *     Horse rekord do old_value przez TenantManager::execute (stable
 *     tenant context).
 *
 * Tylko `name`, `passport_number`, `microchip` są w tym flow (per
 * Q4 z docs/OWNER-STABLE-ROADMAP.md — minimalne tarcie, tylko pola
 * realnie definiujące tożsamość konia).
 */
class HorseFieldChangeRequestService
{
    public function __construct(
        private readonly TenantManager $tenants,
    ) {}

    /**
     * Tworzy lub aktualizuje pending request. Idempotent dla
     * (central_horse_id, field) — gdy istnieje pending, nadpisujemy
     * new_value (stable może zmieniać kilka razy zanim owner zatwierdzi).
     * Historic accepted/rejected zostają nietknięte.
     */
    public function propose(
        Tenant $stableTenant,
        string $centralHorseId,
        string $field,
        ?string $oldValue,
        ?string $newValue,
        ?string $proposedByUserId = null,
    ): HorseFieldChangeRequest {
        $this->validateField($field);

        // Czy istnieje pending dla tego horse + field?
        $existing = HorseFieldChangeRequest::query()
            ->where('central_horse_id', $centralHorseId)
            ->where('field', $field)
            ->where('status', HorseFieldChangeRequest::STATUS_PENDING)
            ->first();

        if ($existing !== null) {
            // Aktualizujemy new_value, zachowujemy oryginalny old_value
            // (z pierwszego proposal) żeby reject mógł revert na true
            // pre-change state.
            $existing->forceFill([
                'new_value' => $newValue,
                'proposed_at' => now(),
                'proposed_by_user_id' => $proposedByUserId ?? $existing->proposed_by_user_id,
            ])->save();

            return $existing;
        }

        return HorseFieldChangeRequest::create([
            'central_horse_id' => $centralHorseId,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'proposed_by_tenant_id' => $stableTenant->id,
            'proposed_by_user_id' => $proposedByUserId,
            'status' => HorseFieldChangeRequest::STATUS_PENDING,
            'proposed_at' => now(),
        ]);
    }

    /**
     * Owner akceptuje — markujemy accepted, brak revert'u (zmiana już
     * jest w Horse).
     *
     * @throws AuthorizationException gdy request nie należy do tego ownera
     */
    public function accept(User $owner, HorseFieldChangeRequest $request): HorseFieldChangeRequest
    {
        $this->ensureOwnerCanDecide($owner, $request);
        if (! $request->isPending()) {
            return $request;
        }

        $request->forceFill([
            'status' => HorseFieldChangeRequest::STATUS_ACCEPTED,
            'decided_at' => now(),
            'decided_by_user_id' => $owner->id,
        ])->save();

        return $request;
    }

    /**
     * Owner odrzuca — markujemy rejected I revert'ujemy Horse rekord
     * w stable DB do old_value przez TenantManager::execute.
     *
     * @throws AuthorizationException
     * @throws RuntimeException gdy stable tenant lub horse nie istnieje
     */
    public function reject(User $owner, HorseFieldChangeRequest $request, ?string $reason = null): HorseFieldChangeRequest
    {
        $this->ensureOwnerCanDecide($owner, $request);
        if (! $request->isPending()) {
            return $request;
        }

        $stableTenant = Tenant::query()->find($request->proposed_by_tenant_id);
        if ($stableTenant === null) {
            throw new RuntimeException("Stable tenant {$request->proposed_by_tenant_id} not found");
        }

        // Revert w stable DB.
        $this->tenants->execute($stableTenant, function () use ($request): void {
            $horse = Horse::query()
                ->where('central_horse_id', $request->central_horse_id)
                ->first();
            if ($horse === null) {
                // Sync rift — log + skip revert. Request markowany
                // jako rejected i tak (status na central jest source
                // of truth dla owner UI).
                return;
            }
            $horse->forceFill([
                $request->field => $request->old_value,
            ])->save();
        });

        $request->forceFill([
            'status' => HorseFieldChangeRequest::STATUS_REJECTED,
            'decided_at' => now(),
            'decided_by_user_id' => $owner->id,
            'reject_reason' => $reason,
        ])->save();

        return $request;
    }

    /**
     * Lista pending requests dla ownera (across all his horses).
     *
     * @return Collection<int, HorseFieldChangeRequest>
     */
    public function pendingForOwner(User $owner): Collection
    {
        // Pending request dla konia którego owner jest primary_owner.
        // JOIN z central_horse_registry.
        return HorseFieldChangeRequest::query()
            ->whereIn('central_horse_id', function ($q) use ($owner) {
                $q->select('id')
                    ->from('central_horse_registry')
                    ->where('primary_owner_user_id', $owner->id);
            })
            ->where('status', HorseFieldChangeRequest::STATUS_PENDING)
            ->orderByDesc('proposed_at')
            ->get();
    }

    private function ensureOwnerCanDecide(User $owner, HorseFieldChangeRequest $request): void
    {
        $exists = CentralHorseRegistry::query()
            ->where('id', $request->central_horse_id)
            ->where('primary_owner_user_id', $owner->id)
            ->exists();
        if (! $exists) {
            throw new AuthorizationException(__('owner/change_requests.access.not_owner'));
        }
    }

    private function validateField(string $field): void
    {
        if (! in_array($field, HorseFieldChangeRequest::ALL_FIELDS, true)) {
            throw new RuntimeException("Field {$field} nie podlega approval flow");
        }
    }
}
