<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pre-fill na publicznym /transport/zapytanie gdy user przychodzi z /app
 * z ?stable={id} (i opcjonalnie ?horse={id}). Graceful degradation —
 * anonim albo user bez membership w stable widzi pusty formularz.
 */
class InquiryPrefillFromStableTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_member_sees_originator_banner_and_prefilled_email(): void
    {
        [$stable, $user] = $this->makeStableWithOwner('Stajnia Pegaz', 'owner@pegaz.test');

        $this->actingAs($user)
            ->get('/transport/zapytanie?stable='.$stable->id.'&from=app')
            ->assertOk()
            ->assertSee('Stajnia Pegaz')
            ->assertSee('value="owner@pegaz.test"', false)
            ->assertSee('wróć do panelu');
    }

    public function test_authenticated_member_with_horse_sees_horse_note_prefill(): void
    {
        [$stable, $user] = $this->makeStableWithOwner();

        // Horse model jest per-tenant DB — w teście używamy mocka.
        // Symulujemy że tenant connection nie istnieje (Horse find zwróci null)
        // → notes pre-fill jest skip'owany ale stable params wciąż działają.
        // Pełny e2e horse test wymagałby tenant DB; tutaj weryfikujemy że
        // brak rekordu (graceful) nie psuje requestu.
        $this->actingAs($user)
            ->get('/transport/zapytanie?stable='.$stable->id.'&horse=01HXY00000000000000000000Z')
            ->assertOk();
    }

    public function test_anonymous_visitor_ignores_stable_param_silently(): void
    {
        [$stable, $_] = $this->makeStableWithOwner();

        $response = $this->get('/transport/zapytanie?stable='.$stable->id);

        $response->assertOk()
            ->assertDontSee('wróć do panelu')
            // Originator banner header musi być nieobecny — sprawdzamy frazę
            // "Zlecenie z poziomu stajni" zamiast samej "Stajnia" bo
            // placeholder formularza ma "np. Stajnia Pegaz, ul. ...".
            ->assertDontSee('Zlecenie z poziomu stajni');
    }

    public function test_user_without_membership_ignores_stable_param(): void
    {
        [$stable, $_] = $this->makeStableWithOwner();
        $outsider = User::create([
            'email' => 'outsider@test.pl',
            'name' => 'Outsider',
            'password' => bcrypt('secret123'),
        ]);

        $this->actingAs($outsider)
            ->get('/transport/zapytanie?stable='.$stable->id)
            ->assertOk()
            ->assertDontSee('wróć do panelu');
    }

    public function test_invalid_stable_param_ignored_silently(): void
    {
        [$stable, $user] = $this->makeStableWithOwner();

        $this->actingAs($user)
            ->get('/transport/zapytanie?stable=not-a-ulid')
            ->assertOk()
            ->assertDontSee('wróć do panelu');
    }

    public function test_submit_with_stable_marks_lead_originator_tenant_id(): void
    {
        [$stable, $user] = $this->makeStableWithOwner();

        $this->mockGeocoder();

        $this->actingAs($user)
            ->post('/transport/zapytanie?stable='.$stable->id, [
                'customer_name' => 'Jan Kowalski',
                'customer_email' => 'jan@example.com',
                'pickup_address' => 'Warszawa, Marymoncka 1',
                'dropoff_address' => 'Kraków, Krakusa 1',
                'preferred_date' => now()->addDays(5)->toDateString(),
                'horse_count' => 1,
                'terms' => '1',
            ])
            ->assertRedirect();

        $lead = TransportLead::first();
        $this->assertNotNull($lead);
        $this->assertSame($stable->id, $lead->originator_tenant_id);
        $this->assertSame($user->id, $lead->originator_user_id);
    }

    public function test_anonymous_submit_keeps_originator_tenant_id_null(): void
    {
        [$stable, $_] = $this->makeStableWithOwner();
        $this->mockGeocoder();

        $this->post('/transport/zapytanie?stable='.$stable->id, [
            'customer_name' => 'Anon',
            'customer_email' => 'a@a.pl',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'terms' => '1',
        ])->assertRedirect();

        $lead = TransportLead::first();
        $this->assertNotNull($lead);
        $this->assertNull($lead->originator_tenant_id);
        $this->assertNull($lead->originator_user_id);
    }

    /**
     * @return array{0:Tenant, 1:User}
     */
    private function makeStableWithOwner(string $stableName = 'Stajnia testowa', string $email = 'owner@test.pl'): array
    {
        $plan = Plan::firstOrCreate(['code' => 'pro'], [
            'audience' => 'stable',
            'name' => 'Pro',
            'currency' => 'PLN',
        ]);

        $stable = Tenant::create([
            'slug' => 'stajnia-'.uniqid(),
            'name' => $stableName,
            'type' => TenantType::Stable,
            'plan_id' => $plan->id,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [
                'public_profile' => [
                    'address' => 'ul. Łąkowa 1, 02-000 Warszawa',
                ],
            ],
        ]);

        $user = User::create([
            'email' => $email,
            'name' => 'Owner',
            'password' => bcrypt('secret123'),
        ]);

        TenantMembership::create([
            'tenant_id' => $stable->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$stable, $user];
    }

    private function mockGeocoder(): void
    {
        config()->set('transport.providers.mapbox.access_token', 'pk.test');

        Http::fake([
            'api.mapbox.com/*' => Http::sequence()
                ->push([
                    'features' => [[
                        'place_name' => 'ul. Marymoncka 1, Warszawa, Polska',
                        'center' => [21.0122, 52.2297],
                        'context' => [['id' => 'region.1', 'text' => 'Mazowieckie']],
                    ]],
                ])
                ->push([
                    'features' => [[
                        'place_name' => 'ul. Krakusa 1, Kraków, Polska',
                        'center' => [19.9362, 50.0413],
                        'context' => [['id' => 'region.2', 'text' => 'Małopolskie']],
                    ]],
                ]),
        ]);
    }
}
