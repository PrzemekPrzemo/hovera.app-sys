<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Models\Central\Tenant;
use App\Models\Tenant\OwnerHorse;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Smoke testy modelu OwnerHorse — pełen flow CRUD przez Filament Resource
 * wymagałby autoryzacji owner panel'u (RequireTenantType + login z
 * OwnerPanelProvider auth middleware). Tu testujemy że model writes/reads
 * pod tenant connection robi co trzeba; resource form/table to thin shell
 * nad standardowym Filament CRUD.
 */
class HorseResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_owner_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->tenant = $this->makeTenant();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_owner_can_create_minimal_horse(): void
    {
        $horse = OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Iskra',
            'breed' => 'Pełna Krew Angielska',
            'birth_date' => '2020-04-12',
            'passport_number' => 'PL-001234',
        ]);

        $this->assertSame('Iskra', $horse->refresh()->name);
        $this->assertSame('Pełna Krew Angielska', $horse->breed);
    }

    public function test_horse_uses_horses_table(): void
    {
        OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Burza',
        ]);

        $rowCount = OwnerHorse::query()->where('name', 'Burza')->count();
        $this->assertSame(1, $rowCount);
        $this->assertSame('horses', (new OwnerHorse)->getTable());
    }

    public function test_horse_soft_deletes(): void
    {
        $horse = OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Echo',
        ]);

        $horse->delete();

        $this->assertNotNull($horse->refresh()->deleted_at);
        $this->assertSame(0, OwnerHorse::query()->count());
        $this->assertSame(1, OwnerHorse::query()->withTrashed()->count());
    }

    public function test_horse_fillable_excludes_stable_only_fields(): void
    {
        $horse = new OwnerHorse([
            'name' => 'Test',
            'owner_client_id' => 'should-not-be-set',
            'box_id' => 'should-not-be-set',
            'cover_image_path' => 'should-not-be-set',
        ]);

        $this->assertSame('Test', $horse->name);
        $this->assertNull($horse->owner_client_id);
        $this->assertNull($horse->box_id);
        $this->assertNull($horse->cover_image_path);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'owner-'.$u,
            'name' => 'Jan Owner',
            'type' => 'horse_owner',
            'db_name' => 'owner_'.$u,
            'db_username' => 'owner_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('ueln', 15)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 24)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('owner_client_id', 26)->nullable();
            $t->string('box_id', 26)->nullable();
            $t->string('cover_image_path')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->string('livejumping_profile_url', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
