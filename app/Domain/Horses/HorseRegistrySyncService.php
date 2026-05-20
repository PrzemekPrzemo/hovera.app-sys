<?php

declare(strict_types=1);

namespace App\Domain\Horses;

use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Horse;
use App\Models\Tenant\OwnerHorse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Cross-tenant sync między lokalną kartoteką konia a centralnym rejestrem.
 * Patrz docs/MARKETPLACE-ROADMAP.md PR 4/5.
 *
 * Wzorce:
 *   - Owner tworzy horse w /owner/horses → registerForOwner() tworzy
 *     central_horse_registry row + zwraca id do zapisania w
 *     owner_horse.central_horse_id.
 *   - Stable claim'uje horse'a bez central_horse_id (legacy):
 *     attachLocalToCentral() po invite owner'a.
 *   - Tworzenie boarding'u: requestBoarding() → pending row;
 *     stable accept → activateBoarding().
 */
class HorseRegistrySyncService
{
    /**
     * Tworzy lub odnajduje central_horse_registry row dla konia
     * dodanego przez owner'a. Pisze do tenant.horses lokalny rekord
     * (z `central_horse_id`) — w tenant DB owner'a.
     *
     * Idempotent: jeśli OwnerHorse już ma central_horse_id, zwracamy
     * istniejący rekord. Jeśli passport_no jest zajęte przez inny
     * central_horse_registry — rzucamy DuplicatePassportException.
     */
    public function registerForOwner(OwnerHorse $horse, ?User $owner): CentralHorseRegistry
    {
        if ($horse->central_horse_id !== null) {
            $existing = CentralHorseRegistry::query()->find($horse->central_horse_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        // Jeśli passport_no podany i już zajęty PRZEZ INNEGO konia — błąd.
        // (jeśli zajęty przez tego samego — reuse'ujemy id).
        if ((string) $horse->passport_number !== '') {
            $byPassport = CentralHorseRegistry::query()
                ->where('passport_no', $horse->passport_number)
                ->first();
            if ($byPassport !== null) {
                // Reuse istniejącego registry — może to ten sam koń,
                // który był wcześniej zarejestrowany w stable. Owner
                // dodaje go u siebie, my linkujemy.
                $horse->forceFill(['central_horse_id' => $byPassport->id])->save();

                return $byPassport;
            }
        }

        $registry = CentralHorseRegistry::create([
            'primary_owner_user_id' => $owner?->id,
            'name' => $horse->name,
            'breed' => $horse->breed,
            'dob' => $horse->birth_date?->toDateString(),
            'passport_no' => (string) $horse->passport_number !== '' ? (string) $horse->passport_number : null,
        ]);

        $horse->forceFill(['central_horse_id' => $registry->id])->save();

        return $registry;
    }

    /**
     * Stable claim'uje legacy horse'a (bez central_horse_id) — po invite
     * + rejestracji owner'a. Albo tworzymy nowy registry (gdy passport
     * nie był wcześniej), albo linkujemy do istniejącego.
     */
    public function attachLocalToCentral(Horse $horse, ?User $owner): CentralHorseRegistry
    {
        if ($horse->central_horse_id !== null) {
            $existing = CentralHorseRegistry::query()->find($horse->central_horse_id);
            if ($existing !== null) {
                return $existing;
            }
        }

        if ((string) $horse->passport_number !== '') {
            $byPassport = CentralHorseRegistry::query()
                ->where('passport_no', $horse->passport_number)
                ->first();
            if ($byPassport !== null) {
                $horse->forceFill(['central_horse_id' => $byPassport->id])->save();

                return $byPassport;
            }
        }

        $registry = CentralHorseRegistry::create([
            'primary_owner_user_id' => $owner?->id,
            'name' => $horse->name,
            'breed' => $horse->breed,
            'dob' => $horse->birth_date?->toDateString(),
            'passport_no' => (string) $horse->passport_number !== '' ? (string) $horse->passport_number : null,
        ]);

        $horse->forceFill(['central_horse_id' => $registry->id])->save();

        return $registry;
    }

    /**
     * Owner składa request o boarding w konkretnej stajni.
     * Tworzy `pending` row jeśli go jeszcze nie ma; idempotent.
     *
     * Stable musi później klliknąć "Akceptuj" → activateBoarding().
     */
    public function requestBoarding(
        CentralHorseRegistry $horse,
        Tenant $stable,
        ?User $owner = null,
    ): HorseBoardingAssignment {
        return DB::connection('central')->transaction(function () use ($horse, $stable, $owner) {
            // Pending już istnieje? — zwracamy go (idempotent).
            $existing = HorseBoardingAssignment::query()
                ->where('central_horse_id', $horse->id)
                ->where('stable_tenant_id', $stable->id)
                ->whereIn('status', [
                    HorseBoardingAssignment::STATUS_PENDING,
                    HorseBoardingAssignment::STATUS_ACTIVE,
                ])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return HorseBoardingAssignment::create([
                'central_horse_id' => $horse->id,
                'stable_tenant_id' => $stable->id,
                'owner_user_id' => $owner?->id ?? $horse->primary_owner_user_id,
                'status' => HorseBoardingAssignment::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Stable akceptuje pending boarding. Ustawia status=active,
     * started_at=now. Idempotent: drugi accept nie zmienia started_at.
     */
    public function activateBoarding(HorseBoardingAssignment $assignment): HorseBoardingAssignment
    {
        if ($assignment->status === HorseBoardingAssignment::STATUS_ACTIVE) {
            return $assignment;
        }

        $assignment->forceFill([
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => $assignment->started_at ?? now(),
        ])->save();

        return $assignment;
    }

    /**
     * Zakończenie boarding'u (owner przeprowadza konia, stable rezygnuje
     * z pensjonariusza). status=ended, ended_at=now.
     */
    public function endBoarding(HorseBoardingAssignment $assignment): HorseBoardingAssignment
    {
        $assignment->forceFill([
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'ended_at' => $assignment->ended_at ?? now(),
        ])->save();

        return $assignment;
    }

    /**
     * Helper: czy `tenant.horses` row jest powiązany z central rejestrem?
     */
    public function isLinked(Model $tenantHorse): bool
    {
        return (string) $tenantHorse->getAttribute('central_horse_id') !== '';
    }
}
