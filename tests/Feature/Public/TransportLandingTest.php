<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportReview;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Publiczny landing /transport — hero, embed formularz, Top 10 z featured boost.
 * Auth-aware redirect dla transporter/stable. Patrz docs/TRANSPORT.md §16.
 */
class TransportLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Top 10 cache w TransporterRankingService — czyścimy między testami.
        Cache::flush();
    }

    public function test_guest_sees_landing_with_inquiry_form_and_top_section(): void
    {
        $this->get('/transport')
            ->assertOk()
            ->assertSee('Top 10 przewoźników')
            ->assertSee('Wypełnij formularz', false)
            // Embed formularza — wymagane pola
            ->assertSee('name="customer_name"', false)
            ->assertSee('name="pickup_address"', false)
            ->assertSee('name="dropoff_address"', false)
            ->assertSee('name="horse_count"', false)
            // Honeypot
            ->assertSee('name="website"', false);
    }

    public function test_top_section_shows_verified_transporters_sorted_by_rating(): void
    {
        $high = $this->makeVerifiedTransporter('top-rated', 'Top Rated Co');
        $low = $this->makeVerifiedTransporter('low-rated', 'Low Rated Co');

        $this->makeReview($high, 5);
        $this->makeReview($high, 5);
        $this->makeReview($low, 2);

        $response = $this->get('/transport');
        $body = $response->getContent();

        $posHigh = strpos($body, 'Top Rated Co');
        $posLow = strpos($body, 'Low Rated Co');

        $this->assertNotFalse($posHigh, 'high-rated transporter expected on landing');
        $this->assertNotFalse($posLow, 'low-rated transporter expected on landing');
        $this->assertLessThan($posLow, $posHigh, 'high-rated should appear before low-rated');
    }

    public function test_featured_transporter_appears_before_higher_rated_non_featured(): void
    {
        $featured = $this->makeVerifiedTransporter('featured-co', 'Featured Co');
        $highRated = $this->makeVerifiedTransporter('high-rated', 'High Rated Co');

        // Non-featured ma świetne oceny, featured ma jedną złą — i tak featured wygrywa.
        $this->makeReview($highRated, 5);
        $this->makeReview($highRated, 5);
        $this->makeReview($highRated, 5);
        $this->makeReview($featured, 2);

        $featured->markFeatured();

        $response = $this->get('/transport');
        $body = $response->getContent();

        $posFeatured = strpos($body, 'Featured Co');
        $posHigh = strpos($body, 'High Rated Co');

        $this->assertNotFalse($posFeatured);
        $this->assertNotFalse($posHigh);
        $this->assertLessThan($posHigh, $posFeatured, 'featured transporter should come first');
    }

    public function test_unverified_transporter_does_not_appear_in_top(): void
    {
        $verified = $this->makeVerifiedTransporter('verified-only', 'Verified Only');

        $pending = Tenant::create([
            'slug' => 'pending-co',
            'name' => 'Pending Co',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Pending,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $response = $this->get('/transport');

        $response->assertSee('Verified Only');
        $response->assertDontSee('Pending Co');
    }

    public function test_empty_state_when_no_verified_transporters(): void
    {
        $this->get('/transport')
            ->assertOk()
            ->assertSee('Wkrótce więcej przewoźników');
    }

    public function test_auth_transporter_redirected_to_panel(): void
    {
        [$tenant, $user] = $this->makeTransporterWithOwner();

        $this->actingAs($user)
            ->get('/transport')
            ->assertRedirect('/transport/dashboard');
    }

    public function test_auth_stable_owner_redirected_to_inquiry_with_prefill(): void
    {
        [$stable, $user] = $this->makeStableWithOwner();

        $this->actingAs($user)
            ->get('/transport')
            ->assertRedirect('/transport/zapytanie?stable='.$stable->id);
    }

    public function test_master_admin_without_memberships_sees_landing(): void
    {
        $admin = User::create([
            'email' => 'master@hovera.test',
            'name' => 'Master',
            'password' => bcrypt('secret123'),
        ]);

        $this->actingAs($admin)
            ->get('/transport')
            ->assertOk()
            ->assertSee('Top 10 przewoźników');
    }

    public function test_landing_route_does_not_shadow_inquiry_subroute(): void
    {
        // Critical: /transport (landing) vs /transport/zapytanie (form).
        // Laravel routing musi rezolwować sub-route przed Filament panel'em.
        $this->get('/transport/zapytanie')
            ->assertOk()
            ->assertSee('Zapytanie o transport koni');
    }

    private function makeVerifiedTransporter(string $slug, string $name): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'name' => $name,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeReview(Tenant $tenant, int $rating): TransportReview
    {
        return TransportReview::create([
            'transporter_tenant_id' => $tenant->id,
            'rating' => $rating,
            'comment' => 'OK',
            'status' => 'published',
            'submitted_at' => now(),
            'invite_token_hash' => hash('sha256', Str::random(40)),
            'customer_email_hash' => hash('sha256', 'a@a.pl'),
            'lead_id' => (string) Str::ulid(),
            'response_id' => (string) Str::ulid(),
        ]);
    }

    /**
     * @return array{0:Tenant, 1:User}
     */
    private function makeTransporterWithOwner(): array
    {
        $tenant = $this->makeVerifiedTransporter('owner-co-'.uniqid(), 'Owner Co');
        $user = User::create([
            'email' => 'owner-'.uniqid().'@test.pl',
            'name' => 'Owner',
            'password' => bcrypt('secret123'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$tenant, $user];
    }

    /**
     * @return array{0:Tenant, 1:User}
     */
    private function makeStableWithOwner(): array
    {
        $plan = Plan::firstOrCreate(['code' => 'pro-test'], [
            'audience' => 'stable',
            'name' => 'Pro',
            'currency' => 'PLN',
        ]);

        $stable = Tenant::create([
            'slug' => 'stable-'.uniqid(),
            'name' => 'Stable',
            'type' => TenantType::Stable,
            'plan_id' => $plan->id,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $user = User::create([
            'email' => 'stable-owner-'.uniqid().'@test.pl',
            'name' => 'Stable Owner',
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
}
