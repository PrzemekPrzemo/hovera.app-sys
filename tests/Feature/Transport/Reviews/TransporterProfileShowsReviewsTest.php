<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Reviews;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\TransportReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransporterProfileShowsReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_profile_hides_reviews_section_when_no_reviews(): void
    {
        $this->makeVerifiedTenant('zero-opinii');

        $this->get('/t/zero-opinii')
            ->assertOk()
            ->assertDontSee('Opinie klientów', false);
    }

    public function test_profile_shows_reviews_section_when_at_least_one_published(): void
    {
        $tenant = $this->makeVerifiedTenant('z-opiniami');
        $this->makePublishedReview($tenant, rating: 5, comment: 'Bardzo polecam, koń dotarł spokojnie.');

        $response = $this->get('/t/z-opiniami');

        $response->assertOk();
        $response->assertSee('Opinie klientów', false);
        $response->assertSee('Bardzo polecam', false);
        $response->assertSee('Zweryfikowana opinia', false);
    }

    public function test_profile_only_counts_published_reviews_in_aggregate(): void
    {
        $tenant = $this->makeVerifiedTenant('aggregate-test');
        $this->makePublishedReview($tenant, rating: 5);
        $this->makePublishedReview($tenant, rating: 5);
        $this->makePublishedReview($tenant, rating: 3);

        // Flagged nie powinien wpływać na średnią
        $review = $this->makePublishedReview($tenant, rating: 1);
        $review->forceFill(['status' => 'flagged'])->save();
        TransportReview::forgetAggregateCache($tenant->id);

        $response = $this->get('/t/aggregate-test');

        $response->assertOk();
        // 5,5,3 → 4.33
        $response->assertSee('4,3', false);
    }

    public function test_profile_redacts_customer_last_name(): void
    {
        $tenant = $this->makeVerifiedTenant('redact');
        $this->makePublishedReview($tenant, rating: 5, customerName: 'Jan Kowalski', comment: 'OK');

        $this->get('/t/redact')
            ->assertOk()
            ->assertSee('Jan K.', false)
            ->assertDontSee('Kowalski', false);
    }

    public function test_profile_shows_transporter_response_when_set(): void
    {
        $tenant = $this->makeVerifiedTenant('z-odpowiedzia');
        $review = $this->makePublishedReview($tenant, rating: 4, comment: 'Bylo OK');
        $review->forceFill([
            'transporter_response' => 'Dziękujemy za miłą opinię!',
            'transporter_responded_at' => now(),
        ])->save();
        TransportReview::forgetAggregateCache($tenant->id);
        Cache::flush();

        $this->get('/t/z-odpowiedzia')
            ->assertOk()
            ->assertSee('Dziękujemy za miłą opinię', false);
    }

    private function makeVerifiedTenant(string $slug): Tenant
    {
        return Tenant::create([
            'slug' => $slug,
            'name' => 'Firma '.$slug,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.str_replace('-', '_', $slug),
            'db_username' => 't_'.str_replace('-', '_', $slug),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makePublishedReview(Tenant $tenant, int $rating, ?string $comment = null, string $customerName = 'Jan Kowalski'): TransportReview
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

        return TransportReview::create([
            'transporter_tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'response_id' => $response->id,
            'invite_token_hash' => hash('sha256', Str::random(48).Str::random(8)),
            'invite_sent_at' => now()->subDays(5),
            'invite_expires_at' => now()->addDays(25),
            'rating' => $rating,
            'comment' => $comment,
            'customer_name' => $customerName,
            'customer_email_hash' => hash('sha256', 'k@example.com'),
            'customer_email_redacted' => 'k***@example.com',
            'status' => 'published',
            'submitted_at' => now()->subDay(),
        ]);
    }
}
