<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Calculator\Data\Quotation;
use App\Domain\Transport\Geocoding\Data\GeocodedAddress;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Quotes\QuoteNumberGenerator;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\CalculationMode;
use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Filament\Transport\Pages\Calculator;
use App\Models\Central\FuelPrice;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportSettings;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * One-shot save z Calculator'a — `saveAsQuoteInline` tworzy Quote
 * bezpośrednio i przekierowuje na EditQuote (zamiast 2-step session
 * pending → CreateQuote::fillForm). Patrz Calculator live UX w
 * docs/MARKETPLACE-ROADMAP.md.
 */
class CalculatorSaveAsQuoteInlineTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_calc_inline_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->tenant = $this->makeTransporterTenant();

        // Logujemy operator'a — Calculator::canAccess() sprawdza membership
        // (TenantRoleGate). Bez tego saveAsQuoteInline rzuca 403.
        $this->user = User::create([
            'email' => 'operator-'.uniqid().'@example.test',
            'name' => 'Operator',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'role' => 'operator',
            'joined_at' => now(),
        ]);
        $this->actingAs($this->user);

        // Pinujemy tenant na TenantManager singleton przez reflection żeby
        // ominąć setCurrent->configureTenantConnection (które nadpisałoby
        // nasz SQLite na MySQL z Tenant::databaseConnectionConfig).
        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $this->tenant);

        // QuoteResource::getUrl() w saveAsQuoteInline resolve'uje URL przez
        // current panel — bez setCurrentPanel Filament default'uje na
        // pierwszy panel i route nie istnieje.
        Filament::setCurrentPanel(Filament::getPanel('transport'));

        FuelPrice::create([
            'fuel_type' => FuelPrice::TYPE_DIESEL,
            'price_pln' => 7.50,
            'snapshot_date' => now()->toDateString(),
            'source' => FuelPrice::SOURCE_EPETROL,
            'created_at' => now(),
        ]);

        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'routes' => [[
                    'summary' => ['distance' => 100.0, 'duration' => 3600],
                    'geometry' => 'POLY',
                ]],
            ]),
        ]);

        $this->mock(MapboxGeocoder::class, function (MockInterface $m) {
            $m->shouldReceive('geocode')->andReturn(new GeocodedAddress(
                displayName: 'Mocked Address',
                coords: new Coords(52.0, 21.0),
            ));
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_inline_save_creates_quote_with_calculator_snapshot(): void
    {
        $page = $this->makeCalculatorWithQuotation();

        $response = $page->saveAsQuoteInline(
            app(QuoteNumberGenerator::class),
            app(TenantAuditLogger::class),
        );

        $this->assertNotNull($response);
        $this->assertSame(1, Quote::query()->count());

        $quote = Quote::query()->first();
        $this->assertNotEmpty($quote->number);
        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertSame('Warszawa', $quote->pickup_address);
        $this->assertSame('Kraków', $quote->dropoff_address);
        $this->assertEquals(100.0, (float) $quote->distance_km);
        // Pełna kalkulacja domyślnymi settings'ami: gross 984.00 (base 450 +
        // surcharge 16.25 + min_adjust 333.75 = 800, ×1.23 VAT = 984).
        $this->assertEquals(984.0, (float) $quote->gross_total);
        $this->assertSame('PLN', $quote->currency);
    }

    public function test_inline_save_redirects_to_edit_quote(): void
    {
        $page = $this->makeCalculatorWithQuotation();

        $response = $page->saveAsQuoteInline(
            app(QuoteNumberGenerator::class),
            app(TenantAuditLogger::class),
        );

        $quote = Quote::query()->first();
        // Filament EditQuote URL: /transport/quotes/{record}/edit (tenant panel).
        // Sprawdzamy że redirect zawiera ID utworzonego quote'a.
        $this->assertStringContainsString((string) $quote->id, $response->getTargetUrl());
        $this->assertStringContainsString('/transport/quotes/', $response->getTargetUrl());
    }

    public function test_inline_save_fills_customer_placeholder_when_no_lead_prefill(): void
    {
        $page = $this->makeCalculatorWithQuotation();

        $page->saveAsQuoteInline(
            app(QuoteNumberGenerator::class),
            app(TenantAuditLogger::class),
        );

        $quote = Quote::query()->first();
        // Bez lead pre-fillu customer_name powinien zawierać placeholder
        // (nie pusty string — chcemy żeby user widział "(do uzupełnienia)"
        // w liście quote'ów zamiast pustego pola).
        $this->assertNotEmpty($quote->customer_name);
    }

    public function test_inline_save_preserves_customer_from_lead_prefill(): void
    {
        session()->put('transport.calc.pending', [
            'lead_id' => '01HZZZZZZZZZZZZZZZZZZZZZ',
            'customer_name' => 'Jan Lead',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '+48 600 000 000',
        ]);

        $page = $this->makeCalculatorWithQuotation();
        $page->pendingLeadId = '01HZZZZZZZZZZZZZZZZZZZZZ';

        $page->saveAsQuoteInline(
            app(QuoteNumberGenerator::class),
            app(TenantAuditLogger::class),
        );

        $quote = Quote::query()->first();
        $this->assertSame('Jan Lead', $quote->customer_name);
        $this->assertSame('jan@example.com', $quote->customer_email);
        $this->assertSame('01HZZZZZZZZZZZZZZZZZZZZZ', $quote->lead_id);
    }

    public function test_inline_save_generates_audit_log_entry(): void
    {
        $page = $this->makeCalculatorWithQuotation();

        $page->saveAsQuoteInline(
            app(QuoteNumberGenerator::class),
            app(TenantAuditLogger::class),
        );

        $audit = AuditLog::query()->where('action', 'quote.create')->first();
        $this->assertNotNull($audit, 'audit log entry should be created');
        $this->assertSame('Quote', $audit->target_type);
        $this->assertSame('calculator_inline', $audit->payload['source'] ?? null);
    }

    public function test_inline_save_aborts_without_quotation(): void
    {
        $page = new Calculator;
        // Brak `calculate()` → `quotation` jest null → 422.
        $this->expectException(HttpException::class);

        $page->saveAsQuoteInline(
            app(QuoteNumberGenerator::class),
            app(TenantAuditLogger::class),
        );
    }

    public function test_inline_save_generates_sequential_quote_numbers(): void
    {
        $page1 = $this->makeCalculatorWithQuotation();
        $page1->saveAsQuoteInline(app(QuoteNumberGenerator::class), app(TenantAuditLogger::class));

        $page2 = $this->makeCalculatorWithQuotation();
        $page2->saveAsQuoteInline(app(QuoteNumberGenerator::class), app(TenantAuditLogger::class));

        $numbers = Quote::query()->orderBy('created_at')->pluck('number')->all();
        $this->assertCount(2, $numbers);
        $this->assertNotSame($numbers[0], $numbers[1], 'numery quote\'ów muszą być unikatowe');
    }

    /**
     * Helper — instancjuje Calculator page z gotową kalkulacją w state'cie.
     * Zamiast iść przez Livewire form lifecycle, ręcznie wstrzykujemy
     * `quotation` + display names + lat/lng do props, tak jak po udanym
     * `calculate()`.
     */
    private function makeCalculatorWithQuotation(): Calculator
    {
        $page = new Calculator;
        $page->data = [
            'from_address' => 'Warszawa',
            'to_address' => 'Kraków',
            'loaded' => true,
            'round_trip' => false,
            'avoid_tolls' => false,
            'avoid_ferries' => false,
            'profile' => 'truck',
            'horses_count' => 1,
            'fixed_fees' => [],
            'surcharge_percent' => null,
            'mode' => CalculationMode::OneWay->value,
        ];
        $page->fromDisplayName = 'Warszawa';
        $page->toDisplayName = 'Kraków';
        $page->pendingPickupLat = 52.0;
        $page->pendingPickupLng = 21.0;
        $page->pendingDropoffLat = 50.0;
        $page->pendingDropoffLng = 19.0;
        $page->quotation = new Quotation(
            distanceKm: 100.0,
            durationSeconds: 3600,
            rateUsed: 4.50,
            baseCost: 450.0,
            fuelSurcharge: 16.25,
            minimumAdjustment: 333.75,
            netTotal: 800.0,
            vatRate: 23.0,
            vatAmount: 184.0,
            grossTotal: 984.0,
            currency: 'PLN',
            routingProvider: 'ors',
            polyline: 'POLY',
        );

        return $page;
    }

    private function makeTransporterTenant(): Tenant
    {
        $plan = Plan::create([
            'code' => 'calc_inline_test_'.uniqid(),
            'audience' => 'transporter',
            'name' => 'Test',
            'currency' => 'PLN',
            'limits' => ['routing_providers' => ['ors'], 'max_vehicles' => 5],
        ]);

        TransportSettings::current()->forceFill([
            'routing_provider' => ['provider' => 'ors', 'api_key' => 'test-key'],
        ])->save();

        $tenant = Tenant::create([
            'slug' => 'calc-i-'.uniqid(),
            'name' => 'Calc Inline',
            'type' => TenantType::Transporter,
            'db_name' => 'calc_i_'.uniqid(),
            'db_username' => 'calc_i_'.substr(uniqid(), -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $tenant->setRelation('plan', $plan);

        return $tenant;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
            $t->decimal('extra_horse_fee_default', 8, 2)->default(0);
            $t->json('fixed_fees_default')->nullable();
            $t->decimal('surcharge_percent_default', 5, 2)->nullable();
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->string('fuel_calculation_mode', 16)->default('surcharge');
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('manual_fuel_price_pln', 5, 2)->nullable();
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->string('home_address', 255)->nullable();
            $t->decimal('home_lat', 10, 7)->nullable();
            $t->decimal('home_lng', 10, 7)->nullable();
            $t->json('routing_provider')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });

        Schema::connection('tenant')->create('quote_counters', function ($t) {
            $t->string('scope', 32)->primary();
            $t->unsignedInteger('seq');
            $t->timestamp('updated_at')->nullable();
        });

        Schema::connection('tenant')->create('audit_log', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('actor_central_user_id', 26)->nullable();
            $t->string('action', 128);
            $t->string('target_type', 64)->nullable();
            $t->string('target_id', 64)->nullable();
            $t->json('payload')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->boolean('via_impersonation')->default(false);
            $t->string('impersonation_session_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::connection('tenant')->create('quotes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 32)->unique();
            $t->string('status', 16)->default('draft');
            $t->string('customer_id', 26)->nullable();
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
            $t->string('calculation_mode', 16)->default('one_way');
            $t->boolean('round_trip')->default(false);
            $t->boolean('loaded')->default(true);
            $t->unsignedTinyInteger('horses_count')->default(1);
            $t->string('vehicle_id', 26)->nullable();
            $t->string('trailer_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->decimal('distance_km', 10, 2);
            $t->integer('duration_seconds');
            $t->string('routing_provider', 16)->default('manual');
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
            $t->string('accept_token', 64)->nullable();
            $t->string('lead_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->string('pdf_url', 500)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('expired_at')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
