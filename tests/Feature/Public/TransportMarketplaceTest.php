<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PR 8 — publiczna giełda otwartych zleceń pod /transport/marketplace.
 *
 * Pokrywa:
 *  - listing pokazuje TYLKO open + not expired + future date
 *  - filtr województwa (pickup OR dropoff)
 *  - filtr within_days (preferred_date <= today + N)
 *  - filtr min_horses (horse_count >= N)
 *  - privacy: pełen adres NIE jest w widoku, tylko województwa
 *  - claim flow: verified transporter dostaje dispatch row + redirect
 *  - claim odmawia gdy user nie ma verified transportera
 *  - claim idempotent — drugi click nie tworzy duplikatu
 */
class TransportMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_shows_open_leads_only(): void
    {
        $open = $this->makeLead(['status' => 'open', 'pickup_voivodeship' => 'mazowieckie']);
        $this->makeLead(['status' => 'quoted', 'pickup_voivodeship' => 'mazowieckie']);
        $this->makeLead(['status' => 'accepted', 'pickup_voivodeship' => 'mazowieckie']);
        $this->makeLead(['status' => 'expired', 'pickup_voivodeship' => 'mazowieckie']);

        $response = $this->get('/transport/marketplace');

        $response->assertOk();
        $response->assertSee($open->pickup_voivodeship, escape: false);
        // 1 open lead, not 4 — używamy trans_choice którego nie sprawdzamy
        // wprost (zależy od locale), zamiast tego liczymy karty leadów.
        $this->assertEquals(1, substr_count($response->getContent(), '/claim'));
    }

    public function test_marketplace_filters_by_voivodeship(): void
    {
        $this->makeLead(['pickup_voivodeship' => 'mazowieckie', 'dropoff_voivodeship' => 'małopolskie']);
        $this->makeLead(['pickup_voivodeship' => 'pomorskie', 'dropoff_voivodeship' => 'wielkopolskie']);
        // Match przez dropoff:
        $this->makeLead(['pickup_voivodeship' => 'śląskie', 'dropoff_voivodeship' => 'mazowieckie']);

        $response = $this->get('/transport/marketplace?voivodeship=mazowieckie');

        $response->assertOk();
        $this->assertEquals(2, substr_count($response->getContent(), '/claim'));
    }

    public function test_marketplace_filters_by_within_days(): void
    {
        $this->makeLead(['preferred_date' => Carbon::today()->addDays(3)->toDateString()]);
        $this->makeLead(['preferred_date' => Carbon::today()->addDays(10)->toDateString()]);
        $this->makeLead(['preferred_date' => Carbon::today()->addDays(20)->toDateString()]);

        $response = $this->get('/transport/marketplace?within_days=7');

        $response->assertOk();
        $this->assertEquals(1, substr_count($response->getContent(), '/claim'),
            'within_days=7 should match only the lead with preferred_date +3 days');
    }

    public function test_marketplace_filters_by_min_horses(): void
    {
        $this->makeLead(['horse_count' => 1]);
        $this->makeLead(['horse_count' => 3]);
        $this->makeLead(['horse_count' => 5]);

        $response = $this->get('/transport/marketplace?min_horses=3');

        $response->assertOk();
        $this->assertEquals(2, substr_count($response->getContent(), '/claim'));
    }

    public function test_marketplace_hides_expired_leads(): void
    {
        // expires_at in past
        $this->makeLead(['expires_at' => now()->subDay()]);
        // preferred_date in past
        $this->makeLead(['preferred_date' => Carbon::yesterday()->toDateString()]);
        // valid one
        $this->makeLead();

        $response = $this->get('/transport/marketplace');

        $response->assertOk();
        $this->assertEquals(1, substr_count($response->getContent(), '/claim'));
    }

    public function test_marketplace_does_not_leak_full_address(): void
    {
        $lead = $this->makeLead([
            'pickup_address' => 'ul. Tajemnicza 42, Warszawa',
            'dropoff_address' => 'Hipodrom Sopot, Polanki 91',
        ]);

        $response = $this->get('/transport/marketplace');

        $response->assertOk();
        $content = $response->getContent();

        // Privacy: pełen adres NIE może wyciec w publicznym listingu.
        $this->assertStringNotContainsString('ul. Tajemnicza 42', $content);
        $this->assertStringNotContainsString('Hipodrom Sopot', $content);
        // Tylko województwa są publiczne:
        $this->assertStringContainsString($lead->pickup_voivodeship, $content);
    }

    public function test_verified_transporter_can_claim_lead(): void
    {
        $lead = $this->makeLead();
        [$user, $tenant] = $this->makeVerifiedTransporter();

        $response = $this->actingAs($user)
            ->post('/transport/marketplace/'.$lead->id.'/claim');

        $response->assertRedirect('/transport/leads/'.$lead->id);

        $this->assertDatabaseHas('transport_lead_dispatch', [
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $tenant->id,
        ]);
    }

    public function test_claim_is_idempotent(): void
    {
        $lead = $this->makeLead();
        [$user, $tenant] = $this->makeVerifiedTransporter();

        // Drugie claim — nie powinno crashować na unique constraint.
        $this->actingAs($user)->post('/transport/marketplace/'.$lead->id.'/claim');
        $this->actingAs($user)->post('/transport/marketplace/'.$lead->id.'/claim')
            ->assertRedirect('/transport/leads/'.$lead->id);

        $this->assertSame(1, TransportLeadDispatch::query()
            ->where('lead_id', $lead->id)
            ->where('transporter_tenant_id', $tenant->id)
            ->count());
    }

    public function test_claim_redirects_to_login_for_anonymous(): void
    {
        $lead = $this->makeLead();

        $response = $this->post('/transport/marketplace/'.$lead->id.'/claim');

        $response->assertRedirect('/transport/login');
    }

    public function test_claim_blocks_non_verified_transporter(): void
    {
        $lead = $this->makeLead();
        // User z aktywnym membership ale tenant nie jest verified
        $user = User::create([
            'name' => 'Test',
            'email' => 'unverified-'.uniqid().'@example.com',
            'password' => bcrypt('x'),
        ]);
        $tenant = Tenant::create([
            'slug' => 'unverif-'.uniqid(),
            'name' => 'Unverified',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Pending,
            'db_name' => 'u_'.uniqid(),
            'db_username' => 'u_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
        TenantMembership::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($user)->post('/transport/marketplace/'.$lead->id.'/claim');

        $response->assertRedirect(route('public.transporters.directory'));
        $this->assertDatabaseMissing('transport_lead_dispatch', [
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $tenant->id,
        ]);
    }

    public function test_claim_rejects_already_expired_lead(): void
    {
        $lead = $this->makeLead(['expires_at' => now()->subHour()]);
        [$user] = $this->makeVerifiedTransporter();

        $response = $this->actingAs($user)->post('/transport/marketplace/'.$lead->id.'/claim');

        $response->assertRedirect(route('public.transport.marketplace'));
        $this->assertSame(0, TransportLeadDispatch::query()
            ->where('lead_id', $lead->id)
            ->count());
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeLead(array $overrides = []): TransportLead
    {
        return TransportLead::create(array_merge([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'originator_name' => 'Anon',
            'originator_email' => 'anon-'.uniqid().'@example.com',
            'pickup_address' => 'PickAddr',
            'pickup_lat' => 52.0,
            'pickup_lng' => 21.0,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'DropAddr',
            'dropoff_lat' => 50.0,
            'dropoff_lng' => 19.0,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'horse_count' => 1,
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ], $overrides));
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeVerifiedTransporter(): array
    {
        $user = User::create([
            'name' => 'Verified',
            'email' => 'verified-'.uniqid().'@example.com',
            'password' => bcrypt('x'),
        ]);
        $tenant = Tenant::create([
            'slug' => 'v-'.uniqid(),
            'name' => 'Verified Carrier',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 'v_'.uniqid(),
            'db_username' => 'v_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
        TenantMembership::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        return [$user, $tenant];
    }
}
