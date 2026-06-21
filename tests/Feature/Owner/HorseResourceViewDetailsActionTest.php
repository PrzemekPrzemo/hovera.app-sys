<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Filament\Owner\Resources\HorseResource;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\OwnerHorse;
use Filament\Tables\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PR O2 — row action "Szczegóły boardingu" na HorseResource. Łączy
 * resource list z istniejącym HorseDetail page'em (cross-tenant view).
 *
 * Visibility gated przez HorseOwnerStableAccessGate:
 *   - brak central_horse_id → false
 *   - central_horse_id istnieje, brak active assignment → false
 *   - central_horse_id + active assignment dla Auth user'a → true
 */
class HorseResourceViewDetailsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_hidden_when_horse_has_no_central_horse_id(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $horse = new OwnerHorse([
            'id' => (string) Str::ulid(),
            'name' => 'Local',
        ]);
        $horse->central_horse_id = null;

        $action = $this->resolveAction($horse);

        $this->assertFalse($action->isVisible());
    }

    public function test_action_hidden_when_no_active_boarding(): void
    {
        $user = $this->makeUser();
        $registry = $this->makeRegistry($user);

        $this->actingAs($user);

        $horse = new OwnerHorse([
            'id' => (string) Str::ulid(),
            'name' => 'Pending',
        ]);
        $horse->central_horse_id = $registry->id;

        $action = $this->resolveAction($horse);

        $this->assertFalse($action->isVisible());
    }

    public function test_action_visible_and_url_correct_when_active_boarding_exists(): void
    {
        $user = $this->makeUser();
        $stable = $this->makeStableTenant();
        $registry = $this->makeRegistry($user);
        $this->makeActiveAssignment($registry, $stable, $user);

        $this->actingAs($user);

        $horse = new OwnerHorse([
            'id' => (string) Str::ulid(),
            'name' => 'Active',
        ]);
        $horse->central_horse_id = $registry->id;

        $action = $this->resolveAction($horse);

        $this->assertTrue($action->isVisible());
        $this->assertSame(
            url('/owner/horses/'.$registry->id.'/details'),
            $action->getUrl()
        );
    }

    public function test_action_hidden_for_other_user(): void
    {
        $ownerA = $this->makeUser();
        $ownerB = $this->makeUser();
        $stable = $this->makeStableTenant();
        $registry = $this->makeRegistry($ownerA);
        $this->makeActiveAssignment($registry, $stable, $ownerA);

        $this->actingAs($ownerB);

        $horse = new OwnerHorse([
            'id' => (string) Str::ulid(),
            'name' => 'NotYours',
        ]);
        $horse->central_horse_id = $registry->id;

        $action = $this->resolveAction($horse);

        $this->assertFalse($action->isVisible());
    }

    private function resolveAction(OwnerHorse $horse): Action
    {
        $reflection = new ReflectionMethod(HorseResource::class, 'viewBoardingDetailsAction');
        $reflection->setAccessible(true);
        /** @var Action $action */
        $action = $reflection->invoke(null);
        $action->record($horse);

        return $action;
    }

    private function makeUser(): User
    {
        return User::create([
            'email' => 'owner-'.uniqid().'@example.test',
            'name' => 'Owner '.uniqid(),
            'password' => bcrypt('secret'),
        ]);
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();
        $tenant = new Tenant([
            'slug' => 'stable-'.$u,
            'name' => 'Stable '.$u,
            'type' => 'stable',
            'db_name' => 'st_'.$u,
            'db_username' => 'st_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return $tenant;
    }

    private function makeRegistry(User $owner): CentralHorseRegistry
    {
        return CentralHorseRegistry::create([
            'name' => 'Iskra',
            'primary_owner_user_id' => $owner->id,
        ]);
    }

    private function makeActiveAssignment(
        CentralHorseRegistry $registry,
        Tenant $stable,
        User $owner,
    ): HorseBoardingAssignment {
        return HorseBoardingAssignment::create([
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $stable->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
    }
}
