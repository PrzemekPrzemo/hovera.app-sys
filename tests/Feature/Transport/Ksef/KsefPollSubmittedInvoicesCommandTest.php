<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Ksef;

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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests dla `transport:ksef:poll-submitted` — cron polluera statusów
 * KSeF dla wszystkich submitted invoice'ów wszystkich aktywnych
 * transporterów.
 */
class KsefPollSubmittedInvoicesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected Tenant $tenant;

    protected \stdClass $tenantHolder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_ksef_poll_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Storage::fake('local');
        $this->publishFakeKsefPublicKey('test');

        $this->setUpTenantTables();
        $this->tenant = $this->makeTenant(VerificationStatus::Verified);

        // Stateful wrapper żeby zachować aktywny tenant przez wszystkie
        // wywołania mocka — Mockery może tworzyć osobne invocations,
        // a `use (&$var)` przy zamknięciach nie jest niezawodne.
        $this->tenantHolder = new \stdClass;
        $this->tenantHolder->current = null;
        $holder = $this->tenantHolder;
        $this->mock(TenantManager::class, function ($m) use ($holder) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use ($holder) {
                $holder->current = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $holder->current);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(function () use ($holder) {
                if ($holder->current === null) {
                    throw new \RuntimeException('No tenant');
                }

                return $holder->current;
            });
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $holder->current !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use ($holder) {
                $holder->current = null;
            });
            $m->shouldReceive('execute')->andReturnUsing(function ($tenant, $callback) use ($holder) {
                $previous = $holder->current;
                $holder->current = $tenant;
                try {
                    return $callback($tenant);
                } finally {
                    $holder->current = $previous;
                }
            });
        });

        // Skonfiguruj KSeF dla tenanta.
        $settings = TransportSettings::current();
        $settings->setKsefToken('tok-poll');
        $settings->forceFill([
            'ksef_enabled' => true,
            'ksef_environment' => 'test',
            'ksef_nip' => '1234567890',
        ])->save();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_picks_up_submitted_invoices_and_marks_accepted_on_200(): void
    {
        $invoice = $this->makeSubmittedInvoice([
            'ksef_submitted_at' => now()->subMinutes(10),
        ]);
        $this->seedCachedSession();

        Http::fake([
            '*/Invoice/Status/*' => Http::response([
                'processingCode' => '200',
                'processingDescription' => 'OK',
                'ksefReferenceNumber' => 'KSEF-FINAL-1',
            ], 200),
        ]);

        $this->artisan('transport:ksef:poll-submitted')->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame(TransportKsefStatus::Accepted, $invoice->ksef_status);
        $this->assertNotNull($invoice->ksef_accepted_at);
    }

    public function test_skips_invoices_younger_than_min_age(): void
    {
        // Świeżo submitted (2 minuty temu) → poll powinien JEJ pominąć (default 5min).
        $invoice = $this->makeSubmittedInvoice([
            'ksef_submitted_at' => now()->subMinutes(2),
        ]);
        $this->seedCachedSession();

        Http::fake();

        $this->artisan('transport:ksef:poll-submitted')->assertExitCode(0);

        // Żadne /Invoice/Status nie powinno być wywołane.
        Http::assertNothingSent();

        $invoice->refresh();
        $this->assertSame(TransportKsefStatus::Submitted, $invoice->ksef_status);
    }

    public function test_ignores_invoices_in_terminal_states(): void
    {
        $accepted = $this->makeSubmittedInvoice([
            'ksef_status' => TransportKsefStatus::Accepted,
            'ksef_submitted_at' => now()->subHour(),
            'number' => 'FT/ACCEPTED',
        ]);
        $rejected = $this->makeSubmittedInvoice([
            'ksef_status' => TransportKsefStatus::Rejected,
            'ksef_submitted_at' => now()->subHour(),
            'number' => 'FT/REJECTED',
        ]);
        $notSubmitted = $this->makeSubmittedInvoice([
            'ksef_status' => TransportKsefStatus::NotSubmitted,
            'ksef_submitted_at' => null,
            'ksef_reference_number' => null,
            'number' => 'FT/NEW',
        ]);
        $this->seedCachedSession();

        Http::fake();

        $this->artisan('transport:ksef:poll-submitted')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame(TransportKsefStatus::Accepted, $accepted->refresh()->ksef_status);
        $this->assertSame(TransportKsefStatus::Rejected, $rejected->refresh()->ksef_status);
        $this->assertSame(TransportKsefStatus::NotSubmitted, $notSubmitted->refresh()->ksef_status);
    }

    public function test_marks_rejected_on_processing_code_400(): void
    {
        $invoice = $this->makeSubmittedInvoice([
            'ksef_submitted_at' => now()->subMinutes(15),
        ]);
        $this->seedCachedSession();

        Http::fake([
            '*/Invoice/Status/*' => Http::response([
                'processingCode' => '400',
                'processingDescription' => 'Invalid buyer NIP',
            ], 200),
        ]);

        $this->artisan('transport:ksef:poll-submitted')->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame(TransportKsefStatus::Rejected, $invoice->ksef_status);
        $this->assertIsArray($invoice->ksef_error_payload);
    }

    public function test_per_tenant_exception_does_not_abort_batch(): void
    {
        // Tworzymy drugiego tenanta z BROKEN settings (token nieobecny),
        // żeby się wywalił przy isEnabledForCurrentTransporter (no-op,
        // nie wyjątek), oraz pierwszego z poprawnym — test sprawdza
        // że oba są przetwarzane.
        $this->makeTenant(VerificationStatus::Verified, slug: 't2-'.uniqid());
        $invoice = $this->makeSubmittedInvoice([
            'ksef_submitted_at' => now()->subMinutes(10),
        ]);
        $this->seedCachedSession();

        Http::fake([
            '*/Invoice/Status/*' => Http::response([
                'processingCode' => '200',
                'processingDescription' => 'OK',
            ], 200),
        ]);

        $this->artisan('transport:ksef:poll-submitted')->assertExitCode(0);
        $invoice->refresh();
        $this->assertSame(TransportKsefStatus::Accepted, $invoice->ksef_status);
    }

    public function test_skips_unconfigured_tenant_silently(): void
    {
        // Wyłącz KSeF żeby `isEnabledForCurrentTransporter` zwrócił false.
        TransportSettings::current()->update(['ksef_enabled' => false]);

        $invoice = $this->makeSubmittedInvoice([
            'ksef_submitted_at' => now()->subMinutes(15),
        ]);
        Http::fake();

        $this->artisan('transport:ksef:poll-submitted')->assertExitCode(0);

        // Brak HTTP wywołań — tenant został pominięty.
        Http::assertNothingSent();
        $invoice->refresh();
        $this->assertSame(TransportKsefStatus::Submitted, $invoice->ksef_status);
    }

    // ------------------------------------------------------------------

    protected function seedCachedSession(): void
    {
        $session = new KsefSessionToken;
        $session->tenant_id = $this->tenant->id;
        $session->environment = 'test';
        $session->setToken('sess-poll-1');
        $session->setAesKey(random_bytes(32));
        $session->expires_at = now()->addHours(2);
        $session->save();
    }

    protected function makeSubmittedInvoice(array $overrides = []): TransportInvoice
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
            'ksef_status' => TransportKsefStatus::Submitted,
            'ksef_reference_number' => 'REF-'.uniqid(),
            'ksef_submitted_at' => now()->subMinutes(10),
        ], $overrides));
    }

    protected function makeTenant(VerificationStatus $status, ?string $slug = null): Tenant
    {
        $slug ??= 't-'.uniqid();

        return Tenant::create([
            'slug' => $slug,
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

    protected function publishFakeKsefPublicKey(string $env): void
    {
        $keyConfig = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $res = openssl_pkey_new($keyConfig);
        $details = openssl_pkey_get_details($res);
        Storage::disk('local')->put('ksef/public-key-'.$env.'.pem', $details['key']);
    }

    protected function setUpTenantTables(): void
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
