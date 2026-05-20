<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Transport\Geocoding\Data\GeocodedAddress;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Leads\LeadDispatcher;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\TenantType;
use App\Models\Central\CentralHorseRegistry;
use App\Models\Central\HorseBoardingAssignment;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * PR 7 — stable zamawia "for boarder". Lead z formu /transport/zapytanie
 * dostaje teraz client_type / client_user_id / created_by_tenant_id na
 * podstawie kontekstu originator'a (?stable=...) + wyboru "Klient zlecenia"
 * (boarder picker).
 *
 * Pokrywa:
 *  - lead bez stable context → client_type='anonymous'
 *  - lead od stable bez boarder pick → client_type='stable',
 *    created_by_tenant_id=stable.id
 *  - lead od stable z boarder pick → client_type='owner',
 *    client_user_id=boarder.owner_user_id
 *  - boarder z innej stajni → graceful fallback do client_type='stable'
 *  - ended boarding → graceful fallback do client_type='stable'
 *  - active boarders są w form data (view rendering)
 */
class TransportLeadClientTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $stableUser;

    private Tenant $stable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGeocoder();
        $this->mockDispatcher();

        $this->stableUser = User::create([
            'name' => 'Stable Owner',
            'email' => 'stable-'.uniqid().'@example.com',
            'password' => bcrypt('x'),
        ]);
        $this->stable = $this->makeStableTenant();
        TenantMembership::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $this->stable->id,
            'user_id' => $this->stableUser->id,
            'role' => 'owner',
        ]);
    }

    public function test_anonymous_submission_sets_client_type_anonymous(): void
    {
        $response = $this->post(route('public.transport.inquiry.submit'), $this->validPayload());

        $response->assertRedirectToRoute('public.transport.inquiry.thanks', ['lead' => TransportLead::query()->first()->id]);

        $lead = TransportLead::query()->first();
        $this->assertSame(TransportLead::CLIENT_TYPE_ANONYMOUS, $lead->client_type);
        $this->assertNull($lead->client_user_id);
        $this->assertNull($lead->created_by_tenant_id);
    }

    public function test_stable_submission_without_boarder_sets_client_type_stable(): void
    {
        $response = $this->actingAs($this->stableUser)
            ->post(
                route('public.transport.inquiry.submit').'?stable='.$this->stable->id,
                $this->validPayload(['stable' => $this->stable->id])
            );

        $response->assertRedirect();

        $lead = TransportLead::query()->first();
        $this->assertSame(TransportLead::CLIENT_TYPE_STABLE, $lead->client_type);
        $this->assertNull($lead->client_user_id);
        $this->assertSame($this->stable->id, $lead->created_by_tenant_id);
    }

    public function test_stable_submission_with_boarder_sets_client_type_owner(): void
    {
        $owner = $this->makeUser();
        $assignment = $this->makeActiveBoarding($this->stable, $owner);

        $response = $this->actingAs($this->stableUser)
            ->post(
                route('public.transport.inquiry.submit').'?stable='.$this->stable->id,
                $this->validPayload([
                    'stable' => $this->stable->id,
                    'client_for' => 'boarder:'.$assignment->id,
                ])
            );

        $response->assertRedirect();

        $lead = TransportLead::query()->first();
        $this->assertSame(TransportLead::CLIENT_TYPE_OWNER, $lead->client_type);
        $this->assertSame($owner->id, $lead->client_user_id);
        $this->assertSame($this->stable->id, $lead->created_by_tenant_id);
    }

    public function test_boarder_from_different_stable_falls_back_to_stable(): void
    {
        // Boarding assignment należy do INNEJ stajni — input próbuje
        // hijack'ować boarder'a. Graceful fallback do client_type='stable'.
        $owner = $this->makeUser();
        $otherStable = $this->makeStableTenant();
        $assignment = $this->makeActiveBoarding($otherStable, $owner);

        $response = $this->actingAs($this->stableUser)
            ->post(
                route('public.transport.inquiry.submit').'?stable='.$this->stable->id,
                $this->validPayload([
                    'stable' => $this->stable->id,
                    'client_for' => 'boarder:'.$assignment->id,
                ])
            );

        $response->assertRedirect();

        $lead = TransportLead::query()->first();
        $this->assertSame(TransportLead::CLIENT_TYPE_STABLE, $lead->client_type);
        $this->assertNull($lead->client_user_id);
    }

    public function test_ended_boarding_falls_back_to_stable(): void
    {
        $owner = $this->makeUser();
        $ended = $this->makeActiveBoarding($this->stable, $owner);
        $ended->update(['status' => HorseBoardingAssignment::STATUS_ENDED, 'ended_at' => now()]);

        $response = $this->actingAs($this->stableUser)
            ->post(
                route('public.transport.inquiry.submit').'?stable='.$this->stable->id,
                $this->validPayload([
                    'stable' => $this->stable->id,
                    'client_for' => 'boarder:'.$ended->id,
                ])
            );

        $response->assertRedirect();

        $lead = TransportLead::query()->first();
        $this->assertSame(TransportLead::CLIENT_TYPE_STABLE, $lead->client_type);
    }

    public function test_show_view_includes_active_boarders_for_stable(): void
    {
        $owner = $this->makeUser();
        $assignment = $this->makeActiveBoarding($this->stable, $owner);

        // Drugi boarder, ale ended — nie powinien się pojawić w form'ie.
        $oldOwner = $this->makeUser();
        $ended = $this->makeActiveBoarding($this->stable, $oldOwner);
        $ended->update(['status' => HorseBoardingAssignment::STATUS_ENDED]);

        $response = $this->actingAs($this->stableUser)
            ->get(route('public.transport.inquiry').'?stable='.$this->stable->id);

        $response->assertOk();
        $response->assertSee('boarder:'.$assignment->id, escape: false);
        $response->assertDontSee('boarder:'.$ended->id, escape: false);
        // Picker visible — sprawdzamy że select istnieje.
        $response->assertSee('name="client_for"', escape: false);
    }

    public function test_show_view_omits_picker_when_no_boarders(): void
    {
        // Bez aktywnych boardings — picker nie powinien się renderować.
        $response = $this->actingAs($this->stableUser)
            ->get(route('public.transport.inquiry').'?stable='.$this->stable->id);

        $response->assertOk();
        $response->assertDontSee('name="client_for"', escape: false);
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Jan Test',
            'customer_email' => 'jan-'.uniqid().'@example.com',
            'customer_phone' => '+48123456789',
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ], $overrides);
    }

    private function makeActiveBoarding(Tenant $stable, User $owner): HorseBoardingAssignment
    {
        $registry = CentralHorseRegistry::create([
            'id' => (string) Str::ulid(),
            'primary_owner_user_id' => $owner->id,
            'name' => 'Iskra',
        ]);

        return HorseBoardingAssignment::create([
            'id' => (string) Str::ulid(),
            'central_horse_id' => $registry->id,
            'stable_tenant_id' => $stable->id,
            'owner_user_id' => $owner->id,
            'status' => HorseBoardingAssignment::STATUS_ACTIVE,
            'started_at' => now()->subDays(30),
        ]);
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'User '.uniqid(),
            'email' => 'u-'.uniqid().'@example.com',
            'password' => bcrypt('x'),
        ]);
    }

    private function makeStableTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'st-'.$u,
            'name' => 'Stable',
            'type' => TenantType::Stable,
            'db_name' => 'st_'.$u,
            'db_username' => 'st_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function mockGeocoder(): void
    {
        $this->mock(MapboxGeocoder::class, function (MockInterface $m) {
            $m->shouldReceive('geocode')->andReturnUsing(function (string $query) {
                return match ($query) {
                    'Warszawa' => new GeocodedAddress(
                        displayName: 'Warszawa, Polska',
                        coords: new Coords(52.2297, 21.0122),
                        countryCode: 'PL',
                        voivodeship: 'mazowieckie',
                    ),
                    'Kraków' => new GeocodedAddress(
                        displayName: 'Kraków, Polska',
                        coords: new Coords(50.0647, 19.9450),
                        countryCode: 'PL',
                        voivodeship: 'małopolskie',
                    ),
                    default => throw new \RuntimeException('Unexpected geocode query: '.$query),
                };
            });
        });
    }

    private function mockDispatcher(): void
    {
        $this->mock(LeadDispatcher::class, function (MockInterface $m) {
            $m->shouldReceive('dispatch')->andReturn(['notified' => 0, 'transporter_ids' => []]);
        });
    }
}
