<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Horses;

use App\Domain\Horses\HorseFieldChangeRequestService;
use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseFieldChangeRequest;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa Faza 6 PR 6.3 — HorseFieldChangeRequestService:
 *   * propose tworzy nowe / aktualizuje istniejące pending
 *   * accept markuje accepted (no revert)
 *   * reject markuje rejected + cross-tenant Horse revert
 *   * pendingForOwner zwraca tylko jego konie
 *   * Ownership check (accept/reject — only primary_owner)
 */
class HorseFieldChangeRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stableTenant;

    private User $owner;

    private string $centralHorseId;

    private string $horseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hovera_fchg_').'.sqlite';
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
        $this->owner = User::create([
            'name' => 'Jan',
            'email' => 'jan-'.uniqid().'@example.test',
            'password' => bcrypt('x'),
        ]);

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

        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $this->owner->id,
            'name' => 'Iskra',
        ]);
        $this->centralHorseId = $registry->id;
        $this->horseId = $this->seedHorse($this->centralHorseId, 'Iskra', 'PL-OLD-123', '985-OLD-456');
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        parent::tearDown();
    }

    public function test_propose_creates_new_pending_request(): void
    {
        $req = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );

        $this->assertSame(HorseFieldChangeRequest::STATUS_PENDING, $req->status);
        $this->assertSame('Iskra', $req->old_value);
        $this->assertSame('Iskierka', $req->new_value);
        $this->assertSame($this->stableTenant->id, $req->proposed_by_tenant_id);
        $this->assertSame(1, HorseFieldChangeRequest::query()->count());
    }

    public function test_propose_updates_existing_pending_for_same_horse_and_field(): void
    {
        // Pierwsze proposal Iskra → Iskierka
        $first = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );

        // Drugie: zmienia w tabeli z Iskierka → Burza (stable jeszcze raz zmienia)
        $second = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskierka', // nie zmienia old_value
            'Burza',
        );

        $this->assertSame($first->id, $second->id, 'Same DB row');
        $this->assertSame(1, HorseFieldChangeRequest::query()->count());

        // old_value zachowany z pierwszego proposal'u (true pre-change state)
        $this->assertSame('Iskra', $second->old_value);
        $this->assertSame('Burza', $second->new_value);
    }

    public function test_propose_rejects_invalid_field(): void
    {
        $this->expectException(\RuntimeException::class);
        app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            'breed', // nie w ALL_FIELDS
            'KWPN',
            'Hanowerska',
        );
    }

    public function test_accept_marks_accepted_and_keeps_horse_value(): void
    {
        // Zmień Horse name w stable (symulacja stable change)
        DB::connection('tenant')->table('horses')
            ->where('id', $this->horseId)
            ->update(['name' => 'Iskierka']);

        $req = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );

        $accepted = app(HorseFieldChangeRequestService::class)->accept($this->owner, $req);

        $this->assertSame(HorseFieldChangeRequest::STATUS_ACCEPTED, $accepted->status);
        $this->assertNotNull($accepted->decided_at);
        $this->assertSame($this->owner->id, $accepted->decided_by_user_id);

        // Horse pozostaje z nową nazwą
        $horseName = DB::connection('tenant')->table('horses')
            ->where('id', $this->horseId)
            ->value('name');
        $this->assertSame('Iskierka', $horseName);
    }

    public function test_reject_reverts_horse_to_old_value(): void
    {
        // Stable zmienił Iskra → Iskierka
        DB::connection('tenant')->table('horses')
            ->where('id', $this->horseId)
            ->update(['name' => 'Iskierka']);

        $req = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );

        $rejected = app(HorseFieldChangeRequestService::class)
            ->reject($this->owner, $req, reason: 'Brak zgody na zmianę paszportu');

        $this->assertSame(HorseFieldChangeRequest::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Brak zgody na zmianę paszportu', $rejected->reject_reason);

        // Horse SHOULD be reverted do 'Iskra'
        $horseName = DB::connection('tenant')->table('horses')
            ->where('id', $this->horseId)
            ->value('name');
        $this->assertSame('Iskra', $horseName);
    }

    public function test_reject_passport_reverts_to_old_passport(): void
    {
        DB::connection('tenant')->table('horses')
            ->where('id', $this->horseId)
            ->update(['passport_number' => 'PL-NEW-999']);

        $req = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_PASSPORT,
            'PL-OLD-123',
            'PL-NEW-999',
        );

        app(HorseFieldChangeRequestService::class)
            ->reject($this->owner, $req, null);

        $passport = DB::connection('tenant')->table('horses')
            ->where('id', $this->horseId)
            ->value('passport_number');
        $this->assertSame('PL-OLD-123', $passport);
    }

    public function test_accept_throws_when_not_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'o-'.uniqid().'@e.t',
            'password' => bcrypt('x'),
        ]);
        $req = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );

        $this->expectException(AuthorizationException::class);
        app(HorseFieldChangeRequestService::class)->accept($other, $req);
    }

    public function test_reject_throws_when_not_owner(): void
    {
        $other = User::create([
            'name' => 'Other',
            'email' => 'o-'.uniqid().'@e.t',
            'password' => bcrypt('x'),
        ]);
        $req = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );

        $this->expectException(AuthorizationException::class);
        app(HorseFieldChangeRequestService::class)->reject($other, $req, null);
    }

    public function test_accept_is_idempotent_on_already_decided_request(): void
    {
        $req = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );

        app(HorseFieldChangeRequestService::class)->accept($this->owner, $req);
        $first = $req->fresh()->decided_at;

        // Drugi accept call — nie powinien nadpisywać timestamp'a
        sleep(1);
        app(HorseFieldChangeRequestService::class)->accept($this->owner, $req->fresh());
        $second = $req->fresh()->decided_at;

        $this->assertEquals($first, $second);
    }

    public function test_pending_for_owner_returns_only_his_horses(): void
    {
        // Drugi owner z drugim koniem + pending request
        $otherOwner = User::create([
            'name' => 'Other',
            'email' => 'o-'.uniqid().'@e.t',
            'password' => bcrypt('x'),
        ]);
        $otherRegistry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $otherOwner->id,
            'name' => 'Burza',
        ]);

        // Pending dla naszego ownera
        app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );

        // Pending dla cudzego ownera
        app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $otherRegistry->id,
            HorseFieldChangeRequest::FIELD_NAME,
            'Burza',
            'Wiosna',
        );

        $pendingForUs = app(HorseFieldChangeRequestService::class)
            ->pendingForOwner($this->owner);
        $pendingForThem = app(HorseFieldChangeRequestService::class)
            ->pendingForOwner($otherOwner);

        $this->assertCount(1, $pendingForUs);
        $this->assertCount(1, $pendingForThem);
        $this->assertSame($this->centralHorseId, $pendingForUs->first()->central_horse_id);
        $this->assertSame($otherRegistry->id, $pendingForThem->first()->central_horse_id);
    }

    public function test_pending_for_owner_excludes_decided_requests(): void
    {
        $req = app(HorseFieldChangeRequestService::class)->propose(
            $this->stableTenant,
            $this->centralHorseId,
            HorseFieldChangeRequest::FIELD_NAME,
            'Iskra',
            'Iskierka',
        );
        app(HorseFieldChangeRequestService::class)->accept($this->owner, $req);

        $pending = app(HorseFieldChangeRequestService::class)
            ->pendingForOwner($this->owner);

        $this->assertCount(0, $pending);
    }

    private function seedHorse(string $centralHorseId, string $name, string $passport, string $microchip): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('horses')->insert([
            'id' => $id,
            'central_horse_id' => $centralHorseId,
            'name' => $name,
            'passport_number' => $passport,
            'microchip' => $microchip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'fchg-st-'.$u,
            'name' => 'FieldChg Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'fchg_st_'.$u,
            'db_username' => 'fchg_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function setUpStableSchema(): void
    {
        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_horse_id', 26)->nullable();
            $t->string('name', 120);
            $t->string('passport_number', 64)->nullable();
            $t->string('microchip', 32)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
