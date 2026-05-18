<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Notifications\QuoteSentNotification;
use App\Domain\Transport\Quotes\QuotePdfGenerator;
use App\Enums\QuoteStatus;
use App\Filament\Transport\Resources\QuoteResource;
use App\Models\Tenant\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class QuotePdfAndEmailTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_qpdf_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();
        $this->setUpTransportSettingsTable();

        $this->mock(\App\Services\TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_transport_mailer_is_registered_in_config(): void
    {
        $this->assertNotNull(config('mail.mailers.transport'), 'mail.mailers.transport must exist');
        $this->assertSame('smtp', config('mail.mailers.transport.transport'));
    }

    public function test_pdf_html_renders_with_quote_data(): void
    {
        $quote = $this->makeQuote();
        $html = app(QuotePdfGenerator::class)->render($quote);

        $this->assertStringContainsString($quote->number, $html);
        $this->assertStringContainsString($quote->customer_name, $html);
        $this->assertStringContainsString($quote->pickup_address, $html);
        $this->assertStringContainsString($quote->dropoff_address, $html);
        $this->assertStringContainsString('PLN', $html);
    }

    public function test_pdf_generate_returns_binary_pdf(): void
    {
        $quote = $this->makeQuote();
        $bytes = app(QuotePdfGenerator::class)->generate($quote);

        $this->assertStringStartsWith('%PDF-', $bytes, 'must be a real PDF binary');
        $this->assertGreaterThan(1000, strlen($bytes));
    }

    public function test_send_quote_dispatches_email_to_customer(): void
    {
        NotificationFacade::fake();

        $quote = $this->makeQuote([
            'customer_email' => 'klient@example.com',
            'status' => QuoteStatus::Draft,
        ]);

        QuoteResource::sendQuote($quote);

        NotificationFacade::assertSentOnDemand(
            QuoteSentNotification::class,
            fn (QuoteSentNotification $n, array $channels, $notifiable) => $notifiable->routes['mail'] === 'klient@example.com'
                && $n->quote->id === $quote->id,
        );
        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
    }

    public function test_send_quote_skips_email_when_customer_has_no_email(): void
    {
        NotificationFacade::fake();

        $quote = $this->makeQuote([
            'customer_email' => null,
            'status' => QuoteStatus::Draft,
        ]);

        QuoteResource::sendQuote($quote);

        NotificationFacade::assertNothingSent();
        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status, 'status still flips to Sent');
        $this->assertNotNull($quote->fresh()->accept_token, 'token still generated');
    }

    public function test_notification_email_uses_transport_mailer(): void
    {
        $quote = $this->makeQuote();
        $notification = new QuoteSentNotification($quote);

        $message = $notification->toMail(new \stdClass());

        $this->assertSame('transport', $message->mailer);
        $this->assertStringContainsString($quote->number, $message->subject);
        $this->assertCount(1, $message->rawAttachments);
        $this->assertSame($quote->number.'.pdf', $message->rawAttachments[0]['name']);
    }

    private function makeQuote(array $overrides = []): Quote
    {
        return Quote::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => QuoteStatus::Draft,
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'pickup_lat' => 52.2818, 'pickup_lng' => 20.9921,
            'dropoff_address' => 'Kraków, Krakusa 1',
            'dropoff_lat' => 50.0413, 'dropoff_lng' => 19.9362,
            'preferred_date' => '2026-06-15',
            'distance_km' => 295.50, 'duration_seconds' => 13_500,
            'routing_provider' => 'mapbox',
            'rate_per_km' => 4.50, 'base_cost' => 1329.75,
            'fuel_surcharge' => 48.02, 'minimum_adjustment' => 0,
            'net_total' => 1377.77, 'vat_rate' => 23.00,
            'vat_amount' => 316.89, 'gross_total' => 1694.66,
            'currency' => 'PLN',
        ], $overrides));
    }

    private function setUpTransportSettingsTable(): void
    {
        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('manual_fuel_price_pln', 5, 2)->nullable();
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->json('routing_provider')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
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
            $t->string('vehicle_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->decimal('distance_km', 8, 2);
            $t->unsignedInteger('duration_seconds');
            $t->string('routing_provider', 16);
            $t->text('polyline')->nullable();
            $t->decimal('rate_per_km', 6, 2);
            $t->decimal('base_cost', 10, 2);
            $t->decimal('fuel_surcharge', 10, 2)->default(0);
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
}
