<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Dashboard\TransportDashboardService;
use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportInvoice;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransportDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_dash_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();
        $this->setUpInvoicesTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_kpi_aggregates_mrr_and_receivables_and_overdue(): void
    {
        // MRR: 1 zapłacona FV w tym miesiącu (1000 zł)
        $this->makeInvoice([
            'status' => TransportInvoiceStatus::Paid,
            'total_cents' => 100_000,
            'paid_at' => Carbon::today(),
        ]);

        // Receivables: 1 issued (2000 zł), 1 overdue (500 zł)
        $this->makeInvoice([
            'status' => TransportInvoiceStatus::Issued,
            'total_cents' => 200_000,
            'due_at' => Carbon::today()->addDays(5),
        ]);
        $this->makeInvoice([
            'status' => TransportInvoiceStatus::Overdue,
            'total_cents' => 50_000,
            'due_at' => Carbon::today()->subDays(3),
        ]);

        // Pending quote: 1 wysłana, valid
        $this->makeQuote(QuoteStatus::Sent, ['valid_until' => Carbon::today()->addDays(7)]);
        // Expired quote — nie liczona
        $this->makeQuote(QuoteStatus::Sent, ['valid_until' => Carbon::today()->subDay()]);

        $kpi = app(TransportDashboardService::class)->kpi();

        $this->assertSame(100_000, $kpi['mrr_month_cents']);
        // Receivables = issued + overdue = 200_000 + 50_000
        $this->assertSame(250_000, $kpi['receivables_cents']);
        $this->assertSame(1, $kpi['overdue_count']);
        $this->assertSame(50_000, $kpi['overdue_cents']);
        $this->assertSame(1, $kpi['pending_quotes']);
    }

    public function test_pending_invoices_returns_accepted_quotes_without_invoice(): void
    {
        $q1 = $this->makeQuote(QuoteStatus::Accepted, ['accepted_at' => now()->subDay()]);
        $q2 = $this->makeQuote(QuoteStatus::Accepted, ['accepted_at' => now()]);
        $q3 = $this->makeQuote(QuoteStatus::Accepted, ['accepted_at' => now()->subDays(2)]);

        // q2 ma już FV
        $this->makeInvoice(['quote_id' => $q2->id]);

        $list = app(TransportDashboardService::class)->pendingInvoices(5);

        $ids = $list->pluck('id')->all();
        $this->assertContains($q1->id, $ids);
        $this->assertContains($q3->id, $ids);
        $this->assertNotContains($q2->id, $ids);

        // Order: accepted_at desc
        $this->assertSame($q1->id, $list->first()->id);
    }

    public function test_top_corridors_groups_by_addresses_and_computes_share(): void
    {
        $this->makeQuote(QuoteStatus::Accepted, ['pickup_address' => 'Warszawa, ul. A', 'dropoff_address' => 'Kraków, ul. B']);
        $this->makeQuote(QuoteStatus::Accepted, ['pickup_address' => 'Warszawa, ul. A', 'dropoff_address' => 'Kraków, ul. B']);
        $this->makeQuote(QuoteStatus::Sent, ['pickup_address' => 'Warszawa, ul. A', 'dropoff_address' => 'Gdańsk, ul. C']);
        // Withdrawn nie wpada
        $this->makeQuote(QuoteStatus::Withdrawn, ['pickup_address' => 'Warszawa, ul. A', 'dropoff_address' => 'Kraków, ul. B']);

        $corridors = app(TransportDashboardService::class)->topCorridors(10);

        $this->assertCount(2, $corridors);
        $this->assertSame(2, $corridors[0]['count']);
        $this->assertEqualsWithDelta(66.7, $corridors[0]['share'], 0.1);
    }

    public function test_upcoming_transports_returns_today_and_tomorrow(): void
    {
        $today = Carbon::today();
        $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today]);
        $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today->copy()->addDay()]);
        $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today->copy()->addDays(2)]);   // day after — skip
        $this->makeQuote(QuoteStatus::Sent, ['preferred_date' => $today]);   // sent != accepted — skip

        $result = app(TransportDashboardService::class)->upcomingTransports();

        $this->assertCount(1, $result['today']);
        $this->assertCount(1, $result['tomorrow']);
    }

    public function test_upcoming_transports_week_returns_next_7_days_accepted_only(): void
    {
        $today = Carbon::today();

        // Accepted w oknie 0–7 dni — wszystkie powinny się pojawić
        $q1 = $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today]);
        $q2 = $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today->copy()->addDays(3)]);
        $q3 = $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today->copy()->addDays(7)]);

        // Poza oknem — nie pojawi się
        $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today->copy()->addDays(8)]);
        $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today->copy()->subDay()]);   // przeszły

        // Niewłaściwy status
        $this->makeQuote(QuoteStatus::Draft, ['preferred_date' => $today]);
        $this->makeQuote(QuoteStatus::Sent, ['preferred_date' => $today]);
        $this->makeQuote(QuoteStatus::Rejected, ['preferred_date' => $today]);

        $result = app(TransportDashboardService::class)->upcomingTransportsWeek(7, 10);

        $ids = $result->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$q1->id, $q2->id, $q3->id], $ids);
        $this->assertSame($q1->id, $result->first()->id);   // sorted by preferred_date asc
    }

    public function test_upcoming_transports_week_respects_limit(): void
    {
        $today = Carbon::today();
        // 12 ofert w oknie
        for ($i = 0; $i < 12; $i++) {
            $this->makeQuote(QuoteStatus::Accepted, ['preferred_date' => $today->copy()->addDays($i % 7)]);
        }

        $result = app(TransportDashboardService::class)->upcomingTransportsWeek(7, 10);

        $this->assertCount(10, $result);
    }

    public function test_top_paid_invoices_returns_paid_in_last_90_days_sorted_by_amount(): void
    {
        $today = Carbon::today();

        // 3 paid w oknie — różne kwoty
        $i1 = $this->makeInvoice(['status' => TransportInvoiceStatus::Paid, 'total_cents' => 100_000, 'paid_at' => $today->copy()->subDays(10)]);
        $i2 = $this->makeInvoice(['status' => TransportInvoiceStatus::Paid, 'total_cents' => 500_000, 'paid_at' => $today->copy()->subDays(20)]);
        $i3 = $this->makeInvoice(['status' => TransportInvoiceStatus::Paid, 'total_cents' => 250_000, 'paid_at' => $today->copy()->subDays(30)]);

        // Paid ale > 90 dni — pomijamy
        $this->makeInvoice(['status' => TransportInvoiceStatus::Paid, 'total_cents' => 999_999, 'paid_at' => $today->copy()->subDays(120)]);

        // Issued (nie paid) — pomijamy
        $this->makeInvoice(['status' => TransportInvoiceStatus::Issued, 'total_cents' => 888_888]);

        $list = app(TransportDashboardService::class)->topPaidInvoices(5, 90);
        $ids = $list->pluck('id')->all();

        $this->assertCount(3, $list);
        $this->assertSame([$i2->id, $i3->id, $i1->id], $ids);   // 500, 250, 100
    }

    public function test_top_paid_invoices_respects_limit(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $this->makeInvoice([
                'status' => TransportInvoiceStatus::Paid,
                'total_cents' => 10_000 * ($i + 1),
                'paid_at' => Carbon::today()->subDays($i + 1),
            ]);
        }

        $list = app(TransportDashboardService::class)->topPaidInvoices(5, 90);
        $this->assertCount(5, $list);
    }

    public function test_lead_flow_kpi_counts_week_window_and_win_rate(): void
    {
        $tenant = $this->makeTransporter();
        app(TenantManager::class)->setCurrent($tenant);

        $now = Carbon::now();
        $lead = $this->makeCentralLead('mazowieckie', 'małopolskie');

        // Bieżący tydzień: 3 odpowiedzi (1 accepted, 2 pending)
        $this->makeResponse($tenant->id, $lead->id, 'accepted', $now->copy()->subDays(2));
        $this->makeResponse($tenant->id, $this->makeCentralLead('mazowieckie', 'śląskie')->id, 'pending', $now->copy()->subDays(3));
        $this->makeResponse($tenant->id, $this->makeCentralLead('mazowieckie', 'śląskie')->id, 'pending', $now->copy()->subDays(5));

        // Poprzedni tydzień: 1 odpowiedź — żeby delta była +200%
        $this->makeResponse($tenant->id, $this->makeCentralLead('łódzkie', 'pomorskie')->id, 'rejected', $now->copy()->subDays(10));

        // Inny transporter — nie wpada
        $other = $this->makeTransporter();
        $this->makeResponse($other->id, $lead->id, 'accepted', $now->copy()->subDay());

        $kpi = app(TransportDashboardService::class)->leadFlowKpi();

        $this->assertSame(3, $kpi['leads_week']);
        // (3 - 1) / 1 * 100 = 200.0
        $this->assertSame(200.0, $kpi['leads_week_delta']);

        // Win rate 30d: accepted=1, total=4 (1+2+1) → 25.0%
        $this->assertSame(25.0, $kpi['win_rate_30d']);
    }

    public function test_lead_flow_kpi_returns_null_win_rate_when_no_responses(): void
    {
        $tenant = $this->makeTransporter();
        app(TenantManager::class)->setCurrent($tenant);

        $kpi = app(TransportDashboardService::class)->leadFlowKpi();

        $this->assertSame(0, $kpi['leads_week']);
        $this->assertSame(0.0, $kpi['leads_week_delta']);   // 0 vs 0
        $this->assertNull($kpi['win_rate_30d']);
        $this->assertNull($kpi['win_rate_30d_delta']);
    }

    public function test_top_voivodeship_pairs_aggregates_by_pickup_and_dropoff(): void
    {
        $tenant = $this->makeTransporter();
        app(TenantManager::class)->setCurrent($tenant);

        $now = Carbon::now();

        // 2× mazowieckie → małopolskie
        $l1 = $this->makeCentralLead('mazowieckie', 'małopolskie');
        $l2 = $this->makeCentralLead('mazowieckie', 'małopolskie');
        $this->makeResponse($tenant->id, $l1->id, 'pending', $now->copy()->subDays(5));
        $this->makeResponse($tenant->id, $l2->id, 'accepted', $now->copy()->subDays(10));

        // 1× mazowieckie → pomorskie
        $l3 = $this->makeCentralLead('mazowieckie', 'pomorskie');
        $this->makeResponse($tenant->id, $l3->id, 'pending', $now->copy()->subDays(15));

        // Inny transporter — pomijamy
        $other = $this->makeTransporter();
        $l4 = $this->makeCentralLead('śląskie', 'pomorskie');
        $this->makeResponse($other->id, $l4->id, 'pending', $now->copy()->subDay());

        // Poza oknem 90 dni
        $l5 = $this->makeCentralLead('mazowieckie', 'małopolskie');
        $this->makeResponse($tenant->id, $l5->id, 'pending', $now->copy()->subDays(120));

        $pairs = app(TransportDashboardService::class)->topVoivodeshipPairs(10, 90);

        $this->assertCount(2, $pairs);
        $this->assertSame('mazowieckie', $pairs[0]['from']);
        $this->assertSame('małopolskie', $pairs[0]['to']);
        $this->assertSame(2, $pairs[0]['count']);
        // share = 2/3 * 100 = 66.7
        $this->assertEqualsWithDelta(66.7, $pairs[0]['share'], 0.1);
    }

    public function test_top_voivodeship_pairs_returns_empty_when_no_tenant(): void
    {
        app(TenantManager::class)->forget();

        $pairs = app(TransportDashboardService::class)->topVoivodeshipPairs(10, 90);

        $this->assertSame([], $pairs);
    }

    private function makeTransporter(): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma Transportowa',
            'type' => TenantType::Transporter,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeCentralLead(string $pickupV, string $dropoffV): TransportLead
    {
        return TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'pickup_address' => 'Test pickup',
            'pickup_lat' => 0, 'pickup_lng' => 0,
            'pickup_voivodeship' => $pickupV,
            'dropoff_address' => 'Test dropoff',
            'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'dropoff_voivodeship' => $dropoffV,
            'preferred_date' => Carbon::now()->addDays(5)->toDateString(),
            'horse_count' => 1,
            'status' => 'open',
            'expires_at' => Carbon::now()->addDays(14),
        ]);
    }

    private function makeResponse(string $tenantId, string $leadId, string $status, Carbon $createdAt): TransportLeadResponse
    {
        $r = TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $leadId,
            'transporter_tenant_id' => $tenantId,
            'price_net' => 800,
            'price_gross' => 984,
            'currency' => 'PLN',
            'proposed_date' => Carbon::now()->addDays(5)->toDateString(),
            'status' => $status,
            'responded_at' => $createdAt,
        ]);

        // RefreshDatabase + auto created_at = now, ale my chcemy w przeszłość
        TransportLeadResponse::query()->where('id', $r->id)->update(['created_at' => $createdAt]);

        return $r->fresh();
    }

    private function makeQuote(QuoteStatus $status, array $overrides = []): Quote
    {
        return Quote::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'customer_name' => 'Jan Kowalski',
            'pickup_address' => 'Warszawa', 'pickup_lat' => 0, 'pickup_lng' => 0,
            'dropoff_address' => 'Kraków', 'dropoff_lat' => 0, 'dropoff_lng' => 0,
            'preferred_date' => '2026-06-15',
            'distance_km' => 100, 'duration_seconds' => 3600,
            'routing_provider' => 'ors',
            'rate_per_km' => 4.50, 'base_cost' => 450,
            'net_total' => 800, 'vat_rate' => 23, 'vat_amount' => 184, 'gross_total' => 984,
            'currency' => 'PLN',
        ], $overrides));
    }

    private function makeInvoice(array $overrides = []): TransportInvoice
    {
        return TransportInvoice::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'FT/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'kind' => TransportInvoiceKind::Fv,
            'status' => TransportInvoiceStatus::Issued,
            'seller_name' => 'Firma',
            'buyer_name' => 'Klient',
            'currency' => 'PLN',
            'subtotal_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
            'issued_at' => Carbon::today(),
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
            $t->decimal('minimum_adjustment', 10, 2)->default(0);
            $t->decimal('net_total', 10, 2);
            $t->decimal('vat_rate', 4, 2);
            $t->decimal('vat_amount', 10, 2);
            $t->decimal('gross_total', 10, 2);
            $t->string('currency', 3)->default('PLN');
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

    private function setUpInvoicesTable(): void
    {
        Schema::connection('tenant')->create('transport_invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable()->unique();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('quote_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->string('corrects_invoice_id', 26)->nullable();
            $t->string('seller_name');
            $t->string('seller_nip', 16)->nullable();
            $t->string('seller_address')->nullable();
            $t->string('seller_postal_code', 16)->nullable();
            $t->string('seller_city', 120)->nullable();
            $t->string('seller_country', 2)->default('PL');
            $t->string('seller_iban', 40)->nullable();
            $t->string('seller_bank_name', 120)->nullable();
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->string('buyer_country', 2)->default('PL');
            $t->string('buyer_email')->nullable();
            $t->string('pickup_address')->nullable();
            $t->string('dropoff_address')->nullable();
            $t->date('service_date')->nullable();
            $t->decimal('distance_km', 8, 2)->nullable();
            $t->string('vehicle_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->unsignedBigInteger('subtotal_cents')->default(0);
            $t->unsignedBigInteger('vat_cents')->default(0);
            $t->unsignedBigInteger('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->string('ksef_reference', 191)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->text('notes')->nullable();
            $t->string('pdf_url')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
