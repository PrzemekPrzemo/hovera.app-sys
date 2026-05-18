<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Reviews;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\TransportReview;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransportReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_form_with_valid_token(): void
    {
        [, $review, $rawToken] = $this->makeInvite();

        $this->get('/transport/review/'.$rawToken)
            ->assertOk()
            ->assertSee('rating', false);
    }

    public function test_unknown_token_returns_expired_page(): void
    {
        $this->makeInvite();

        $this->get('/transport/review/'.str_repeat('z', 48))
            ->assertStatus(410);
    }

    public function test_short_token_blocked_by_route_regex(): void
    {
        $this->get('/transport/review/short')->assertNotFound();
    }

    public function test_submit_publishes_review_and_redirects_to_thanks(): void
    {
        [$tenant, $review, $rawToken] = $this->makeInvite();

        $this->post('/transport/review/'.$rawToken, [
            'rating' => 5,
            'comment' => 'Świetny transport, koń dotarł spokojny.',
        ])->assertRedirect('/transport/review/dziekujemy');

        $fresh = TransportReview::find($review->id);
        $this->assertSame(5, $fresh->rating);
        $this->assertSame('Świetny transport, koń dotarł spokojny.', $fresh->comment);
        $this->assertSame('published', $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
    }

    public function test_token_works_once_then_shows_already_submitted_page(): void
    {
        [, $review, $rawToken] = $this->makeInvite();

        $this->post('/transport/review/'.$rawToken, ['rating' => 4])->assertRedirect();

        // Druga próba GET — friendly "już zostawiłeś opinię" page, 200.
        $this->get('/transport/review/'.$rawToken)
            ->assertOk()
            ->assertSee('Już zostawiłeś opinię');
    }

    public function test_second_submit_redirects_to_thanks_without_changing_record(): void
    {
        [, $review, $rawToken] = $this->makeInvite();

        $this->post('/transport/review/'.$rawToken, ['rating' => 5, 'comment' => 'pierwszy']);
        $this->post('/transport/review/'.$rawToken, ['rating' => 1, 'comment' => 'hijack'])
            ->assertRedirect('/transport/review/dziekujemy');

        $fresh = TransportReview::find($review->id);
        $this->assertSame(5, $fresh->rating, 'submit po pierwszym nie może nadpisać oceny');
        $this->assertSame('pierwszy', $fresh->comment);
    }

    public function test_expired_token_returns_410(): void
    {
        [, $review, $rawToken] = $this->makeInvite(expiresAt: now()->subDay());

        $this->get('/transport/review/'.$rawToken)
            ->assertStatus(410);
    }

    public function test_rating_validation_required_min_max(): void
    {
        [, , $rawToken] = $this->makeInvite();

        $this->post('/transport/review/'.$rawToken, [])
            ->assertSessionHasErrors(['rating']);

        $this->post('/transport/review/'.$rawToken, ['rating' => 6])
            ->assertSessionHasErrors(['rating']);
    }

    public function test_comment_truncated_to_2000_chars(): void
    {
        [, $review, $rawToken] = $this->makeInvite();

        $long = str_repeat('a', 3000);
        $this->post('/transport/review/'.$rawToken, ['rating' => 3, 'comment' => $long])
            ->assertSessionHasErrors(['comment']);
    }

    /**
     * @return array{0:Tenant, 1:TransportReview, 2:string}
     */
    private function makeInvite(?CarbonInterface $expiresAt = null): array
    {
        $tenant = Tenant::create([
            'slug' => 'konie-trans-'.Str::random(4),
            'name' => 'Konie Trans',
            'type' => TenantType::Transporter,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'pickup_address' => 'Warszawa',
            'pickup_lat' => 52.28, 'pickup_lng' => 20.99,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Kraków',
            'dropoff_lat' => 50.04, 'dropoff_lng' => 19.93,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => now()->subDays(20)->toDateString(),
            'horse_count' => 1,
            'status' => 'accepted',
            'expires_at' => now()->subDays(10),
            'originator_email' => 'klient@example.com',
            'originator_name' => 'Jan Kowalski',
        ]);

        $response = TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $tenant->id,
            'price_net' => 1000, 'price_gross' => 1230, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date,
            'status' => 'accepted',
        ]);

        $rawToken = Str::random(48);
        $review = TransportReview::create([
            'transporter_tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'response_id' => $response->id,
            'invite_token_hash' => hash('sha256', $rawToken),
            'invite_sent_at' => now()->subDay(),
            'invite_expires_at' => $expiresAt ?? now()->addDays(30),
            'customer_name' => 'Jan Kowalski',
            'customer_email_hash' => hash('sha256', 'klient@example.com'),
            'customer_email_redacted' => 'k***@example.com',
            'status' => 'invited',
        ]);

        return [$tenant, $review, $rawToken];
    }
}
