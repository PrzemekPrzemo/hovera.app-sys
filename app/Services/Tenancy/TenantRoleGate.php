<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Central\TenantMembership;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for "what role does the logged-in user have in
 * the currently active tenant". Used by Filament resources / pages to
 * gate `canAccess()` without rewriting the same TenantMembership lookup
 * everywhere.
 *
 * Roles per tenant type:
 *
 *   STABLE tenants (matching `TeamMemberResource::roleOptions()` dla stables):
 *   - owner      — full access, only the owner can delete the tenant
 *   - admin      — full access except tenant deletion
 *   - manager    — operations + finance, no tenant settings, no team
 *   - instructor — calendar, bookings, own horses, clients
 *   - employee   — activity log, calendar read-only, horses read-only
 *   - vet        — health records, horses, calendar (own visits), specialists
 *   - viewer     — read-only across operations
 *
 *   TRANSPORTER tenants:
 *   - owner      — full access, only the owner can delete the tenant
 *   - admin      — full access except tenant deletion
 *   - operator   — oferty + kalkulacje + faktury + przypisywanie kierowców
 *                  (bez administracji firmy — billing, settings, team mgmt
 *                  zarezerwowane dla owner/admin)
 *   - driver     — TYLKO swoje trasy + kalendarz + swoje dokumenty
 *                  (kierowca nie widzi cudzych ofert, leadów, faktur)
 *
 *   Legacy: niektóre stare transporter tenants mogą mieć `manager` role
 *   (zanim wprowadziliśmy `operator`). TRANSPORT_OPERATORS zawiera
 *   `manager` dla backward compat — w UI nie pokazujemy go już jako opcji.
 *
 * Returns null when there's no active tenant or no auth user — callers
 * use that to deny access defensively.
 */
class TenantRoleGate
{
    /** Common role groupings used by canAccess() across the app. */
    public const FULL_ADMINS = ['owner', 'admin'];

    public const FULL_ADMINS_AND_MANAGERS = ['owner', 'admin', 'manager'];

    /** Horses, health, calendar — vet sees these to review patients. */
    public const HORSE_AND_CARE_STAFF = ['owner', 'admin', 'manager', 'instructor', 'employee', 'vet', 'viewer'];

    /** Boxes, buildings, arenas — no vet, no isolated employee. */
    public const STABLE_OPS_STAFF = ['owner', 'admin', 'manager', 'instructor', 'viewer'];

    /**
     * Recurring calendar entries — STABLE_OPS_STAFF + `vet`.
     * Wet musi móc planować długoterminowe sesje rehabilitacyjne post-injury
     * (np. 6 tygodni codziennej longy w określonej arenie) bez konieczności
     * proszenia managera o utworzenie cyklu. Single-use entries pozostają
     * dostępne dla wszystkich z HORSE_AND_CARE_STAFF.
     */
    public const RECURRING_CALENDAR_STAFF = ['owner', 'admin', 'manager', 'instructor', 'vet', 'viewer'];

    /** Specialist directory + treatment templates — vet uses both. */
    public const SPECIALIST_STAFF = ['owner', 'admin', 'manager', 'vet', 'viewer'];

    /**
     * Tworzenie/edycja klinicznych wpisów (HealthRecord) — tylko medyczna
     * autoryzacja. Instructor/employee NIE mogą tworzyć diagnoz, leczeń,
     * szczepień — to data quality risk (instruktor wpisuje "szczepiono"
     * z pamięci zamiast vet'a faktycznie podpisanego). Read access dla
     * całego HORSE_AND_CARE_STAFF pozostaje przez allowedRoles() — chcemy
     * żeby instruktor widział historię zdrowotną przed lekcją.
     */
    public const CLINICAL_WRITE_STAFF = ['owner', 'admin', 'manager', 'vet'];

    /** Feed inventory — employees record daily issuance. */
    public const FEED_STAFF = ['owner', 'admin', 'manager', 'employee', 'viewer'];

    /** Invoices, passes, monthly reports — viewer sees read-only. */
    public const FINANCE_STAFF = ['owner', 'admin', 'manager', 'viewer'];

    /**
     * Transport panel: kalkulacja / oferty / leady / faktury / pojazdy / przypisanie kierowców.
     * `manager` w środku dla backward compat (old transporter tenants stworzone
     * przed wprowadzeniem `operator` mogą wciąż mieć ten role).
     */
    public const TRANSPORT_OPERATORS = ['owner', 'admin', 'operator', 'manager'];

    /**
     * Wszyscy członkowie team'u transportera włącznie z kierowcami — używane dla
     * widoków typu „company directory" gdzie kierowca też powinien widzieć
     * np. listę kolegów.
     */
    public const TRANSPORT_TEAM = ['owner', 'admin', 'operator', 'manager', 'driver'];

    /** Tylko kierowcy — driver-only views (moje trasy, mój kalendarz). */
    public const DRIVERS_ONLY = ['driver'];

    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * Role of the logged-in user in the currently active tenant, or null
     * if no tenant is bound, no user is logged in, or no membership exists.
     */
    public function role(): ?string
    {
        $tenant = $this->tenants->current();
        $user = Auth::user();

        if (! $tenant || ! $user) {
            return null;
        }

        $role = $tenant->memberships()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->value('role');

        return $role !== null ? (string) $role : null;
    }

    /**
     * @param  list<string>  $roles
     */
    public function isAnyOf(array $roles): bool
    {
        $role = $this->role();

        return $role !== null && in_array($role, $roles, true);
    }

    public function isMasterAdmin(): bool
    {
        return Auth::user()?->is_master_admin === true;
    }

    /**
     * Master admins always pass — they impersonate to debug. Standard
     * users are checked against the role list.
     *
     * @param  list<string>  $roles
     */
    public function allows(array $roles): bool
    {
        if ($this->isMasterAdmin()) {
            return true;
        }

        return $this->isAnyOf($roles);
    }
}
