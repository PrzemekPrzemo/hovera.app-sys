<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Leads\QuoteAcceptanceService;
use App\Domain\Transport\Notifications\LeadClosedNotification;
use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\User;
use App\Models\Tenant\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteAcceptanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_qa_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_quote_without_lead_returns_zero_counts(): void
    {
        NotificationFacade::fake();
        $tenant = $this->makeTransporter();
        $quote = $this->makeQuote(['lead_id' => null]);

        $result = app(QuoteAcceptanceService::class)->onQuoteAccepted($quote, $tenant);

        $this->assertNull($result['response_id']);
        $this->assertSame(0, $result['rejected_count']);
        $this->assertSame(0, $result['notified_count']);
        NotificationFacade::assertNothingSent();
    }

    public function test_accept_flips_winning_response_and_rejects_others(): void
    {
        NotificationFacade::fake();

        // 3 transporterów, każdy odpowiedział ofertą
        [$winner, $winnerEmail] = $this->makeVerifiedTransporterWithOwner();
        [$loser1, $loser1Email] = $this->makeVerifiedTransporterWithOwner();
        [$loser2, $loser2Email] = $this->makeVerifiedTransporterWithOwner();

        $lead = $this->makeLead();

        $r1 = TransportLeadResponse::create([
            'lead_id' => $lead->id, 'transporter_tenant_id' => $winner->id,
            'price_net' => 1000, 'price_gross' => 1230, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date, 'status' => 'pending',
        ]);
        $r2 = TransportLeadResponse::create([
            'lead_id' => $lead->id, 'transporter_tenant_id' => $loser1->id,
            'price_net' => 1100, 'price_gross' => 1353, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date, 'status' => 'pending',
        ]);
        $r3 = TransportLeadResponse::create([
            'lead_id' => $lead->id, 'transporter_tenant_id' => $loser2->id,
            'price_net' => 1200, 'price_gross' => 1476, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date, 'status' => 'pending',
        ]);

        $quote = $this->makeQuote(['lead_id' => $lead->id]);
        $result = app(QuoteAcceptanceService::class)->onQuoteAccepted($quote, $winner);

        $this->assertSame($r1->id, $result['response_id']);
        $this->assertSame(2, $result['rejected_count']);
        $this->assertSame(2, $result['notified_count']);

        // Status: winner=accepted, loser=rejected
        $this->assertSame('accepted', $r1->fresh()->status);
        $this->assertSame('rejected', $r2->fresh()->status);
        $this->assertSame('rejected', $r3->fresh()->status);

        // Lead → accepted z accepted_response_id
        $this->assertSame('accepted', $lead->fresh()->status);
        $this->assertSame($r1->id, $lead->fresh()->accepted_response_id);

        // Notyfikacje LeadClosedNotification do loserów
        NotificationFacade::assertSentOnDemandTimes(LeadClosedNotification::class, 2);
        NotificationFacade::assertSentOnDemand(
            LeadClosedNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $loser1Email,
        );
        NotificationFacade::assertSentOnDemand(
            LeadClosedNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === $loser2Email,
        );
    }

    public function test_creates_response_when_winner_did_not_respond_yet(): void
    {
        // Edge case: transporter dostał lead, użył calculator → save as quote
        // bez przechodzenia przez "Odpowiedz ofertą" w inboxie. Quote wpada
        // na akceptację bez wcześniejszego TransportLeadResponse.
        NotificationFacade::fake();
        [$winner] = $this->makeVerifiedTransporterWithOwner();
        $lead = $this->makeLead();
        $quote = $this->makeQuote([
            'lead_id' => $lead->id,
            'net_total' => 1500,
            'gross_total' => 1845,
        ]);

        $result = app(QuoteAcceptanceService::class)->onQuoteAccepted($quote, $winner);

        $this->assertNotNull($result['response_id']);
        $newResponse = TransportLeadResponse::find($result['response_id']);
        $this->assertSame('accepted', $newResponse->status);
        $this->assertSame('1500.00', $newResponse->price_net);
        $this->assertSame($quote->id, $newResponse->quote_id);
    }

    public function test_lead_status_unchanged_when_not_found(): void
    {
        NotificationFacade::fake();
        [$tenant] = $this->makeVerifiedTransporterWithOwner();
        // Lead z manualnym id ale nieistniejący w DB
        $quote = $this->makeQuote(['lead_id' => (string) Str::ulid()]);

        $result = app(QuoteAcceptanceService::class)->onQuoteAccepted($quote, $tenant);

        $this->assertNull($result['response_id']);
        $this->assertSame(0, $result['notified_count']);
    }

    public function test_loser_without_owner_does_not_throw(): void
    {
        NotificationFacade::fake();

        [$winner] = $this->makeVerifiedTransporterWithOwner();
        $loserNoOwner = $this->makeTransporter();   // brak membership
        $lead = $this->makeLead();

        TransportLeadResponse::create([
            'lead_id' => $lead->id, 'transporter_tenant_id' => $winner->id,
            'price_net' => 1000, 'price_gross' => 1230, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date, 'status' => 'pending',
        ]);
        TransportLeadResponse::create([
            'lead_id' => $lead->id, 'transporter_tenant_id' => $loserNoOwner->id,
            'price_net' => 1100, 'price_gross' => 1353, 'currency' => 'PLN',
            'proposed_date' => $lead->preferred_date, 'status' => 'pending',
        ]);

        $quote = $this->makeQuote(['lead_id' => $lead->id]);
        $result = app(QuoteAcceptanceService::class)->onQuoteAccepted($quote, $winner);

        // 1 loser, ale 0 notified bo bez ownera. Brak rzucania.
        $this->assertSame(1, $result['rejected_count']);
        $this->assertSame(0, $result['notified_count']);
    }

    /** @return array{0: Tenant, 1: string} */
    private function makeVerifiedTransporterWithOwner(): array
    {
        $tenant = $this->makeTransporter();
        $email = 'owner-'.uniqid().'@example.com';
        $owner = User::create([
            'email' => $email,
            'name' => 'Owner',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$tenant, $email];
    }

    private function makeTransporter(): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeLead(): TransportLead
    {
        return TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'pickup_address' => 'Warszawa',
            'pickup_lat' => 0, 'pickup_lng' => 0, 'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'Kraków',
            'dropoff_lat' => 0, 'dropoff_lng' => 0, 'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'status' => 'quoted',
            'expires_at' => now()->addDays(14),
        ]);
    }

    private function makeQuote(array $overrides): Quote
    {
        return Quote::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => QuoteStatus::Accepted,
            'customer_name' => 'Jan Kowalski',
            'pickup_address' => 'Warszawa',
            'pickup_lat' => 0, 'pickup_lng' => 0,
            'dropoff_address' => 'Kraków',
            'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'preferred_date' => '2026-06-15',
            'distance_km' => 295, 'duration_seconds' => 13_500,
            'routing_provider' => 'mapbox',
            'rate_per_km' => 4.50, 'base_cost' => 1329,
            'net_total' => 1377, 'vat_rate' => 23,
            'vat_amount' => 316, 'gross_total' => 1694,
            'currency' => 'PLN',
        ], $overrides));
    }

    private function setUpQuotesTable(): void
    {
        Schema::connection('tenant')->create('quotes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 32)->unique();
            $t->string('status', 16)->default('draft');
            $t->string('customer_name');
            $t->string('customer_email')->nullable();
            $t->string('customer_phone', 40)->nullable();
            $t->string('customer_company')->nullable();
            $t->string('customer_tax_id', 32)->nullable();
            $t->text('customer_address')->nullable();
            $t->string('pickup_address');
            $t->decimal('pickup_lat', 10, 7);
            $t->decimal('pickup_lng', 10, 7);
            $t->string('dropoff_address');
            $t->decimal('dropoff_lat', 10, 7);
            $t->decimal('dropoff_lng', 10, 7);
            $t->date('preferred_date');
            $t->time('preferred_time')->nullable();
            $t->boolean('round_trip')->default(false);
            $t->boolean('loaded')->default(true);
            $t->unsignedTinyInteger('horses_count')->default(1);
            $t->string('vehicle_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->decimal('distance_km', 8, 2);
            $t->unsignedInteger('duration_seconds');
            $t->string('routing_provider', 16);
            $t->text('polyline')->nullable();
            $t->decimal('rate_per_km', 6, 2);
            $t->decimal('base_cost', 10, 2);
            $t->decimal('fuel_surcharge', 10, 2)->default(0);
            $t->decimal('extra_horse_fee_snapshot', 10, 2)->default(0);
            $t->json('fixed_fees_snapshot')->nullable();
            $t->decimal('surcharge_percent_snapshot', 5, 2)->nullable();
            $t->decimal('surcharge_amount_snapshot', 10, 2)->nullable();
            $t->json('line_items')->nullable();
            $t->decimal('minimum_adjustment', 10, 2)->default(0);
            $t->decimal('net_total', 10, 2);
            $t->decimal('vat_rate', 4, 2);
            $t->decimal('vat_amount', 10, 2);
            $t->decimal('gross_total', 10, 2);
            $t->string('currency', 3)->default('PLN');
            $t->decimal('exchange_rate_to_pln', 10, 4)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->date('valid_until')->nullable();
            $t->string('accept_token', 64)->nullable()->unique();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('expired_at')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->string('lead_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->string('pdf_url')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
