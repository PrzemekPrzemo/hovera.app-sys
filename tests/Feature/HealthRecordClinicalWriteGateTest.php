<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\App\Resources\HealthRecordResource;
use App\Services\Tenancy\TenantRoleGate;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pokrywa write gate na HealthRecordResource (G4 z audytu ról). Tylko
 * vet/admin/manager mogą tworzyć/edytować/kasować HealthRecord —
 * instruktor i employee mają wyłącznie read access.
 *
 * Read access pozostaje na HORSE_AND_CARE_STAFF (instruktor musi widzieć
 * historię zdrowotną przed lekcją).
 */
class HealthRecordClinicalWriteGateTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_clinical_write_staff_includes_vet_admin_manager_only(): void
    {
        $this->assertSame(
            ['owner', 'admin', 'manager', 'vet'],
            TenantRoleGate::CLINICAL_WRITE_STAFF,
        );
        $this->assertNotContains('instructor', TenantRoleGate::CLINICAL_WRITE_STAFF);
        $this->assertNotContains('employee', TenantRoleGate::CLINICAL_WRITE_STAFF);
        $this->assertNotContains('viewer', TenantRoleGate::CLINICAL_WRITE_STAFF);
    }

    public function test_resource_allowed_roles_unchanged_to_keep_read_access(): void
    {
        // canAccess() decyduje o nav visibility + list view. Nie ruszamy
        // tego — chcemy żeby instruktor widział listę i historię.
        $method = new ReflectionMethod(HealthRecordResource::class, 'allowedRoles');
        $method->setAccessible(true);
        $roles = $method->invoke(null);

        $this->assertContains('instructor', $roles);
        $this->assertContains('employee', $roles);
        $this->assertContains('viewer', $roles);
        $this->assertSame(TenantRoleGate::HORSE_AND_CARE_STAFF, $roles);
    }

    public function test_vet_can_write_clinical(): void
    {
        $this->bindGate(['vet']);
        $this->assertTrue(HealthRecordResource::canWriteClinical());
        $this->assertTrue(HealthRecordResource::canCreate());
    }

    public function test_manager_can_write_clinical(): void
    {
        $this->bindGate(['manager']);
        $this->assertTrue(HealthRecordResource::canCreate());
    }

    public function test_instructor_cannot_write_clinical(): void
    {
        $this->bindGate(['instructor']);
        $this->assertFalse(HealthRecordResource::canCreate());
        $this->assertFalse(HealthRecordResource::canWriteClinical());
    }

    public function test_employee_cannot_write_clinical(): void
    {
        $this->bindGate(['employee']);
        $this->assertFalse(HealthRecordResource::canCreate());
    }

    public function test_viewer_cannot_write_clinical(): void
    {
        $this->bindGate(['viewer']);
        $this->assertFalse(HealthRecordResource::canCreate());
    }

    public function test_master_admin_passes_via_gate_allows(): void
    {
        // gate.allows() ma masterAdmin escape — symulujemy via mock.
        $mock = Mockery::mock(TenantRoleGate::class);
        $mock->shouldReceive('allows')
            ->with(TenantRoleGate::CLINICAL_WRITE_STAFF)
            ->andReturn(true);
        $this->app->instance(TenantRoleGate::class, $mock);

        $this->assertTrue(HealthRecordResource::canCreate());
    }

    /** @param  list<string>  $effectiveRoles */
    private function bindGate(array $effectiveRoles): void
    {
        $mock = Mockery::mock(TenantRoleGate::class);
        $mock->shouldReceive('allows')->andReturnUsing(
            fn (array $required): bool => count(array_intersect($effectiveRoles, $required)) > 0,
        );
        $this->app->instance(TenantRoleGate::class, $mock);
    }
}
