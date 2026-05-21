<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\App\Resources\ArenaResource;
use App\Filament\App\Resources\RecurringCalendarEntryResource;
use App\Services\Tenancy\TenantRoleGate;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pokrywa role gating dla RecurringCalendarEntryResource (G2 z audytu
 * ról vet/instructor/employee). Wet dostaje dostęp do recurring sessions
 * tylko (planowanie rehab post-injury), ale NIE do Arena/Box/Building.
 */
class RecurringCalendarVetAccessTest extends TestCase
{
    public function test_recurring_calendar_resource_uses_recurring_calendar_staff_gate(): void
    {
        $method = new ReflectionMethod(RecurringCalendarEntryResource::class, 'allowedRoles');
        $method->setAccessible(true);
        $roles = $method->invoke(null);

        $this->assertSame(TenantRoleGate::RECURRING_CALENDAR_STAFF, $roles);
        $this->assertContains('vet', $roles, 'vet musi mieć dostęp do recurring calendar (G2)');
    }

    public function test_recurring_calendar_staff_extends_stable_ops_with_vet(): void
    {
        // Wszystkie role z STABLE_OPS_STAFF MUSZĄ być w RECURRING_CALENDAR_STAFF
        // (no regression dla istniejących użytkowników), + extra vet.
        $diff = array_diff(TenantRoleGate::STABLE_OPS_STAFF, TenantRoleGate::RECURRING_CALENDAR_STAFF);
        $this->assertSame([], $diff, 'RECURRING_CALENDAR_STAFF musi zawierać wszystkie role z STABLE_OPS_STAFF');

        $added = array_values(array_diff(TenantRoleGate::RECURRING_CALENDAR_STAFF, TenantRoleGate::STABLE_OPS_STAFF));
        $this->assertSame(['vet'], $added, 'RECURRING_CALENDAR_STAFF dodaje TYLKO vet ponad STABLE_OPS_STAFF');
    }

    public function test_arena_resource_still_excludes_vet(): void
    {
        // Sprawdzamy że nie rozszerzyliśmy vet'a accidentalnie na Arena/Box/etc —
        // tylko recurring calendar dostaje vet'a.
        $method = new ReflectionMethod(ArenaResource::class, 'allowedRoles');
        $method->setAccessible(true);
        $roles = $method->invoke(null);

        $this->assertNotContains('vet', $roles, 'vet nie powinien mieć dostępu do Arena CRUD (tylko recurring sessions)');
    }
}
