<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Reviews;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\TransportReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransportReviewAggregateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_aggregate_returns_zeros_for_no_reviews(): void
    {
        $tenant = $this->makeTenant();

        $agg = TransportReview::aggregateFor($tenant);

        $this->assertSame(0, $agg['count']);
        $this->assertSame(0.0, $agg['average']);
        $this->assertSame([5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0], $agg['distribution']);
    }

    public function test_aggregate_only_counts_published(): void
    {
        $tenant = $this->makeTenant();
        $this->makePublishedReview($tenant, rating: 5);
        $this->makePublishedReview($tenant, rating: 4);
        $this->makePublishedReview($tenant, rating: 5);
        $this->makeFlaggedReview($tenant, rating: 1);   // flagged → ignored
        $this->makeInvitedReview($tenant);              // brak rating → ignored

        Cache::flush();
        $agg = TransportReview::aggregateFor($tenant);

        $this->assertSame(3, $agg['count']);
        $this->assertEqualsWithDelta(4.67, $agg['average'], 0.01);
        $this->assertSame(2, $agg['distribution'][5]);
        $this->assertSame(1, $agg['distribution'][4]);
        $this->assertSame(0, $agg['distribution'][1]);
    }

    public function test_aggregate_is_cached_and_can_be_busted(): void
    {
        $tenant = $this->makeTenant();
        $this->makePublishedReview($tenant, rating: 5);

        // Pierwszy call zapełnia cache.
        $first = TransportReview::aggregateFor($tenant);
        $this->assertSame(1, $first['count']);

        // Dodajemy nowy review bezpośrednio (omijamy cache busting controllera).
        $this->makePublishedReview($tenant, rating: 3);

        $cached = TransportReview::aggregateFor($tenant);
        $this->assertSame(1, $cached['count'], 'cache trzyma starą wartość');

        TransportReview::forgetAggregateCache($tenant->id);
        $fresh = TransportReview::aggregateFor($tenant);
        $this->assertSame(2, $fresh['count'], 'cache zbusted po forgetAggregateCache');
    }

    public function test_aggregate_is_per_transporter(): void
    {
        $tenantA = $this->makeTenant('aaa');
        $tenantB = $this->makeTenant('bbb');

        $this->makePublishedReview($tenantA, rating: 5);
        $this->makePublishedReview($tenantA, rating: 5);
        $this->makePublishedReview($tenantB, rating: 1);

        $aggA = TransportReview::aggregateFor($tenantA);
        $aggB = TransportReview::aggregateFor($tenantB);

        $this->assertSame(2, $aggA['count']);
        $this->assertSame(5.0, $aggA['average']);
        $this->assertSame(1, $aggB['count']);
        $this->assertSame(1.0, $aggB['average']);
    }

    public function test_redact_customer_name_removes_last_name(): void
    {
        $this->assertSame('Jan K.', TransportReview::redactCustomerName('Jan Kowalski'));
        $this->assertSame('Anna', TransportReview::redactCustomerName('Anna'));
        $this->assertSame('Jan-Maria K.', TransportReview::redactCustomerName('Jan-Maria Kowalski'));
    }

    public function test_redact_email_keeps_first_letter_and_domain(): void
    {
        $this->assertSame('j***@example.com', TransportReview::redactEmail('jan@example.com'));
        $this->assertSame('***', TransportReview::redactEmail('no-at-sign'));
    }

    private function makeTenant(?string $suffix = null): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.($suffix ?? uniqid()),
            'name' => 'Konie Trans',
            'type' => TenantType::Transporter,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makePublishedReview(Tenant $tenant, int $rating): TransportReview
    {
        return $this->makeReview($tenant, [
            'rating' => $rating,
            'status' => 'published',
            'submitted_at' => now(),
        ]);
    }

    private function makeFlaggedReview(Tenant $tenant, int $rating): TransportReview
    {
        return $this->makeReview($tenant, [
            'rating' => $rating,
            'status' => 'flagged',
            'submitted_at' => now(),
        ]);
    }

    private function makeInvitedReview(Tenant $tenant): TransportReview
    {
        return $this->makeReview($tenant, [
            'rating' => null,
            'status' => 'invited',
            'submitted_at' => null,
        ]);
    }

    private function makeReview(Tenant $tenant, array $overrides): TransportReview
    {
        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'pickup_address' => 'a', 'pickup_lat' => 0, 'pickup_lng' => 0, 'pickup_voivodeship' => 'maz',
            'dropoff_address' => 'b', 'dropoff_lat' => 0, 'dropoff_lng' => 0, 'dropoff_voivodeship' => 'mal',
            'preferred_date' => now()->subDays(30)->toDateString(),
            'horse_count' => 1,
            'status' => 'accepted',
            'expires_at' => now()->subDays(20),
            'originator_email' => 'k@example.com',
        ]);
        $response = TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $tenant->id,
            'price_net' => 1, 'price_gross' => 1, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date,
            'status' => 'accepted',
        ]);

        return TransportReview::create(array_merge([
            'transporter_tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'response_id' => $response->id,
            'invite_token_hash' => hash('sha256', Str::random(48).Str::random(8)),
            'invite_sent_at' => now()->subDays(5),
            'invite_expires_at' => now()->addDays(25),
            'customer_email_hash' => hash('sha256', 'k@example.com'),
            'customer_email_redacted' => 'k***@example.com',
        ], $overrides));
    }
}
