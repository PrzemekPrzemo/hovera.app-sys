<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\VehicleType;
use App\Models\Tenant\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehicleModelTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_veh_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpVehiclesTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_can_create_vehicle_with_full_payload(): void
    {
        $vehicle = Vehicle::create([
            'id' => (string) Str::ulid(),
            'name' => 'Volvo FH16 — wóz duży',
            'registration_plate' => 'WPL 12345',
            'capacity_horses' => 6,
            'gross_weight_kg' => 18000,
            'payload_kg' => 9000,
            'year_of_manufacture' => 2021,
            'has_air_suspension' => true,
            'has_camera' => true,
            'has_climate_control' => false,
            'photos' => ['storage/vehicles/abc.jpg'],
            'notes' => 'Wóz flagowy',
        ]);

        $fresh = $vehicle->fresh();

        $this->assertSame(6, $fresh->capacity_horses);
        $this->assertSame(18000, $fresh->gross_weight_kg);
        $this->assertTrue($fresh->has_air_suspension);
        $this->assertTrue($fresh->has_camera);
        $this->assertFalse($fresh->has_climate_control);
        $this->assertTrue($fresh->is_active);
        $this->assertSame(['storage/vehicles/abc.jpg'], $fresh->photos);
    }

    public function test_capacity_horses_is_cast_to_integer(): void
    {
        $vehicle = Vehicle::create([
            'id' => (string) Str::ulid(),
            'name' => 'Test',
            'registration_plate' => 'X',
            'capacity_horses' => '3',
        ]);

        $this->assertSame(3, $vehicle->fresh()->capacity_horses);
    }

    public function test_photos_round_trip_as_array(): void
    {
        $vehicle = Vehicle::create([
            'id' => (string) Str::ulid(),
            'name' => 'T',
            'registration_plate' => 'X',
            'capacity_horses' => 2,
            'photos' => ['a.jpg', 'b.jpg'],
        ]);

        $this->assertSame(['a.jpg', 'b.jpg'], $vehicle->fresh()->photos);
    }

    public function test_soft_deletes(): void
    {
        $vehicle = Vehicle::create([
            'id' => (string) Str::ulid(),
            'name' => 'T',
            'registration_plate' => 'X',
            'capacity_horses' => 2,
        ]);

        $vehicle->delete();

        $this->assertNull(Vehicle::find($vehicle->id));
        $this->assertNotNull(Vehicle::withTrashed()->find($vehicle->id));
    }

    public function test_default_vehicle_type_is_truck(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Default truck',
            'registration_plate' => 'X',
            'capacity_horses' => 2,
        ]);

        $this->assertSame(VehicleType::Truck, $vehicle->refresh()->vehicle_type);
        $this->assertFalse($vehicle->isTrailer());
    }

    public function test_trailers_and_trucks_scopes_filter_by_type(): void
    {
        Vehicle::create([
            'name' => 'Volvo FH16',
            'vehicle_type' => VehicleType::Truck->value,
            'registration_plate' => 'PL-T1',
            'capacity_horses' => 4,
        ]);
        Vehicle::create([
            'name' => 'Bockmann Comfort',
            'vehicle_type' => VehicleType::Trailer->value,
            'registration_plate' => 'PL-A1',
            'capacity_horses' => 2,
        ]);

        $this->assertSame(1, Vehicle::query()->trucks()->count());
        $this->assertSame(1, Vehicle::query()->trailers()->count());
        $this->assertTrue(Vehicle::query()->trailers()->first()->isTrailer());
    }

    public function test_filament_route_is_registered(): void
    {
        $routes = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue($routes->contains('filament.transport.resources.vehicles.index'));
        $this->assertTrue($routes->contains('filament.transport.resources.vehicles.create'));
        $this->assertTrue($routes->contains('filament.transport.resources.vehicles.edit'));
    }

    private function setUpVehiclesTable(): void
    {
        Schema::connection('tenant')->create('vehicles', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('vehicle_type', 16)->default('truck');
            $t->string('registration_plate', 16);
            $t->unsignedTinyInteger('capacity_horses');
            $t->decimal('gross_weight_kg', 8, 0)->nullable();
            $t->decimal('payload_kg', 8, 0)->nullable();
            $t->unsignedSmallInteger('year_of_manufacture')->nullable();
            $t->json('photos')->nullable();
            $t->boolean('has_air_suspension')->default(false);
            $t->boolean('has_camera')->default(false);
            $t->boolean('has_climate_control')->default(false);
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
