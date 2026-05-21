<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Enums\TenantType;
use App\Filament\Owner\Resources\PendingBoardingRequestResource;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa PR 2 — Owner panel widzi tylko własne pending boarding'i
 * (anti-leak), accept aktywuje + tworzy Horse w stable tenant DB,
 * reject ustawia disputed.
 */
class PendingBoardingRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stable;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hov_pbr_').'.sqlite';
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

        $this->stable = $this->makeStableTenant();
        $this->owner = User::create([
            'name' => 'Jan',
            'email' => 'jan-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_eloquent_query_scoped_to_owner_pending_only(): void
    {
        $this->actingAs($this->owner);

        $other = User::create(['name' => 'Other', 'email' => 'other-'.uniqid().'@x.test', 'password' => bcrypt('x')]);

        $h1 = $this->makeRegistry();
        $a1 = $this->makeAssignment($h1, $this->owner, HorseBoardingAssignment::STATUS_PENDING);

        // Active boarding — nie powinno być w pending list.
        $h2 = $this->makeRegistry();
        $this->makeAssignment($h2, $this->owner, HorseBoardingAssignment::STATUS_ACTIVE);

        // Cudzy owner — nie leak.
        $h3 = $this->makeRegistry($other);
        $this->makeAssignment($h3, $other, HorseBoardingAssignment::STATUS_PENDING);

        $rows = PendingBoardingRequestResource::getEloquentQuery()->get();

        $this->assertCount(1, $rows);
        $this->assertSame($a1->id, $rows->first()->id);
    }

    public function test_handle_accept_activates_assignment_and_materializes_horse_in_stable(): void
    {
        $this->actingAs($this->owner);

        $registry = $this->makeRegistry();
        $assignment = $this->makeAssignment($registry, $this->owner, HorseBoardingAssignment::STATUS_PENDING);

        PendingBoardingRequestResource::handleAccept($assignment->fresh());

        $assignment->refresh();
        $this->assertSame(HorseBoardingAssignment::STATUS_ACTIVE, $assignment->status);
        $this->assertNotNull($assignment->started_at);

        // Horse pojawił się w stable tenant DB z central_horse_id linkiem.
        $count = DB::connection('tenant')->table('horses')
            ->where('central_horse_id', $registry->id)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_handle_accept_does_not_duplicate_horse_if_already_linked(): void
    {
        $this->actingAs($this->owner);

        $registry = $this->makeRegistry();
        // Pre-seed Horse w stable z central_horse_id (legacy / previous boarding).
        DB::connection('tenant')->table('horses')->insert([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'name' => 'Iskra (legacy)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignment = $this->makeAssignment($registry, $this->owner, HorseBoardingAssignment::STATUS_PENDING);

        PendingBoardingRequestResource::handleAccept($assignment->fresh());

        $count = DB::connection('tenant')->table('horses')
            ->where('central_horse_id', $registry->id)
            ->count();
        $this->assertSame(1, $count, 'No duplicate Horse row should be created if already linked');
    }

    public function test_handle_reject_sets_status_disputed(): void
    {
        $registry = $this->makeRegistry();
        $assignment = $this->makeAssignment($registry, $this->owner, HorseBoardingAssignment::STATUS_PENDING);

        PendingBoardingRequestResource::handleReject($assignment->fresh(), 'koń sprzedany, nie boarding');

        $assignment->refresh();
        $this->assertSame(HorseBoardingAssignment::STATUS_DISPUTED, $assignment->status);
    }

    private function makeRegistry(?User $owner = null): CentralHorseRegistry
    {
        return CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => ($owner ?? $this->owner)->id,
            'name' => 'Iskra',
            'passport_no' => 'PL'.random_int(100000, 999999),
        ]);
    }

    private function makeAssignment(CentralHorseRegistry $horse, User $owner, string $status): HorseBoardingAssignment
    {
        return HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $horse->id,
            'stable_tenant_id' => $this->stable->id,
            'owner_user_id' => $owner->id,
            'status' => $status,
            'started_at' => $status === HorseBoardingAssignment::STATUS_ACTIVE ? now() : null,
        ]);
    }

    private function makeStableTenant(): Tenant
    {
        return Tenant::create([
            'slug' => 'st-'.uniqid(),
            'name' => 'Test Stable',
            'type' => TenantType::Stable,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'st_'.uniqid(),
            'db_username' => 'st_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
