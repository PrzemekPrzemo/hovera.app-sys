<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Notifications\LeadReceivedNotification;
use App\Domain\Transport\Notifications\QuoteAcceptedNotification;
use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use App\Models\Central\TransportLeadResponse;
use App\Models\Central\TransportServiceArea;
use App\Models\Central\User;
use App\Models\Tenant\Quote;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SMOKE TEST 5x5x5 — Faza 8 GA-ready end-to-end happy path.
 *
 * Cel: jednym przebiegiem zweryfikować, że trzy filary marketplace'u
 * transportowego współpracują integralnie:
 *
 *   1. INQUIRY      — 5 anonimowych klientów wysyła zapytanie przez publiczny
 *                     formularz (POST /transport/zapytanie).  LeadDispatcher
 *                     znajduje 1 zweryfikowanego przewoźnika i tworzy
 *                     TransportLeadDispatch + notyfikacje.
 *   2. QUOTE        — Dla każdego leadu tworzymy ofertę (Quote w bazie tenanta
 *                     + TransportLeadResponse w central) — symulujemy "transporter
 *                     odpowiedział z ofertą" bez przechodzenia przez Filament UI
 *                     (poza zakresem smoke testu).
 *   3. ACCEPTANCE   — Klient klika link akceptacji (POST .../accept).  Quote
 *                     status → accepted, marketplace się domyka
 *                     (QuoteAcceptanceService), notyfikacje lecą do właściciela.
 *
 * Pragmatyczne uproszczenia (smoke ≠ E2E):
 *   • Wszystkie quote'y żyją w jednym sqlite "tenant" connection (5 osobnych
 *     baz tenant w sqlite testowym to overkill — pełna izolacja per-tenant
 *     jest testowana w QuoteAcceptanceTest).  Mimo to każdy quote ma własnego
 *     transporter_tenant_id w central, więc dispatch i acceptance widzą
 *     5 oddzielnych tenantów.
 *   • LeadDispatcher::recordDispatch + notifyOwner są realne (nie mockowane),
 *     więc faktycznie testujemy adjacency, service area lookup i wysyłkę maili.
 *   • Geocoder zwraca deterministyczne (lat,lng,voivodeship) dla pickup/dropoff.
 *
 * Patrz docs/TRANSPORT.md §9 Faza 8 — "Smoke test 5×5×5 (kontrola integracji
 * przed GA)".
 */
