<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Horses;

use App\Domain\Horses\StableHorseSnapshotService;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Pokrywa `StableHorseSnapshotService` — cross-tenant reader który
 * używa TenantManager::execute do przepięcia connection 'tenant' na
 * stable DB, czyta Horse + relacje i zwraca HorseSnapshot DTO.
 */
class StableHorseSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_snapshot_').'.sqlite';
        touch($this->stableDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->stableDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpStableSchema();
        $this->stableTenant = $this->makeStableTenant();

        // Mock TenantManager — bez tego execute() wywoła setCurrent który
        // nadpisze nasz SQLite config Tenant::databaseConnectionConfig'iem
        // (MySQL z pustym host'em → "Database hosts array is empty").
        // Pattern z DocumentExpiryNotificationTest.
        $held = null;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(fn () => $held);
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
            $m->shouldReceive('execute')->andReturnUsing(function (Tenant $t, callable $cb) use (&$held) {
                $prev = $held;
                $held = $t;
                try {
                    return $cb($t);
                } finally {
                    $held = $prev;
                }
            });
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_snapshot_includes_full_horse_identification(): void
    {
        $centralHorseId = (string) Str::ulid();
        $this->seedHorse($centralHorseId, [
            'name' => 'Iskra',
            'breed' => 'KWPN',
            'sex' => 'mare',
            'color' => 'gniada',
            'birth_date' => '2018-03-15',
            'passport_number' => 'PL-12345',
            'microchip' => '985121234567890',
            'ueln' => '528003201812345',
            'notes' => 'Spokojna, dobrze pracuje z młodzieżą.',
        ]);

        // Konieczne: po tenant table setup, manager nie ma tenant'a.
        // Service sam się przełączy przez execute(), ale my musimy
        // upewnić się że po wyjściu connection wraca do "no tenant" stanu.
        $snapshot = app(StableHorseSnapshotService::class)
            ->forCentralHorse($centralHorseId, $this->stableTenant);

        $this->assertSame($centralHorseId, $snapshot->centralHorseId);
        $this->assertSame('Iskra', $snapshot->name);
        $this->assertSame('KWPN', $snapshot->breed);
        $this->assertSame('mare', $snapshot->sex);
        $this->assertSame('gniada', $snapshot->color);
        $this->assertSame('PL-12345', $snapshot->passportNumber);
        $this->assertSame('985121234567890', $snapshot->microchip);
        $this->assertSame('528003201812345', $snapshot->ueln);
        $this->assertNotNull($snapshot->birthDate);
        $this->assertSame('2018-03-15', $snapshot->birthDate->toDateString());
        $this->assertSame('Spokojna, dobrze pracuje z młodzieżą.', $snapshot->notes);
    }

    public function test_snapshot_includes_current_box_with_building(): void
    {
        $centralHorseId = (string) Str::ulid();
        $horseId = (string) Str::ulid();
        $buildingId = (string) Str::ulid();
        $boxId = (string) Str::ulid();

        DB::connection('tenant')->table('buildings')->insert([
            'id' => $buildingId,
            'name' => 'Stajnia główna',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('boxes')->insert([
            'id' => $boxId,
            'building_id' => $buildingId,
            'name' => 'Box #7',
            'type' => 'indoor',
            'capacity' => 1,
            'monthly_rate_cents' => 180000,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedHorse($centralHorseId, ['name' => 'Iskra'], $horseId, $boxId);
        DB::connection('tenant')->table('box_assignments')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $horseId,
            'box_id' => $boxId,
            'assigned_at' => Carbon::parse('2026-01-10 10:00:00'),
            'vacated_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = app(StableHorseSnapshotService::class)
            ->forCentralHorse($centralHorseId, $this->stableTenant);

        $this->assertNotNull($snapshot->currentBox);
        $this->assertSame('Box #7', $snapshot->currentBox->boxName);
        $this->assertSame('Stajnia główna', $snapshot->currentBox->buildingName);
        $this->assertSame(180000, $snapshot->currentBox->monthlyRateCents);
    }

    public function test_snapshot_skips_vacated_box_assignments(): void
    {
        $centralHorseId = (string) Str::ulid();
        $horseId = (string) Str::ulid();
        $boxId = (string) Str::ulid();

        DB::connection('tenant')->table('boxes')->insert([
            'id' => $boxId,
            'name' => 'Old box',
            'type' => 'indoor',
            'capacity' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedHorse($centralHorseId, ['name' => 'Iskra'], $horseId);
        DB::connection('tenant')->table('box_assignments')->insert([
            'id' => (string) Str::ulid(),
            'horse_id' => $horseId,
            'box_id' => $boxId,
            'assigned_at' => Carbon::parse('2025-12-01'),
            'vacated_at' => Carbon::parse('2026-01-09'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = app(StableHorseSnapshotService::class)
            ->forCentralHorse($centralHorseId, $this->stableTenant);

        // Wszystkie boxAssignments są vacated → null current box.
        $this->assertNull($snapshot->currentBox);
    }

    public function test_snapshot_includes_active_boarding_services_with_pivot_override(): void
    {
        $centralHorseId = (string) Str::ulid();
        $horseId = (string) Str::ulid();
        $serviceId = (string) Str::ulid();

        DB::connection('tenant')->table('boarding_services')->insert([
            'id' => $serviceId,
            'name' => 'Pensjonat full board',
            'description' => 'Trzy posiłki dziennie + sprzątanie + paddock',
            'unit' => 'm-c',
            'frequency' => 'monthly',
            'price_cents' => 200000,
            'vat_rate' => '23',
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedHorse($centralHorseId, ['name' => 'Iskra'], $horseId);
        DB::connection('tenant')->table('horse_boarding_services')->insert([
            'horse_id' => $horseId,
            'boarding_service_id' => $serviceId,
            'price_override_cents' => 180000,  // negocjowana cena per ten koń
            'quantity' => 1,
            'starts_at' => Carbon::parse('2026-01-01'),
            'ends_at' => null,
            'notes' => 'Owner ma stałą zniżkę',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = app(StableHorseSnapshotService::class)
            ->forCentralHorse($centralHorseId, $this->stableTenant);

        $this->assertCount(1, $snapshot->boardingServices);
        $service = $snapshot->boardingServices[0];
        $this->assertSame('Pensjonat full board', $service->name);
        $this->assertSame('monthly', $service->frequency);
        // Override z pivot'u wygrywa nad price_cents w boarding_services.
        $this->assertSame(180000, $service->effectivePriceCents);
        $this->assertSame('Owner ma stałą zniżkę', $service->notes);
    }

    public function test_snapshot_uses_service_default_price_when_no_pivot_override(): void
    {
        $centralHorseId = (string) Str::ulid();
        $horseId = (string) Str::ulid();
        $serviceId = (string) Str::ulid();

        DB::connection('tenant')->table('boarding_services')->insert([
            'id' => $serviceId,
            'name' => 'Owies dodatkowy',
            'unit' => 'kg',
            'frequency' => 'daily',
            'price_cents' => 500,
            'vat_rate' => '23',
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->seedHorse($centralHorseId, ['name' => 'Iskra'], $horseId);
        DB::connection('tenant')->table('horse_boarding_services')->insert([
            'horse_id' => $horseId,
            'boarding_service_id' => $serviceId,
            'price_override_cents' => null,
            'quantity' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = app(StableHorseSnapshotService::class)
            ->forCentralHorse($centralHorseId, $this->stableTenant);

        $this->assertCount(1, $snapshot->boardingServices);
        $this->assertSame(500, $snapshot->boardingServices[0]->effectivePriceCents);
        $this->assertSame(2.0, $snapshot->boardingServices[0]->quantity);
    }

    public function test_throws_runtime_exception_when_horse_missing_in_stable(): void
    {
        // Sync rift — assignment istnieje (centralnie) ale stable DB nie ma
        // tego central_horse_id (np. stable usunął rekord accidentally).
        $this->expectException(RuntimeException::class);
        app(StableHorseSnapshotService::class)
            ->forCentralHorse('01HZZZZZZZZZZZZZZZZZZZZZ', $this->stableTenant);
    }

    public function test_estimated_monthly_cost_combines_box_and_services(): void
    {
        $centralHorseId = (string) Str::ulid();
        $horseId = (string) Str::ulid();
        $boxId = (string) Str::ulid();
        $monthlyServiceId = (string) Str::ulid();
        $dailyServiceId = (string) Str::ulid();

        DB::connection('tenant')->table('boxes')->insert([
            'id' => $boxId,
            'name' => 'Box',
            'type' => 'indoor',
            'capacity' => 1,
            'monthly_rate_cents' => 150000, // 1500 zł
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('boarding_services')->insert([
            ['id' => $monthlyServiceId, 'name' => 'Mineralia', 'unit' => 'm-c', 'frequency' => 'monthly', 'price_cents' => 20000, 'vat_rate' => '23', 'is_active' => 1, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['id' => $dailyServiceId, 'name' => 'Owies', 'unit' => 'kg', 'frequency' => 'daily', 'price_cents' => 500, 'vat_rate' => '23', 'is_active' => 1, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seedHorse($centralHorseId, ['name' => 'Iskra'], $horseId, $boxId);
        DB::connection('tenant')->table('horse_boarding_services')->insert([
            ['horse_id' => $horseId, 'boarding_service_id' => $monthlyServiceId, 'quantity' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['horse_id' => $horseId, 'boarding_service_id' => $dailyServiceId, 'quantity' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $snapshot = app(StableHorseSnapshotService::class)
            ->forCentralHorse($centralHorseId, $this->stableTenant);

        // box 150000 + monthly 20000*1 + daily 500*2*30 = 200000
        $this->assertSame(200000, $snapshot->estimatedMonthlyCostCents);
    }

    public function test_execute_restores_tenant_connection_after_snapshot(): void
    {
        // Krytyczne dla cross-tenant safety: po execute() TenantManager
        // musi mieć current=null (lub previous tenant), connection nie
        // może wskazywać na stable po wyjściu z serwisu.
        $centralHorseId = (string) Str::ulid();
        $this->seedHorse($centralHorseId, ['name' => 'Iskra']);

        $tm = app(TenantManager::class);
        $this->assertFalse($tm->hasTenant(), 'precondition: no tenant before');

        app(StableHorseSnapshotService::class)
            ->forCentralHorse($centralHorseId, $this->stableTenant);

        $this->assertFalse(
            $tm->hasTenant(),
            'TenantManager should be back to no-tenant state after execute()'
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedHorse(string $centralHorseId, array $overrides = [], ?string $horseId = null, ?string $boxId = null): string
    {
        $horseId ??= (string) Str::ulid();
        DB::connection('tenant')->table('horses')->insert(array_merge([
            'id' => $horseId,
            'central_horse_id' => $centralHorseId,
            'name' => 'Horse',
            'box_id' => $boxId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $horseId;
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'snap-st-'.$u,
            'name' => 'Snapshot Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'snap_st_'.$u,
            'db_username' => 'snap_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function setUpStableSchema(): void
    {
        Schema::connection('tenant')->create('buildings', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('boxes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('building_id', 26)->nullable();
            $t->string('name', 120);
            $t->string('label', 120)->nullable();
            $t->string('type', 32)->default('indoor');
            $t->integer('size_m2')->nullable();
            $t->integer('capacity')->default(1);
            $t->integer('monthly_rate_cents')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_horse_id', 26)->nullable();
            $t->string('name', 120);
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('ueln', 32)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 24)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('owner_client_id', 26)->nullable();
            $t->string('box_id', 26)->nullable();
            $t->string('cover_image_path', 500)->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->string('livejumping_profile_url', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('box_assignments', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('box_id', 26);
            $t->string('assigned_by_user_id', 26)->nullable();
            $t->timestamp('assigned_at');
            $t->timestamp('vacated_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });

        Schema::connection('tenant')->create('boarding_services', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->string('unit', 32);
            $t->string('frequency', 16);
            $t->integer('price_cents');
            $t->string('vat_rate', 8)->default('23');
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horse_boarding_services', function ($t) {
            $t->string('horse_id', 26);
            $t->string('boarding_service_id', 26);
            $t->integer('price_override_cents')->nullable();
            $t->decimal('quantity', 10, 2)->default(1);
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->primary(['horse_id', 'boarding_service_id']);
        });
    }
}
