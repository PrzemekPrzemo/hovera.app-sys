<?php

declare(strict_types=1);

namespace Tests\Feature\Horses;

use App\Domain\Horses\HorseRegistrySyncService;
use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Horse;
use App\Models\Tenant\OwnerHorse;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/**
 * PR 4/5 — cross-tenant horse registry foundation.
 *
 * Pokrywa:
 *  - owner tworzy konia → registerForOwner() tworzy central row +
 *    back-fillsuje central_horse_id w lokalnej kartotece
 *  - register jest idempotentny (drugi call nie tworzy duplikatu)
 *  - passport_no kolizja: gdy owner dodaje konia z passport_no który
 *    już jest w central, link'ujemy do istniejącego (potencjalnie
 *    legacy stable horse)
 *  - requestBoarding tworzy pending row, drugi call to no-op
 *  - activateBoarding(pending) → active + started_at
 *  - endBoarding → ended + ended_at
 */
class HorseRegistrySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private HorseRegistrySyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_hrs_').'.sqlite';
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
        $this->sync = app(HorseRegistrySyncService::class);
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_register_for_owner_creates_central_row_and_backfills_local(): void
    {
        $owner = $this->makeUser();
        $horse = OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Iskra',
            'breed' => 'Arab',
            'birth_date' => '2020-04-12',
            'passport_number' => 'PL-001234',
        ]);

        $registry = $this->sync->registerForOwner($horse, $owner);

        $this->assertNotNull($registry->id);
        $this->assertSame($owner->id, $registry->primary_owner_user_id);
        $this->assertSame('Iskra', $registry->name);
        $this->assertSame('PL-001234', $registry->passport_no);

        $horse->refresh();
        $this->assertSame($registry->id, $horse->central_horse_id);
    }

    public function test_register_is_idempotent(): void
    {
        $owner = $this->makeUser();
        $horse = OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Iskra',
            'passport_number' => 'PL-001234',
        ]);

        $first = $this->sync->registerForOwner($horse, $owner);
        $second = $this->sync->registerForOwner($horse->refresh(), $owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CentralHorseRegistry::query()->count());
    }

    public function test_register_links_to_existing_central_when_passport_matches(): void
    {
        // Pre-existing central row (np. legacy stable horse)
        $existing = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'name' => 'Old Name',
            'passport_no' => 'PL-DUPLICATE',
        ]);

        // Owner dodaje konia z tym samym paszportem — chcemy link do
        // istniejącego, NIE nowego registry.
        $owner = $this->makeUser();
        $horse = OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Iskra',
            'passport_number' => 'PL-DUPLICATE',
        ]);

        $registry = $this->sync->registerForOwner($horse, $owner);

        $this->assertSame($existing->id, $registry->id);
        $this->assertSame(1, CentralHorseRegistry::query()->count(),
            'Nie tworzymy duplikatu — linkujemy do istniejącego po passport_no');
    }

    public function test_register_without_passport_creates_separate_rows(): void
    {
        // Bez passport_no — każde wywołanie tworzy NOWY registry
        // (passport_no jest jedynym deterministyczym dedupe key'em).
        $owner = $this->makeUser();
        $horse1 = OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Horse 1',
        ]);
        $horse2 = OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Horse 2',
        ]);

        $r1 = $this->sync->registerForOwner($horse1, $owner);
        $r2 = $this->sync->registerForOwner($horse2, $owner);

        $this->assertNotSame($r1->id, $r2->id);
        $this->assertSame(2, CentralHorseRegistry::query()->count());
    }

    public function test_request_boarding_creates_pending_row(): void
    {
        $owner = $this->makeUser();
        $horse = OwnerHorse::create(['id' => (string) Str::ulid(), 'name' => 'Iskra']);
        $registry = $this->sync->registerForOwner($horse, $owner);

        $stable = $this->makeStableTenant();

        $assignment = $this->sync->requestBoarding($registry, $stable, $owner);

        $this->assertSame(HorseBoardingAssignment::STATUS_PENDING, $assignment->status);
        $this->assertSame($stable->id, $assignment->stable_tenant_id);
        $this->assertSame($owner->id, $assignment->owner_user_id);
        $this->assertNull($assignment->started_at);
    }

    public function test_request_boarding_is_idempotent(): void
    {
        $owner = $this->makeUser();
        $horse = OwnerHorse::create(['id' => (string) Str::ulid(), 'name' => 'Iskra']);
        $registry = $this->sync->registerForOwner($horse, $owner);
        $stable = $this->makeStableTenant();

        $first = $this->sync->requestBoarding($registry, $stable, $owner);
        $second = $this->sync->requestBoarding($registry, $stable, $owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, HorseBoardingAssignment::query()->count());
    }

    public function test_activate_boarding_flips_pending_to_active(): void
    {
        $owner = $this->makeUser();
        $horse = OwnerHorse::create(['id' => (string) Str::ulid(), 'name' => 'Iskra']);
        $registry = $this->sync->registerForOwner($horse, $owner);
        $stable = $this->makeStableTenant();

        $pending = $this->sync->requestBoarding($registry, $stable, $owner);
        $this->assertNull($pending->started_at);

        $active = $this->sync->activateBoarding($pending);

        $this->assertSame(HorseBoardingAssignment::STATUS_ACTIVE, $active->status);
        $this->assertNotNull($active->started_at);
    }

    public function test_activate_is_idempotent_does_not_reset_started_at(): void
    {
        $owner = $this->makeUser();
        $horse = OwnerHorse::create(['id' => (string) Str::ulid(), 'name' => 'Iskra']);
        $registry = $this->sync->registerForOwner($horse, $owner);
        $stable = $this->makeStableTenant();

        $pending = $this->sync->requestBoarding($registry, $stable, $owner);
        $first = $this->sync->activateBoarding($pending);
        $startedAt = $first->started_at;

        // Drugi activate — nie chcemy resetu started_at.
        $second = $this->sync->activateBoarding($first->refresh());

        $this->assertTrue($startedAt->equalTo($second->started_at));
    }

    public function test_end_boarding_sets_status_and_ended_at(): void
    {
        $owner = $this->makeUser();
        $horse = OwnerHorse::create(['id' => (string) Str::ulid(), 'name' => 'Iskra']);
        $registry = $this->sync->registerForOwner($horse, $owner);
        $stable = $this->makeStableTenant();

        $active = $this->sync->activateBoarding(
            $this->sync->requestBoarding($registry, $stable, $owner),
        );

        $ended = $this->sync->endBoarding($active);

        $this->assertSame(HorseBoardingAssignment::STATUS_ENDED, $ended->status);
        $this->assertNotNull($ended->ended_at);
    }

    public function test_attach_local_to_central_works_for_legacy_stable_horse(): void
    {
        // Legacy: stable utworzył horse'a w pełnej tabeli `horses` (z box_id,
        // owner_client_id, itp.), bez central_horse_id.
        $horse = Horse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Stable Horse',
            'breed' => 'Trakehner',
            'passport_number' => 'PL-STABLE-1',
        ]);
        $this->assertNull($horse->central_horse_id);

        $owner = $this->makeUser();
        $registry = $this->sync->attachLocalToCentral($horse, $owner);

        $horse->refresh();
        $this->assertSame($registry->id, $horse->central_horse_id);
        $this->assertSame($owner->id, $registry->primary_owner_user_id);
        $this->assertSame('PL-STABLE-1', $registry->passport_no);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Owner '.uniqid(),
            'email' => 'owner-'.uniqid().'@example.com',
            'password' => bcrypt('x'),
        ]);
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'stable-'.$u,
            'name' => 'Stable',
            'type' => TenantType::Stable,
            'db_name' => 'stb_'.$u,
            'db_username' => 'stb_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = Tenant::create([
            'slug' => 'own-'.$u,
            'name' => 'Owner Tenant',
            'type' => TenantType::HorseOwner,
            'db_name' => 'own_'.$u,
            'db_username' => 'own_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $tm = $this->app->make(TenantManager::class);
        $ref = new ReflectionClass($tm);
        $prop = $ref->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_horse_id', 26)->nullable()->index();
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