class SmokeTest5x5x5 extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    /** @var array<int, Tenant> */
    private array $transporters = [];

    /** @var array<int, string> */
    private array $ownerEmails = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Wspólny sqlite plik dla wszystkich tenant'ów — patrz pragmatyczne
        // uproszczenia w docblocku klasy.
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_smoke_').'.sqlite';
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

        // Realny TenantManager wybuchnie próbą połączenia się z prawdziwą bazą
        // tenant'a — w teście podmieniamy na in-memory holder (patrz
        // QuoteAcceptanceTest::setUp dla pełnego komentarza).
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

        config()->set('transport.voivodeship_adjacency', [
            'mazowieckie' => ['łódzkie', 'lubelskie', 'podlaskie'],
        ]);

        $this->mockGeocoder();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_five_inquiries_become_five_quotes_become_five_acceptances(): void
    {
        NotificationFacade::fake();

        // ── Setup: 5 zweryfikowanych transporterów obsługujących mazowieckie ──
        $this->createFiveVerifiedTransporters();
        $this->assertCount(5, $this->transporters);

        // ── Faza 1: 5 zapytań ──
        // Wysłane jako DIRECT (każde celuje w 1 konkretnego transportera) — pozwala
        // deterministycznie zbindować lead→tenant dla fazy 2 bez polegania na
        // broadcast'cie który wysłałby do wszystkich 5 i utrudnił mapowanie 1:1.
        for ($i = 0; $i < 5; $i++) {
            $transporter = $this->transporters[$i];
            $response = $this->post(
                "/transport/zapytanie?transporter={$transporter->slug}",
                $this->inquiryPayload($i),
            );

            $response->assertRedirect();
        }

        $leads = TransportLead::query()->orderBy('created_at')->get();
        $this->assertCount(5, $leads);
        $this->assertSame(5, $leads->where('mode', 'direct')->count(),
            'Each inquiry must be DIRECT to keep 1:1 lead→tenant mapping');
        $this->assertSame(5, $leads->where('status', 'open')->count());

        // Każdy lead powinien mieć dokładnie 1 dispatch rekord (direct mode →
        // tylko jeden targeted transporter) i jedna notyfikacja LeadReceived
        // powinna polecieć do owner'a.
        $this->assertSame(5, TransportLeadDispatch::query()->count(),
            'Each direct inquiry creates exactly one dispatch record');
        NotificationFacade::assertSentOnDemandTimes(LeadReceivedNotification::class, 5);

        // ── Faza 2: 5 ofert ──
        // Symulujemy "transporter loguje się do panelu i klika 'Wyślij ofertę'" —
        // produkcyjny przepływ tworzy Quote (bazie tenanta) + TransportLeadResponse
        // (central) z back-linkiem quote_id.  W smoke teście robimy to ręcznie
        // bez przechodzenia przez Filament UI (poza zakresem smoke).
        $quotesAndTokens = [];
        foreach ($leads as $i => $lead) {
            $transporter = $this->transporters[$i];
            $this->app->make(TenantManager::class)->setCurrent($transporter);

            $token = str_repeat(['a', 'b', 'c', 'd', 'e'][$i], 48);
            $quote = $this->makeQuoteForLead($lead, $token);

            // TransportLeadResponse w central — replicates respondToLead w
            // LeadResource (jeszcze przed wysyłką, transporter ma 'pending').
            TransportLeadResponse::create([
                'lead_id' => $lead->id,
                'transporter_tenant_id' => $transporter->id,
                'price_net' => $quote->net_total,
                'price_gross' => $quote->gross_total,
                'currency' => $quote->currency,
                'distance_km' => $quote->distance_km,
                'proposed_date' => $quote->preferred_date,
                'quote_id' => $quote->id,
                'status' => 'pending',
                'responded_at' => now(),
            ]);

            $quotesAndTokens[] = ['quote' => $quote, 'token' => $token, 'tenant' => $transporter];
        }

        $this->assertSame(5, Quote::query()->count());
        $this->assertSame(5, TransportLeadResponse::query()->count());
        // Wszystkie oferty są w statusie 'sent' (gotowe do akceptacji przez klienta).
        $this->assertSame(5, Quote::query()->where('status', QuoteStatus::Sent->value)->count());

        // ── Faza 3: 5 akceptacji ──
        // Klient klika link "Akceptuję" — POST /transport/quote/{slug}/{token}/accept.
        foreach ($quotesAndTokens as $entry) {
            $tenant = $entry['tenant'];
            $this->app->make(TenantManager::class)->setCurrent($tenant);

            $response = $this->post(
                "/transport/quote/{$tenant->slug}/{$entry['token']}/accept",
            );

            $response->assertRedirect();
        }

        // ── Asercje końcowe ──
        // Wszystkie 5 quote'ów flipnięte na Accepted z timestampem.
        $accepted = Quote::query()->where('status', QuoteStatus::Accepted->value)->get();
        $this->assertCount(5, $accepted, 'All 5 quotes must be accepted');
        foreach ($accepted as $q) {
            $this->assertNotNull($q->accepted_at, "Quote {$q->id} must have accepted_at set");
        }

        // QuoteAcceptanceService domknął wszystkie 5 leadów.
        $this->assertSame(
            5,
            TransportLead::query()->where('status', 'accepted')->count(),
            'All 5 leads must be marked accepted by QuoteAcceptanceService',
        );

        // Każdy lead ma accepted_response_id wypełnione.
        $this->assertSame(
            5,
            TransportLead::query()->whereNotNull('accepted_response_id')->count(),
        );

        // Wszystkie response'y → 'accepted' (po jednym per lead, więc nie ma
        // 'rejected' żeby wyklucz przegranych — direct mode = 1 ofert per lead).
        $this->assertSame(
            5,
            TransportLeadResponse::query()->where('status', 'accepted')->count(),
        );

        // Notyfikacje akceptacji do właścicieli transporterów (1 per quote).
        NotificationFacade::assertSentOnDemandTimes(QuoteAcceptedNotification::class, 5);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function createFiveVerifiedTransporters(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $slug = "smoke-transporter-{$i}";
            $tenant = Tenant::create([
                'slug' => $slug,
                'name' => "Smoke Transporter {$i}",
                'type' => TenantType::Transporter,
                'verification_status' => VerificationStatus::Verified,
                'db_name' => 'smoke_t_'.$i,
                'db_username' => 'smoke_t_'.$i,
                'db_password_encrypted' => Crypt::encryptString('x'),
                'status' => 'active',
            ]);

            // Service area = mazowieckie (matchuje pickup z geocoder mocka).
            TransportServiceArea::create([
                'transporter_tenant_id' => $tenant->id,
                'voivodeship' => 'mazowieckie',
            ]);

            // Owner z mailem — wymagany żeby LeadDispatcher::notifyOwner faktycznie
            // wysłał notyfikację (resolveOwnerEmail zwraca pierwszy non-revoked
            // owner; bez niego dispatch tworzy rekord z notified_email=false).
            $email = "owner-{$i}@smoke.example.com";
            $owner = User::create([
                'email' => $email,
                'name' => "Owner {$i}",
                'password' => bcrypt('secret'),
            ]);
            TenantMembership::create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            $this->transporters[] = $tenant;
            $this->ownerEmails[] = $email;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inquiryPayload(int $i): array
    {
        return [
            'customer_name' => "Klient {$i}",
            'customer_email' => "klient{$i}@example.com",
            'customer_phone' => '+48 600 100 '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'pickup_address' => 'Warszawa, Marymoncka '.($i + 1),
            'dropoff_address' => 'Kraków, Krakusa '.($i + 1),
            'preferred_date' => now()->addDays(7 + $i)->toDateString(),
            'preferred_time' => '08:00',
            'horse_count' => 1 + ($i % 3),
            'notes' => "Konia numer {$i} — smoke test.",
            'terms' => '1',
        ];
    }

    private function makeQuoteForLead(TransportLead $lead, string $token): Quote
    {
        return Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'OF/SMK/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => QuoteStatus::Sent->value,
            'customer_name' => $lead->originator_name,
            'customer_email' => $lead->originator_email,
            'customer_phone' => $lead->originator_phone,
            'pickup_address' => $lead->pickup_address,
            'pickup_lat' => $lead->pickup_lat,
            'pickup_lng' => $lead->pickup_lng,
            'dropoff_address' => $lead->dropoff_address,
            'dropoff_lat' => $lead->dropoff_lat,
            'dropoff_lng' => $lead->dropoff_lng,
            'preferred_date' => $lead->preferred_date,
            'preferred_time' => $lead->preferred_time,
            'distance_km' => 295.50,
            'duration_seconds' => 13_500,
            'routing_provider' => 'mapbox',
            'rate_per_km' => 4.50,
            'base_cost' => 1329.75,
            'fuel_surcharge' => 48.02,
            'minimum_adjustment' => 0,
            'net_total' => 1377.77,
            'vat_rate' => 23.00,
            'vat_amount' => 316.89,
            'gross_total' => 1694.66,
            'currency' => 'PLN',
            'accept_token' => $token,
            'sent_at' => now(),
            'lead_id' => $lead->id,
        ]);
    }

    /**
     * Mapbox API mock — pickup zwraca mazowieckie (matchuje service area
     * transporterów), dropoff małopolskie.  Sequence przewidziana na 10
     * wywołań (5 leadów × 2 adresów).
     */
    private function mockGeocoder(): void
    {
        config()->set('transport.providers.mapbox.access_token', 'pk.test');

        $sequence = Http::sequence();
        for ($i = 0; $i < 5; $i++) {
            $sequence->push([
                'features' => [[
                    'place_name' => 'ul. Marymoncka '.($i + 1).', Warszawa, Polska',
                    'center' => [21.0122 + ($i * 0.001), 52.2297],
                    'context' => [['id' => 'region.1', 'text' => 'Mazowieckie']],
                ]],
            ])->push([
                'features' => [[
                    'place_name' => 'ul. Krakusa '.($i + 1).', Kraków, Polska',
                    'center' => [19.9362, 50.0413],
                    'context' => [['id' => 'region.2', 'text' => 'Małopolskie']],
                ]],
            ]);
        }

        Http::fake(['api.mapbox.com/*' => $sequence]);
    }

    private function setUpQuotesTable(): void
    {
        // Kopia ze schematu z QuoteAcceptanceTest — utrzymywać synchronicznie
        // gdyby ktoś zmieniał strukturę quotes tabeli.
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
            $t->string('payment_url', 2048)->nullable();
            $t->string('payment_method_label', 80)->nullable();
            $t->timestamp('payment_completed_at')->nullable();
            $t->text('payment_notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }

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
            $t->json('routing_provider')->nullable();
            $t->string('default_payment_url_template', 2048)->nullable();
            $t->string('default_payment_method_label', 80)->nullable();
            $t->text('payment_instructions')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
