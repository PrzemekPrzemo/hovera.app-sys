<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Horses;

use App\Domain\Horses\HorseOwnerStableAccessGate;
use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pokrywa `HorseOwnerStableAccessGate` — gate cross-tenant dostępu
 * owner'a do danych konia goszczącego w stajni. Faza 1 Owner ↔ Stable
 * shared view (patrz docs/OWNER-STABLE-ROADMAP.md).
 */
class HorseOwnerStableAccessGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_returns_assignment_for_primary_owner_with_active_boarding(): void
    {
        $owner = $this->makeUser();
        $stable = $this->makeStableTenant();
        $registry = $this->makeRegistry($owner);
        $assignment = $this->makeActiveBoarding($stable, $owner, $registry);

        $result = app(HorseOwnerStableAccessGate::class)->authorize($owner, $registry->id);

        $this->assertSame($assignment->id, $result->id);
        $this->assertSame(HorseBoardingAssignment::STATUS_ACTIVE, $result->status);
    }

    public function test_authorize_throws_when_user_is_not_primary_owner(): void
    {
        $owner = $this->makeUser();
        $otherUser = $this->makeUser();
        $stable = $this->makeStableTenant();
        $registry = $this->makeRegistry($owner);
        $this->makeActiveBoarding($stable, $owner, $registry);

        $this->expectException(AuthorizationException::class);
        app(HorseOwnerStableAccessGate::class)->authorize($otherUser, $registry->id);
    }

    public function test_authorize_throws_when_no_active_boarding_exists(): void
    {
        $owner = $this->makeUser();
        $registry = $this->makeRegistry($owner);
        // Brak HorseBoardingAssignment w ogóle.

        $this->expectException(AuthorizationException::class);
        app(HorseOwnerStableAccessGate::class)->authorize($owner, $registry->id);
    }

    public function test_authorize_throws_when_boarding_is_pending_not_active(): void
    {
        $owner = $this->makeUser();
        $stable = $this->makeStableTenant();
        $registry = $this->makeRegistry($owner);
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $stable->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_PENDING,
        ]);

        $this->expectException(AuthorizationException::class);
        app(HorseOwnerStableAccessGate::class)->authorize($owner, $registry->id);
    }

    public function test_authorize_throws_when_boarding_is_ended(): void
    {
        // Per roadmap Q3 ended boarding zostanie później dopuszczone do
        // read-only history view, ale w Fazie 1 = denied (musimy mieć
        // wyraźny entry-point dla "historical access" w fazie 3+).
        $owner = $this->makeUser();
        $stable = $this->makeStableTenant();
        $registry = $this->makeRegistry($owner);
        HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $stable->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_ENDED,
            'started_at' => now()->subYear(),
            'ended_at' => now()->subDays(30),
        ]);

        $this->expectException(AuthorizationException::class);
        app(HorseOwnerStableAccessGate::class)->authorize($owner, $registry->id);
    }

    public function test_try_authorize_returns_null_instead_of_throwing(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $registry = $this->makeRegistry($owner);

        $result = app(HorseOwnerStableAccessGate::class)->tryAuthorize($other, $registry->id);

        $this->assertNull($result);
    }

    public function test_try_authorize_returns_null_for_unknown_horse_id(): void
    {
        $owner = $this->makeUser();

        $result = app(HorseOwnerStableAccessGate::class)
            ->tryAuthorize($owner, '01HZZZZZZZZZZZZZZZZZZZZZ');

        $this->assertNull($result);
    }

    public function test_has_any_active_boarding_true_when_at_least_one_active(): void
    {
        $owner = $this->makeUser();
        $stable = $this->makeStableTenant();
        $registry = $this->makeRegistry($owner);
        $this->makeActiveBoarding($stable, $owner, $registry);

        $this->assertTrue(
            app(HorseOwnerStableAccessGate::class)->hasAnyActiveBoarding($owner)
        );
    }

    public function test_has_any_active_boarding_false_for_owner_without_active_assignments(): void
    {
        $owner = $this->makeUser();

        $this->assertFalse(
            app(HorseOwnerStableAccessGate::class)->hasAnyActiveBoarding($owner)
        );
    }

    public function test_authorize_works_when_owner_has_multiple_horses_and_picks_correct_one(): void
    {
        $owner = $this->makeUser();
        $stable = $this->makeStableTenant();
        $registry1 = $this->makeRegistry($owner, 'Iskra');
        $registry2 = $this->makeRegistry($owner, 'Burza');
        $assignment1 = $this->makeActiveBoarding($stable, $owner, $registry1);
        $assignment2 = $this->makeActiveBoarding($stable, $owner, $registry2);

        $result = app(HorseOwnerStableAccessGate::class)->authorize($owner, $registry2->id);

        $this->assertSame($assignment2->id, $result->id);
        $this->assertNotSame($assignment1->id, $result->id);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'Owner '.uniqid(),
            'email' => 'owner-'.uniqid().'@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function makeRegistry(User $owner, string $name = 'Iskra'): CentralHorseRegistry
    {
        return CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => $name,
        ]);
    }

    private function makeActiveBoarding(Tenant $stable, User $owner, CentralHorseRegistry $registry): HorseBoardingAssignment
    {
        return HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $stable->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => now()->subDays(30),
        ]);
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'gate-st-'.$u,
            'name' => 'Stable '.$u,
            'type' => TenantType::Stable,
            'db_name' => 'gate_st_'.$u,
            'db_username' => 'gate_st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
