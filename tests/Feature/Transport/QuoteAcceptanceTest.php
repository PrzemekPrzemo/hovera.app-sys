<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Notifications\QuoteAcceptedNotification;
use App\Domain\Transport\Notifications\QuoteRejectedNotification;
use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Models\Tenant\Quote;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_qacc_').'.sqlite';
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
        $this->tenant = $this->makeTenant();

        // W produkcji TenantManager::setCurrent() przepina connection
        // tenant na MySQL z tenant.db_host. W teście to wysadzi sqlite —
        // mockujemy żeby setCurrent tylko trzymał aktualny tenant bez
        // dotykania connection config. current()/tenantOrFail() oddają
        // ostatnio ustawionego.
        $heldTenant = null;
        $this->mock(TenantManager::class, function ($m) use (&$heldTenant) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$heldTenant) {
                $heldTenant = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $heldTenant);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(function () use (&$heldTenant) {
                if ($heldTenant === null) {
                    throw new \RuntimeException('No tenant initialised for this request.');
                }

                return $heldTenant;
            });
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $heldTenant !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$heldTenant) {
                $heldTenant = null;
            });
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_show_landing_returns_200_with_quote_data(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Sent, ['accept_token' => str_repeat('a', 48)]);

        $this->get("/transport/quote/{$this->tenant->slug}/".str_repeat('a', 48))
            ->assertOk()
            ->assertSee($quote->number)
            ->assertSee($quote->pickup_address)
            ->assertSee($quote->dropoff_address)
            ->assertSee('Akceptuję ofertę');
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->makeQuote(QuoteStatus::Sent, ['accept_token' => str_repeat('a', 48)]);

        $this->get("/transport/quote/{$this->tenant->slug}/".str_repeat('z', 48))
            ->assertNotFound();
    }

    public function test_short_token_blocked_by_route_regex(): void
    {
        $this->get("/transport/quote/{$this->tenant->slug}/tooshort")
            ->assertNotFound();
    }

    public function test_accept_transitions_quote_and_notifies_owner(): void
    {
        NotificationFacade::fake();
        $owner = User::create([
            'email' => 'owner@example.com',
            'name' => 'Owner',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $quote = $this->makeQuote(QuoteStatus::Sent, ['accept_token' => str_repeat('b', 48)]);

        $this->post("/transport/quote/{$this->tenant->slug}/".str_repeat('b', 48).'/accept')
            ->assertRedirect();

        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->accepted_at);

        NotificationFacade::assertSentOnDemand(
            QuoteAcceptedNotification::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'owner@example.com',
        );
    }

    public function test_reject_transitions_quote_and_notifies_owner(): void
    {
        NotificationFacade::fake();
        $owner = User::create([
            'email' => 'owner2@example.com',
            'name' => 'Owner',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $quote = $this->makeQuote(QuoteStatus::Sent, ['accept_token' => str_repeat('c', 48)]);

        $this->post("/transport/quote/{$this->tenant->slug}/".str_repeat('c', 48).'/reject')
            ->assertRedirect();

        $this->assertSame(QuoteStatus::Rejected, $quote->fresh()->status);
        $this->assertNotNull($quote->fresh()->rejected_at);

        NotificationFacade::assertSentOnDemand(QuoteRejectedNotification::class);
    }

    public function test_accept_after_final_status_is_no_op_redirect(): void
    {
        NotificationFacade::fake();
        $quote = $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('d', 48),
            'accepted_at' => now()->subDay(),
        ]);

        $this->post("/transport/quote/{$this->tenant->slug}/".str_repeat('d', 48).'/accept')
            ->assertRedirect();

        // Status nie zmieniony, accepted_at z poprzedniej akcji
        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
        NotificationFacade::assertNothingSent();
    }

    public function test_landing_shows_accepted_banner_when_already_accepted(): void
    {
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => str_repeat('e', 48),
            'accepted_at' => now(),
        ]);

        $response = $this->get("/transport/quote/{$this->tenant->slug}/".str_repeat('e', 48));
        $response->assertOk();
        $response->assertSee('została już zaakceptowana');
    }

    public function test_routes_registered(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();
        $this->assertTrue($names->contains('public.transport.quote'));
        $this->assertTrue($names->contains('public.transport.quote.accept'));
        $this->assertTrue($names->contains('public.transport.quote.reject'));
        $this->assertTrue($names->contains('public.transport.quote.lookup_nip'));
    }

    public function test_accept_as_company_saves_buyer_data_on_quote(): void
    {
        NotificationFacade::fake();
        $quote = $this->makeQuote(QuoteStatus::Sent, ['accept_token' => str_repeat('f', 48)]);

        $this->post("/transport/quote/{$this->tenant->slug}/".str_repeat('f', 48).'/accept', [
            'buyer_type' => 'company',
            'customer_company' => 'Stajnia Sp. z o.o.',
            'customer_tax_id' => '5260250274', // poprawny NIP (suma kontrolna OK)
            'customer_address' => 'ul. Marszałkowska 1, 00-001 Warszawa',
        ])->assertRedirect();

        $fresh = $quote->fresh();
        $this->assertSame(QuoteStatus::Accepted, $fresh->status);
        $this->assertSame('Stajnia Sp. z o.o.', $fresh->customer_company);
        $this->assertSame('5260250274', $fresh->customer_tax_id);
        $this->assertSame('ul. Marszałkowska 1, 00-001 Warszawa', $fresh->customer_address);
    }

    public function test_accept_as_company_rejects_invalid_nip(): void
    {
        $this->makeQuote(QuoteStatus::Sent, ['accept_token' => str_repeat('g', 48)]);

        $this->post("/transport/quote/{$this->tenant->slug}/".str_repeat('g', 48).'/accept', [
            'buyer_type' => 'company',
            'customer_company' => 'Foo',
            'customer_tax_id' => '1234567890', // niepoprawna suma kontrolna
            'customer_address' => 'gdzieś',
        ])->assertSessionHasErrors('customer_tax_id');
    }

    public function test_accept_as_private_does_not_overwrite_existing_buyer_fields(): void
    {
        NotificationFacade::fake();
        $quote = $this->makeQuote(QuoteStatus::Sent, [
            'accept_token' => str_repeat('h', 48),
            'customer_company' => 'PRE',
            'customer_tax_id' => '5260250274',
            'customer_address' => 'PRE addr',
        ]);

        $this->post("/transport/quote/{$this->tenant->slug}/".str_repeat('h', 48).'/accept', [
            'buyer_type' => 'private',
        ])->assertRedirect();

        $fresh = $quote->fresh();
        $this->assertSame(QuoteStatus::Accepted, $fresh->status);
        // Private acceptance NIE czyści snapshot'u — transporter wciąż może
        // wystawić FV firmową jeśli klient wcześniej (np. przy zapytaniu)
        // podał dane firmy.
        $this->assertSame('PRE', $fresh->customer_company);
        $this->assertSame('5260250274', $fresh->customer_tax_id);
    }

    public function test_lookup_nip_returns_invalid_for_bad_checksum(): void
    {
        $this->makeQuote(QuoteStatus::Sent, ['accept_token' => str_repeat('i', 48)]);

        $this->postJson("/transport/quote/{$this->tenant->slug}/".str_repeat('i', 48).'/lookup-nip', [
            'nip' => '1234567890',
        ])->assertStatus(422)->assertJson(['ok' => false, 'error' => 'invalid_nip']);
    }

    public function test_lookup_nip_unknown_token_returns_404(): void
    {
        $this->postJson("/transport/quote/{$this->tenant->slug}/".str_repeat('z', 48).'/lookup-nip', [
            'nip' => '5260250274',
        ])->assertStatus(404);
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma',
            'type' => TenantType::Transporter,
            'db_name' => 'firma_'.uniqid(),
            'db_username' => 'firma_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makeQuote(QuoteStatus $status, array $overrides = []): Quote
    {
        return Quote::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
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
            'sent_at' => now(),
        ], $overrides));
    }

    /**
     * Quote landing renderuje sekcję płatności (direct-charge MVP — patrz
     * docs/TRANSPORT.md §13), która woła TransportSettings::current(). Stąd
     * tabela musi istnieć w sqlite test DB, żeby controller się nie wywalił.
     */
    private function setUpTransportSettingsTable(): void
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
            $t->decimal('exchange_rate_to_pln', 10, 4)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->json('routing_provider')->nullable();
            $t->string('default_payment_url_template', 2048)->nullable();
            $t->string('default_payment_method_label', 80)->nullable();
            $t->text('payment_instructions')->nullable();
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
}
