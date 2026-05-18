<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class DriverModelTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_drv_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpDriversTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_can_create_driver_with_full_payload(): void
    {
        $driver = Driver::create([
            'id' => (string) Str::ulid(),
            'first_name' => 'Adam',
            'last_name' => 'Kowalski',
            'email' => 'adam@example.com',
            'phone' => '+48 600 100 200',
            'license_number' => 'ABC12345',
            'license_categories' => ['B', 'C', 'CE'],
            'license_expires_at' => now()->addYears(3)->toDateString(),
            'has_animal_transport_cert' => true,
            'animal_transport_cert_expires_at' => now()->addYear()->toDateString(),
            'date_of_birth' => '1985-04-12',
            'hire_date' => '2020-09-01',
        ]);

        $fresh = $driver->fresh();

        $this->assertSame('Adam Kowalski', $fresh->full_name);
        $this->assertSame(['B', 'C', 'CE'], $fresh->license_categories);
        $this->assertTrue($fresh->has_animal_transport_cert);
        $this->assertFalse($fresh->has_adr);
        $this->assertTrue($fresh->is_active);
    }

    public function test_full_name_handles_blank_parts(): void
    {
        $driver = Driver::create([
            'id' => (string) Str::ulid(),
            'first_name' => 'Adam',
            'last_name' => '',
        ]);

        $this->assertSame('Adam', $driver->fresh()->full_name);
    }

    public function test_license_categories_round_trip_as_array(): void
    {
        $driver = Driver::create([
            'id' => (string) Str::ulid(),
            'first_name' => 'A',
            'last_name' => 'B',
            'license_categories' => ['C+E', 'D'],
        ]);

        $this->assertSame(['C+E', 'D'], $driver->fresh()->license_categories);
    }

    public function test_soft_deletes(): void
    {
        $driver = Driver::create([
            'id' => (string) Str::ulid(),
            'first_name' => 'A',
            'last_name' => 'B',
        ]);

        $driver->delete();

        $this->assertNull(Driver::find($driver->id));
        $this->assertNotNull(Driver::withTrashed()->find($driver->id));
    }

    public function test_filament_route_is_registered(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue($routes->contains('filament.transport.resources.drivers.index'));
        $this->assertTrue($routes->contains('filament.transport.resources.drivers.create'));
        $this->assertTrue($routes->contains('filament.transport.resources.drivers.edit'));
    }

    private function setUpDriversTable(): void
    {
        Schema::connection('tenant')->create('drivers', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_user_id', 26)->nullable();
            $t->string('first_name', 60);
            $t->string('last_name', 80)->default('');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('license_number', 32)->nullable();
            $t->json('license_categories')->nullable();
            $t->date('license_expires_at')->nullable();
            $t->boolean('has_animal_transport_cert')->default(false);
            $t->date('animal_transport_cert_expires_at')->nullable();
            $t->boolean('has_adr')->default(false);
            $t->date('adr_expires_at')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->date('hire_date')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
