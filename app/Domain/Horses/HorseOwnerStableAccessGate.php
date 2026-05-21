<?php

declare(strict_types=1);

namespace App\Domain\Horses;

use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Gate dla cross-tenant dostępu owner'a do danych konia goszczącego w
 * stajni. Wszystkie nowe endpointy / Filament page'e które czytają lub
 * piszą do stable DB w kontekście konia muszą najpierw przepuścić się
 * przez ten gate.
 *
 * Reguły:
 *   1. User musi być primary_owner_user_id w `central_horse_registry` —
 *      tylko właściciel widzi swojego konia. Inne osoby (np. członek
 *      rodziny) docelowo dostaną własną relację, w Fazie 1 = tylko owner.
 *   2. Musi istnieć `horse_boarding_assignments` row ze status='active'
 *      łączący tego konia z tenant stajni. Statusy pending/ended/disputed
 *      = brak dostępu (per roadmap, ended dostanie read-only read access
 *      w późniejszej fazie).
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Architektura — Gate".
 */
class HorseOwnerStableAccessGate
{
    /**
     * Wymusza autoryzację — rzuca AuthorizationException jeśli user nie
     * jest uprawniony. Zwraca aktywną assignment'a (caller potrzebuje
     * stable_tenant_id z niej).
     *
     * Owner musi być primary_owner w CentralHorseRegistry I mieć active
     * HorseBoardingAssignment z stajnią dla tego konia.
     */
    public function authorize(User $owner, string $centralHorseId): HorseBoardingAssignment
    {
        $assignment = $this->tryAuthorize($owner, $centralHorseId);

        if ($assignment === null) {
            throw new AuthorizationException(
                __('owner/horse_detail.access.denied')
            );
        }

        return $assignment;
    }

    /**
     * Wariant bez wyjątku — dla `canAccess()` w Filament'cie i innych
     * miejsc gdzie chcemy soft-deny zamiast 403 explosion.
     */
    public function tryAuthorize(User $owner, string $centralHorseId): ?HorseBoardingAssignment
    {
        // Krok 1: właściciel musi być primary_owner w central registry.
        // Defensive: nie ufamy że central_horse_id z URL jest prawdziwy,
        // sprawdzamy w DB.
        $registry = CentralHorseRegistry::query()
            ->where('id', $centralHorseId)
            ->where('primary_owner_user_id', $owner->id)
            ->first();

        if ($registry === null) {
            return null;
        }

        // Krok 2: active boarding assignment. W normalnych warunkach max 1
        // (koń jest w jednej stajni). Multiple active = dispute, bierzemy
        // pierwszy ale upstream powinien rozstrzygnąć.
        return HorseBoardingAssignment::query()
            ->where('central_horse_id', $centralHorseId)
            ->where('owner_user_id', $owner->id)
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Sprawdza czy owner ma JAKIEKOLWIEK active boarding'i — używane w
     * nawigacji panelu (czy pokazywać sekcję "boarding view"). Tańsza
     * sprawdzka niż per-horse authorize().
     */
    public function hasAnyActiveBoarding(User $owner): bool
    {
        return HorseBoardingAssignment::query()
            ->where('owner_user_id', $owner->id)
            ->where('status', HorseBoardingAssignment::STATUS_ACTIVE)
            ->exists();
    }
}
