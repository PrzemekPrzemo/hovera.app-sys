<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wszystkie publiczne powierzchnie marketplace MUSZĄ pokazywać, że Hovera
 * = pośrednik (nie przewoźnik, nie strona umowy). Bez tych disclaimerów
 * klient po reklamacji może powiedzieć „myślałem że to Hovera wykonuje
 * transport". Testy sprawdzają obecność słów-kluczy w renderze:
 *   - /transport/zapytanie (formularz)
 *   - /transport/zapytanie/dziekujemy/{lead} (potwierdzenie)
 *   - /transport/quote/{slug}/{token} (akceptacja oferty)
 *   - /t/{slug} (publiczny profil przewoźnika)
 */
class TransportDisclaimerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_disc_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();
        $this->tenant = $this->makeVerifiedTransporter();

        // Mock TenantManager identyczny jak w QuoteAcceptanceTest — bez tego
        // controller próbuje przepiąć connection na MySQL i wysadza testy.
        $heldTenant = null;
        $this->mock(\App\Tenancy\TenantManager::class, function ($m) use (&$heldTenant) {
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

    public function test_inquiry_form_shows_intermediary_disclaimer(): void
    {
        $response = $this->get('/transport/zapytanie');

        $response->assertOk();
        $response->assertSee('pośrednikiem', false);
        $response->assertSee('regulamin-marketplace', false);
    }

    public function test_quote_landing_shows_intermediary_warning_before_accept(): void
    {
        $token = str_repeat('a', 48);
        $this->makeQuote(QuoteStatus::Sent, ['accept_token' => $token]);

        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");

        $response->assertOk();
        // Kluczowy wymóg: PRZED przyciskiem accept widoczne że umowa będzie
        // z przewoźnikiem, nie z Hovera.
        $response->assertSee('BEZPOŚREDNIO', false);
        $response->assertSee('Hovera jest pośrednikiem marketplace', false);
        $response->assertSee('NIE jest stroną tej umowy', false);
        // Link do regulaminu marketplace.
        $response->assertSee('/regulamin-marketplace', false);
        // Nazwa transportera w warningu (legal_name fallback do name).
        $response->assertSee($this->tenant->legal_name ?? $this->tenant->name);
    }

    public function test_quote_landing_does_not_show_warning_after_acceptance(): void
    {
        $token = str_repeat('b', 48);
        $this->makeQuote(QuoteStatus::Accepted, [
            'accept_token' => $token,
            'accepted_at' => now(),
        ]);

        $response = $this->get("/transport/quote/{$this->tenant->slug}/{$token}");

        $response->assertOk();
        // Po akceptacji info box z disclaimer'em już nie ma sensu —
        // akcje są ukryte, więc i ostrzeżenie z nich.
        $response->assertDontSee('Akceptując ofertę zawierasz umowę');
    }

    public function test_public_transporter_profile_shows_intermediary_disclaimer(): void
    {
        $response = $this->get('/t/'.$this->tenant->slug);

        $response->assertOk();
        $response->assertSee('pośrednik marketplace', false);
        $response->assertSee('/regulamin-marketplace', false);
        // Nazwa transportera wymieniona w disclaimerze stopki.
        $response->assertSee($this->tenant->name);
    }

    private function makeVerifiedTransporter(): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma Transport Test',
            'legal_name' => 'Firma Transport Test Sp. z o.o.',
            'tax_id' => '5252866457',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
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
