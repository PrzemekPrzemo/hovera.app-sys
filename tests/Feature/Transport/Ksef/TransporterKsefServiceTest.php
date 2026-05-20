<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Ksef;

use App\Domain\Transport\Ksef\Api\KsefHttpClient;
use App\Domain\Transport\Ksef\KsefNotConfiguredException;
use App\Domain\Transport\Ksef\TransporterKsefService;
use App\Enums\TenantType;
use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Enums\TransportKsefStatus;
use App\Enums\VerificationStatus;
use App\Models\Central\KsefSessionToken;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransportInvoice;
use App\Models\Tenant\TransportSettings;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests dla TransporterKsefService — pełen KSeF handshake (challenge +
 * RSA-OAEP + AES-256-CBC + InitSessionToken + send) + cache sesji.
 *
 * Wszystko mockujemy: TenantManager (current tenant), tenant DB (sqlite
 * in-memory file), HTTP do KSeF (Http::fake), klucz publiczny MF
 * (generujemy świeży RSA keypair w setUp i wgrywamy do storage).
 */
class TransporterKsefServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_ksef_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        // Fake KSeF public key: świeży RSA keypair w pamięci, public część
        // ląduje w storage tam gdzie KsefHttpClient::getPublicKey() szuka.
        Storage::fake('local');
        $this->publishFakeKsefPublicKey('test');

        $this->setUpTenantTables();
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
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_throws_when_token_not_configured(): void
    {
        TransportSettings::current()->update(['ksef_enabled' => false]);

        $invoice = $this->makeInvoice();

        $this->expectException(KsefNotConfiguredException::class);
        app(TransporterKsefService::class)->submit($invoice);
    }

    public function test_throws_when_tenant_not_verified(): void
    {
        $this->tenant->verification_status = VerificationStatus::Pending;
        $this->tenant->save();

        $this->configureKsef('valid-token-123');
        $invoice = $this->makeInvoice();

        $this->expectException(KsefNotConfiguredException::class);
        app(TransporterKsefService::class)->submit($invoice);
    }

    public function test_submit_runs_full_handshake_and_sends_invoice(): void
    {
        $this->configureKsef('per-tenant-token-XYZ');
        $invoice = $this->makeInvoice();

        $this->fakeSuccessfulHandshakeAndSend('KSEF-REF-001', 'sess-token-abc');

        $result = app(TransporterKsefService::class)->submit($invoice);

        $this->assertTrue($result->isSuccess(), 'submit() should succeed; got status='.$result->status->value);

        // 1) Challenge endpoint trafiony.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/Session/AuthorisationChallenge'));
        // 2) InitToken endpoint trafiony.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/Session/InitToken'));
        // 3) Invoice/Send trafiony Z SessionToken (NIE z raw auth tokenem).
        Http::assertSent(function ($r) {
            return str_contains($r->url(), '/Invoice/Send')
                && $r->hasHeader('SessionToken', 'sess-token-abc');
        });
        // 4) RAW token autoryzacyjny NIE trafia w żadnym headerze.
        Http::assertNotSent(fn ($r) => $r->hasHeader('SessionToken', 'per-tenant-token-XYZ'));
    }

    public function test_submit_updates_invoice_ksef_status_on_success(): void
    {
        $this->configureKsef('tok');
        $invoice = $this->makeInvoice();

        $this->fakeSuccessfulHandshakeAndSend('REF-OK', 'sess-1');

        app(TransporterKsefService::class)->submit($invoice);

        $invoice->refresh();
        $this->assertSame(TransportKsefStatus::Submitted, $invoice->ksef_status);
        $this->assertNotNull($invoice->ksef_submitted_at);
        $this->assertNotEmpty($invoice->ksef_xml);
    }

    public function test_submit_persists_reference_number(): void
    {
        $this->configureKsef('tok');
        $invoice = $this->makeInvoice();

        $this->fakeSuccessfulHandshakeAndSend('KSEF-2026-001', 'sess-2');

        app(TransporterKsefService::class)->submit($invoice);

        $invoice->refresh();
        $this->assertSame('KSEF-2026-001', $invoice->ksef_reference_number);
        $this->assertSame('KSEF-2026-001', $invoice->ksef_reference); // legacy compat
    }

    public function test_submit_handles_400_response_gracefully_storing_error_payload(): void
    {
        $this->configureKsef('tok');
        $invoice = $this->makeInvoice();

        $this->fakeHandshakeOnly('sess-3');
        Http::fake([
            '*/online/Invoice/Send' => Http::response(['code' => '400-1', 'message' => 'Invalid NIP'], 400),
        ]);
        // Re-merge: Http::fake calls REPLACE the prior fake table.
        // Order matters — explicit setup:
        $this->fakeFullChainWithSendError(400);

        $result = app(TransporterKsefService::class)->submit($invoice);

        $this->assertFalse($result->isSuccess());
        $invoice->refresh();
        $this->assertSame(TransportKsefStatus::Rejected, $invoice->ksef_status);
        $this->assertIsArray($invoice->ksef_error_payload);
        $this->assertSame(400, $invoice->ksef_error_payload['status']);
    }

    public function test_refresh_status_polls_ksef_and_updates_invoice(): void
    {
        $this->configureKsef('tok');
        $invoice = $this->makeInvoice([
            'ksef_status' => TransportKsefStatus::Submitted,
            'ksef_reference_number' => 'KSEF-REF-001',
            'ksef_submitted_at' => now()->subHour(),
        ]);

        // Cache aktywnego session — pomijamy handshake w teście.
        $this->seedActiveSession('sess-status-1');

        Http::fake([
            '*/online/Invoice/Status/*' => Http::response([
                'processingCode' => '200',
                'processingDescription' => 'OK',
                'ksefReferenceNumber' => 'KSEF-FINAL-XYZ',
            ], 200),
        ]);

        $result = app(TransporterKsefService::class)->refreshStatus($invoice);

        $this->assertSame(TransportKsefStatus::Accepted, $result->status);
        $invoice->refresh();
        $this->assertSame(TransportKsefStatus::Accepted, $invoice->ksef_status);
        $this->assertNotNull($invoice->ksef_accepted_at);
    }

    public function test_session_token_is_reused_within_expiry(): void
    {
        $this->configureKsef('tok');
        $this->fakeSuccessfulHandshakeAndSend('REF-A', 'sess-cache-X');

        // 1st submit — handshake + send.
        $invoice1 = $this->makeInvoice(['number' => 'FT/2026/05/A']);
        app(TransporterKsefService::class)->submit($invoice1);

        // 2nd submit — session powinno być cached, więc /InitToken nie
        // powinno być wywołane drugi raz. Liczymy.
        $invoice2 = $this->makeInvoice(['number' => 'FT/2026/05/B']);
        app(TransporterKsefService::class)->submit($invoice2);

        // Powinno być DOKŁADNIE jedno /InitToken wywołanie.
        $initCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) $pair[0]->url(), '/Session/InitToken'))
            ->count();
        $this->assertSame(1, $initCalls, 'InitToken should be called once when session is cached');
    }

    public function test_session_token_refreshes_after_expiry(): void
    {
        $this->configureKsef('tok');
        $this->fakeSuccessfulHandshakeAndSend('REF-A', 'sess-fresh-X');

        $invoice1 = $this->makeInvoice(['number' => 'FT/2026/05/A']);
        app(TransporterKsefService::class)->submit($invoice1);

        // Wymuś stary timestamp w cache → InitToken powinno być wywołane ponownie.
        KsefSessionToken::query()
            ->where('tenant_id', $this->tenant->id)
            ->update(['expires_at' => now()->subMinutes(5)]);

        $invoice2 = $this->makeInvoice(['number' => 'FT/2026/05/B']);
        app(TransporterKsefService::class)->submit($invoice2);

        $initCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) $pair[0]->url(), '/Session/InitToken'))
            ->count();
        $this->assertGreaterThanOrEqual(2, $initCalls, 'InitToken should be called again after cache expiry');
    }

    public function test_failed_handshake_persists_error_and_does_not_send_invoice(): void
    {
        $this->configureKsef('bad-tok');
        $invoice = $this->makeInvoice();

        Http::fake([
            '*/Session/AuthorisationChallenge' => Http::response(['error' => 'Bad NIP'], 400),
        ]);

        $result = app(TransporterKsefService::class)->submit($invoice);

        $this->assertFalse($result->isSuccess());
        $invoice->refresh();
        $this->assertNotSame(TransportKsefStatus::Submitted, $invoice->ksef_status);
        $this->assertIsArray($invoice->ksef_error_payload);

        // /Invoice/Send NIE powinno być wywołane bo handshake padł.
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/Invoice/Send'));
    }

    public function test_token_never_appears_in_logs_or_exceptions(): void
    {
        $secretToken = 'TOP-SECRET-TOKEN-NEVER-LEAK-987654321';
        $this->configureKsef($secretToken);
        $invoice = $this->makeInvoice();

        Log::spy();

        // Sfake'uj 500 error po stronie handshake → ścieżka logError.
        Http::fake([
            '*' => Http::response(['error' => 'boom'], 500),
        ]);

        try {
            app(TransporterKsefService::class)->submit($invoice);
        } catch (\Throwable) {
            // Nie powinno polecieć wyjątkiem.
        }

        Log::shouldHaveReceived('warning')->withArgs(function ($message, $context = []) use ($secretToken) {
            $serialized = json_encode([$message, $context]);
            $this->assertStringNotContainsString($secretToken, (string) $serialized);

            return true;
        });

        $invoice->refresh();
        $this->assertIsArray($invoice->ksef_error_payload);
        $payloadJson = json_encode($invoice->ksef_error_payload);
        $this->assertStringNotContainsString($secretToken, (string) $payloadJson);
    }

    public function test_is_enabled_for_current_transporter_returns_false_when_token_missing(): void
    {
        TransportSettings::current()->update(['ksef_enabled' => true, 'ksef_nip' => '1234567890']);

        $this->assertFalse(app(TransporterKsefService::class)->isEnabledForCurrentTransporter());
    }

    public function test_is_enabled_for_current_transporter_returns_true_when_fully_configured(): void
    {
        $this->configureKsef('tok');
        $this->assertTrue(app(TransporterKsefService::class)->isEnabledForCurrentTransporter());
    }

    public function test_redacted_token_preview_does_not_expose_full_token(): void
    {
        $this->configureKsef('SUPER-LONG-SECRET-12345');
        $settings = TransportSettings::current();

        $preview = $settings->redactedTokenPreview();

        $this->assertNotNull($preview);
        $this->assertStringNotContainsString('LONG-SECRET', (string) $preview);
        $this->assertStringContainsString('*', (string) $preview);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function fakeSuccessfulHandshakeAndSend(string $reference, string $sessionToken): void
    {
        Http::fake([
            '*/Session/AuthorisationChallenge' => Http::response([
                'challenge' => 'CHL-'.uniqid(),
                'timestamp' => now()->toIso8601String(),
            ], 200),
            '*/Session/InitToken' => Http::response([
                'sessionToken' => [
                    'token' => $sessionToken,
                    'expirationDate' => now()->addHours(2)->toIso8601String(),
                ],
            ], 200),
            '*/Invoice/Send' => Http::response([
                'elementReferenceNumber' => $reference,
            ], 200),
        ]);
    }

    private function fakeHandshakeOnly(string $sessionToken): void
    {
        Http::fake([
            '*/Session/AuthorisationChallenge' => Http::response([
                'challenge' => 'CHL-'.uniqid(),
                'timestamp' => now()->toIso8601String(),
            ], 200),
            '*/Session/InitToken' => Http::response([
                'sessionToken' => [
                    'token' => $sessionToken,
                    'expirationDate' => now()->addHours(2)->toIso8601String(),
                ],
            ], 200),
        ]);
    }

    private function fakeFullChainWithSendError(int $sendStatus): void
    {
        Http::fake([
            '*/Session/AuthorisationChallenge' => Http::response([
                'challenge' => 'CHL-x', 'timestamp' => now()->toIso8601String(),
            ], 200),
            '*/Session/InitToken' => Http::response([
                'sessionToken' => ['token' => 'sess-err', 'expirationDate' => now()->addHour()->toIso8601String()],
            ], 200),
            '*/Invoice/Send' => Http::response(['error' => 'Invalid NIP'], $sendStatus),
        ]);
    }

    private function seedActiveSession(string $sessionToken): void
    {
        $session = new KsefSessionToken;
        $session->tenant_id = $this->tenant->id;
        $session->environment = 'test';
        $session->setToken($sessionToken);
        $session->setAesKey(random_bytes(32));
        $session->expires_at = now()->addHours(2);
        $session->save();
    }

    private function publishFakeKsefPublicKey(string $env): void
    {
        $keyConfig = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($keyConfig);
        $details = openssl_pkey_get_details($res);
        Storage::disk('local')->put('ksef/public-key-'.$env.'.pem', $details['key']);
    }

    private function configureKsef(string $token): void
    {
        $settings = TransportSettings::current();
        $settings->setKsefToken($token);
        $settings->forceFill([
            'ksef_enabled' => true,
            'ksef_environment' => 'test',
            'ksef_nip' => '1234567890',
        ])->save();
    }

    private function makeInvoice(array $overrides = []): TransportInvoice
    {
        return TransportInvoice::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'FT/2026/05/'.uniqid(),
            'kind' => TransportInvoiceKind::Fv,
            'status' => TransportInvoiceStatus::Issued,
            'seller_name' => 'Firma Transport',
            'seller_nip' => '1234567890',
            'buyer_name' => 'Jan Kowalski',
            'buyer_nip' => '5252111222',
            'currency' => 'PLN',
            'subtotal_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
        ], $overrides));
    }

    private function makeTenant(VerificationStatus $status): Tenant
    {
        return Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma Transport',
            'legal_name' => 'Firma Transport Sp. z o.o.',
            'tax_id' => '1234567890',
            'type' => TenantType::Transporter,
            'verification_status' => $status,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'country' => 'PL',
        ]);
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
            $t->json('routing_provider')->nullable();
            $t->text('ksef_token_encrypted')->nullable();
            $t->string('ksef_environment', 16)->default('test');
            $t->string('ksef_nip', 16)->nullable();
            $t->boolean('ksef_enabled')->default(false);
            $t->timestamp('created_at')->useCurrent();
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
            $t->string('ksef_reference_number', 191)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->timestamp('ksef_submitted_at')->nullable();
            $t->timestamp('ksef_accepted_at')->nullable();
            $t->longText('ksef_xml')->nullable();
            $t->json('ksef_error_payload')->nullable();
            $t->text('notes')->nullable();
            $t->string('pdf_url')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('audit_log', function ($t) {
            $t->ulid('id')->primary();
            $t->string('actor_central_user_id', 26)->nullable();
            $t->string('action', 191);
            $t->string('target_type', 100)->nullable();
            $t->string('target_id', 64)->nullable();
            $t->json('payload')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->boolean('via_impersonation')->default(false);
            $t->string('impersonation_session_id', 64)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }
}
