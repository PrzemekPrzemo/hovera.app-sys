<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Domain\Horses\HorseRegistrySyncService;
use App\Enums\TenantType;
use App\Filament\App\Resources\StablePendingBoardingRequestResource;
use App\Filament\Owner\Pages\StableMarketplace;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa PR 3 z TODO.md — owner marketplace + stable accept side.
 *
 * Owner widzi listę stajni → wysyła request → tworzy się pending
 * HorseBoardingAssignment → stable widzi w /app/pending-boarding-requests
 * (mirror PendingBoardingRequestResource z odwróconym scopem).
 */
class StableMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hov_sm_').'.sqlite';
        touch($this->stableDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->stableDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_horse_id', 26)->nullable();
            $t->string('name', 120);
            $t->string('passport_number', 64)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_marketplace_lists_only_active_stable_tenants(): void
    {
        $active = $this->makeStable('active-1', TenantType::Stable, 'active');
        $trialing = $this->makeStable('trial-1', TenantType::Stable, 'trialing');
        $suspended = $this->makeStable('susp-1', TenantType::Stable, 'suspended');
        // Transporter — nie powinien być w marketplace.
        $this->makeStable('tr-1', TenantType::Transporter, 'active');

        $page = new StableMarketplace;
        $ids = $page->stables()->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertContains($trialing->id, $ids);
        $this->assertNotContains($suspended->id, $ids);
    }

    public function test_owner_initiated_request_creates_pending_assignment(): void
    {
        $stable = $this->makeStable('marketplace-st', TenantType::Stable, 'active');
        $owner = User::create(['name' => 'Jan', 'email' => 'jan-'.uniqid().'@x.test', 'password' => bcrypt('x')]);
        $horse = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'Iskra',
        ]);

        $this->actingAs($owner);
        Notification::fake();

        // Symulujemy submit przez requestBoarding service (Filament action
        // wewnętrznie wywołuje tę samą metodę).
        $assignment = app(HorseRegistrySyncService::class)->requestBoarding($horse, $stable, $owner);

        $this->assertSame(HorseBoardingAssignment::STATUS_PENDING, $assignment->status);
        $this->assertSame($stable->id, $assignment->stable_tenant_id);
        $this->assertSame($owner->id, $assignment->owner_user_id);
    }

    public function test_stable_pending_resource_scoped_to_current_stable(): void
    {
        $stableA = $this->makeStable('A-'.uniqid(), TenantType::Stable, 'active');
        $stableB = $this->makeStable('B-'.uniqid(), TenantType::Stable, 'active');
        $owner = User::create(['name' => 'O', 'email' => 'o-'.uniqid().'@x.test', 'password' => bcrypt('x')]);
        $horse = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'Iskra',
        ]);

        // Request do stable A
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $horse->id,
            'stable_tenant_id' => $stableA->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_PENDING,
        ]);

        // Request do stable B (cudzy)
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $horse->id,
            'stable_tenant_id' => $stableB->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_PENDING,
        ]);

        // Mock TenantManager żeby current() zwracał stableA.
        $this->app->instance(TenantManager::class, new class($stableA) extends TenantManager
        {
            public function __construct(private Tenant $stable) {}

            public function current(): ?Tenant
            {
                return $this->stable;
            }
        });

        $rows = StablePendingBoardingRequestResource::getEloquentQuery()->get();
        $this->assertCount(1, $rows);
        $this->assertSame($stableA->id, $rows->first()->stable_tenant_id);
    }

    public function test_stable_handle_accept_materializes_horse_and_activates(): void
    {
        $stable = $this->makeStable('accept-st', TenantType::Stable, 'active');
        $owner = User::create(['name' => 'Jan', 'email' => 'jan-acc-'.uniqid().'@x.test', 'password' => bcrypt('x')]);
        $horse = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'Iskra',
            'passport_no' => 'PL777',
        ]);
        $assignment = HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $horse->id,
            'stable_tenant_id' => $stable->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_PENDING,
        ]);

        $held = null;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
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

        StablePendingBoardingRequestResource::handleAccept($assignment->fresh());

        $assignment->refresh();
        $this->assertSame(HorseBoardingAssignment::STATUS_ACTIVE, $assignment->status);
        $this->assertNotNull($assignment->started_at);

        $count = DB::connection('tenant')->table('horses')
            ->where('central_horse_id', $horse->id)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_stable_handle_reject_sets_disputed(): void
    {
        $stable = $this->makeStable('reject-st', TenantType::Stable, 'active');
        $owner = User::create(['name' => 'Jan', 'email' => 'jan-rej-'.uniqid().'@x.test', 'password' => bcrypt('x')]);
        $horse = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'Iskra',
        ]);
        $assignment = HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $horse->id,
            'stable_tenant_id' => $stable->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_PENDING,
        ]);

        StablePendingBoardingRequestResource::handleReject($assignment->fresh(), 'brak wolnych boksów');

        $assignment->refresh();
        $this->assertSame(HorseBoardingAssignment::STATUS_DISPUTED, $assignment->status);
    }

    private function makeStable(string $slug, TenantType $type, string $status): Tenant
    {
        return Tenant::create([
            'slug' => $slug.'-'.Str::random(4),
            'name' => 'Test '.$slug,
            'type' => $type,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_'.Str::random(8),
            'db_username' => 'hovera_t_'.Str::random(8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => $status,
        ]);
    }
}
