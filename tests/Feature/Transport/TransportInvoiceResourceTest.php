<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Notifications\TransportInvoiceSentNotification;
use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Enums\VerificationStatus;
use App\Filament\Transport\Resources\QuoteResource;
use App\Filament\Transport\Resources\TransportInvoiceResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportInvoice;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class TransportInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_invres_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();
        $this->setUpInvoicesTables();
        $this->tenant = $this->makeTenant(VerificationStatus::Verified);

        $held = $this->tenant;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(function () use (&$held) {
                if ($held === null) {
                    throw new \RuntimeException('No tenant');
                }

                return $held;
            });
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
        });
        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_resource_routes_registered(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();
        $this->assertTrue($names->contains('filament.transport.resources.transport-invoices.index'));
        $this->assertTrue($names->contains('filament.transport.resources.transport-invoices.view'));
    }

    public function test_issue_invoice_from_quote_creates_invoice_when_verified(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Accepted);

        QuoteResource::issueInvoice($quote);

        $invoice = TransportInvoice::query()->where('quote_id', $quote->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(TransportInvoiceStatus::Issued, $invoice->status);
        $this->assertSame(TransportInvoiceKind::Fv, $invoice->kind);
    }

    public function test_issue_invoice_blocked_when_tenant_not_verified(): void
    {
        // Re-bind tenant manager z tenantem nieverified
        $this->tenant->forceFill(['verification_status' => VerificationStatus::Pending])->save();
        $held = $this->tenant->fresh();
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(fn () => $held);
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
        });

        $quote = $this->makeQuote(QuoteStatus::Accepted);

        QuoteResource::issueInvoice($quote);

        $this->assertSame(0, TransportInvoice::count(), 'invoice must NOT be created when tenant not verified');
    }

    public function test_send_email_dispatches_notification(): void
    {
        NotificationFacade::fake();
        $invoice = $this->makeInvoice([
            'buyer_email' => 'klient@example.com',
            'status' => TransportInvoiceStatus::Issued,
        ]);

        TransportInvoiceResource::sendInvoiceEmail($invoice);

        NotificationFacade::assertSentOnDemand(
            TransportInvoiceSentNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'klient@example.com'
                && $n->invoice->id === $invoice->id,
        );
    }

    public function test_send_email_skipped_when_no_buyer_email(): void
    {
        NotificationFacade::fake();
        $invoice = $this->makeInvoice([
            'buyer_email' => null,
            'status' => TransportInvoiceStatus::Issued,
        ]);

        TransportInvoiceResource::sendInvoiceEmail($invoice);

        NotificationFacade::assertNothingSent();
    }

    public function test_mark_paid_flips_status_and_timestamp(): void
    {
        $invoice = $this->makeInvoice([
            'status' => TransportInvoiceStatus::Issued,
            'paid_at' => null,
        ]);

        TransportInvoiceResource::markPaid($invoice);

        $fresh = $invoice->fresh();
        $this->assertSame(TransportInvoiceStatus::Paid, $fresh->status);
        $this->assertNotNull($fresh->paid_at);
    }

    private function makeTenant(VerificationStatus $vs): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma Transport',
            'legal_name' => 'Firma Transport Sp. z o.o.',
            'tax_id' => '1234567890',
            'type' => TenantType::Transporter,
            'verification_status' => $vs,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'country' => 'PL',
        ]);
    }

    private function makeQuote(QuoteStatus $status): Quote
    {
        return Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa',
            'pickup_lat' => 52.23, 'pickup_lng' => 21.01,
            'dropoff_address' => 'Kraków',
            'dropoff_lat' => 50.04, 'dropoff_lng' => 19.94,
            'preferred_date' => '2026-06-15',
            'distance_km' => 295.50, 'duration_seconds' => 13500,
            'routing_provider' => 'mapbox',
            'rate_per_km' => 4.50, 'base_cost' => 1329.75,
            'fuel_surcharge' => 48.02, 'minimum_adjustment' => 0,
            'net_total' => 1377.77, 'vat_rate' => 23,
            'vat_amount' => 316.89, 'gross_total' => 1694.66,
            'currency' => 'PLN',
        ]);
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
            'buyer_email' => 'klient@example.com',
            'currency' => 'PLN',
            'subtotal_cents' => 137777,
            'vat_cents' => 31689,
            'total_cents' => 169466,
            'issued_at' => '2026-05-18',
            'sale_date' => '2026-05-18',
            'due_at' => '2026-06-01',
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

        Schema::connection('tenant')->create('quote_waypoints', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('quote_id', 26)->index();
            $t->unsignedTinyInteger('sort_order')->default(0);
            $t->string('kind', 16)->default('stop');
            $t->string('address');
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->string('poi_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }

    private function setUpInvoicesTables(): void
    {
        Schema::connection('tenant')->create('transport_invoice_counters', function ($t) {
            $t->string('scope', 64)->primary();
            $t->unsignedInteger('seq')->default(0);
            $t->timestamp('updated_at')->useCurrent();
        });
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
            $t->string('buyer_type', 16)->default('individual');
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
            $t->decimal('exchange_rate', 14, 6)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->string('exchange_rate_source', 16)->nullable();
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
        Schema::connection('tenant')->create('transport_invoice_items', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('invoice_id', 26);
            $t->unsignedSmallInteger('position')->default(1);
            $t->string('name');
            $t->string('description')->nullable();
            $t->decimal('quantity', 10, 3)->default(1);
            $t->string('unit', 16)->default('usł.');
            $t->string('vat_rate', 8)->default('23');
            $t->unsignedBigInteger('unit_price_cents');
            $t->unsignedBigInteger('net_cents');
            $t->unsignedBigInteger('vat_cents');
            $t->unsignedBigInteger('total_cents');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
