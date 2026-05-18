<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Reviews;

use App\Domain\Transport\Notifications\TransportReviewInviteNotification;
use App\Domain\Transport\Reviews\TransportReviewInviteService;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\TransportReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransportReviewInviteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_creates_one_invite_per_accepted_response_14d_past(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $lead = $this->makeLead(['originator_email' => 'klient@example.com', 'originator_name' => 'Jan Kowalski'], daysAgo: 20);
        $response = $this->makeResponse($lead, $tenant, status: 'accepted');

        $sent = app(TransportReviewInviteService::class)->dispatchPendingInvites();

        $this->assertSame(1, $sent);
        $this->assertSame(1, TransportReview::count());
        $row = TransportReview::first();
        $this->assertSame('invited', $row->status);
        $this->assertSame($lead->id, $row->lead_id);
        $this->assertSame($response->id, $row->response_id);
        $this->assertSame('k***@example.com', $row->customer_email_redacted);
        $this->assertSame(64, strlen($row->invite_token_hash));
        $this->assertNotNull($row->invite_expires_at);
        Notification::assertSentOnDemand(TransportReviewInviteNotification::class);
    }

    public function test_dispatch_is_idempotent_on_rerun(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $lead = $this->makeLead(['originator_email' => 'k@example.com'], daysAgo: 20);
        $this->makeResponse($lead, $tenant, status: 'accepted');

        $firstRun = app(TransportReviewInviteService::class)->dispatchPendingInvites();
        $secondRun = app(TransportReviewInviteService::class)->dispatchPendingInvites();

        $this->assertSame(1, $firstRun);
        $this->assertSame(0, $secondRun, 're-run musi pominąć istniejące invites');
        $this->assertSame(1, TransportReview::count());
    }

    public function test_does_not_invite_when_under_14_days_past_preferred_date(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $lead = $this->makeLead(['originator_email' => 'k@example.com'], daysAgo: 5);
        $this->makeResponse($lead, $tenant, status: 'accepted');

        $sent = app(TransportReviewInviteService::class)->dispatchPendingInvites();

        $this->assertSame(0, $sent);
        $this->assertSame(0, TransportReview::count());
        Notification::assertNothingSent();
    }

    public function test_does_not_invite_pending_or_rejected_responses(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $lead1 = $this->makeLead(['originator_email' => 'a@example.com'], daysAgo: 20);
        $this->makeResponse($lead1, $tenant, status: 'pending');

        $lead2 = $this->makeLead(['originator_email' => 'b@example.com'], daysAgo: 20);
        $this->makeResponse($lead2, $tenant, status: 'rejected');

        $sent = app(TransportReviewInviteService::class)->dispatchPendingInvites();

        $this->assertSame(0, $sent);
        $this->assertSame(0, TransportReview::count());
    }

    public function test_skips_leads_without_email(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $lead = $this->makeLead(['originator_email' => null], daysAgo: 20);
        $this->makeResponse($lead, $tenant, status: 'accepted');

        $sent = app(TransportReviewInviteService::class)->dispatchPendingInvites();

        $this->assertSame(0, $sent);
    }

    public function test_token_hash_is_sha256_of_raw(): void
    {
        Notification::fake();

        $tenant = $this->makeTenant();
        $lead = $this->makeLead(['originator_email' => 'x@example.com'], daysAgo: 20);
        $this->makeResponse($lead, $tenant, status: 'accepted');

        app(TransportReviewInviteService::class)->dispatchPendingInvites();

        $review = TransportReview::first();
        // Raw token nigdy nie zapisany w DB — sprawdzamy że hash jest 64-char hex
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $review->invite_token_hash);
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Konie Trans',
            'type' => TenantType::Transporter,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeLead(array $overrides = [], int $daysAgo = 20): TransportLead
    {
        return TransportLead::create(array_merge([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'pickup_lat' => 52.28, 'pickup_lng' => 20.99,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Kraków, Krakusa 1',
            'dropoff_lat' => 50.04, 'dropoff_lng' => 19.93,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => Carbon::today()->subDays($daysAgo)->toDateString(),
            'horse_count' => 1,
            'status' => 'accepted',
            'expires_at' => now()->subDays(max(1, $daysAgo - 10)),
            'originator_email' => 'klient@example.com',
            'originator_name' => 'Jan Kowalski',
        ], $overrides));
    }

    private function makeResponse(TransportLead $lead, Tenant $tenant, string $status = 'accepted'): TransportLeadResponse
    {
        return TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $lead->id,
            'transporter_tenant_id' => $tenant->id,
            'price_net' => 1000.00, 'price_gross' => 1230.00, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date,
            'status' => $status,
            'responded_at' => now()->subDays(15),
        ]);
    }
}
