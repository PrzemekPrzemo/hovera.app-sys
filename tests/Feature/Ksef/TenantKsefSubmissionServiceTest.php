<?php

declare(strict_types=1);

namespace Tests\Feature\Ksef;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\Ksef\KsefCertificateService;
use App\Services\Ksef\TenantKsefSubmissionService;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Cert-based KSeF submit flow dla regular `Invoice`. Pokrywa:
 *   - happy path: build XML → handshake z embedded AES → POST encrypted
 *   - persistence: ksef_status='submitted', ref number, environment, xml
 *     cache, sent timestamps
 *   - error handling:
 *     - cert not configured → error result, no HTTP call
 *     - challenge HTTP 5xx → error, ksef_status='error', payload
 *     - send HTTP 4xx → rejected, ksef_status='rejected'
 *     - send HTTP 5xx → error
 *     - send response missing reference → error
 */
class TenantKsefSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    /** @var array{cert:string, key:string, password:string, pfx:string} */
    private array $testCert;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_tksef_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->tenant = $this->makeTenant();
        $this->testCert = $this->generateSelfSignedCert();

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });

        // Mock TenantManager — service requires current tenant via $tenants->current()
        // but real setCurrent triggers MySQL connection, which nie istnieje w testach.
        $held = $this->tenant;
        $this->mock(TenantManager::class, function (MockInterface $m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(fn () => $held);
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_submit_returns_error_when_ksef_not_configured(): void
    {
        // No cert / NIP set on tenant
        $invoice = $this->makeIssuedInvoice();

        $result = app(TenantKsefSubmissionService::class)->submit($invoice);

        $this->assertSame(TenantKsefSubmissionService::STATUS_ERROR, $result->status);
        $this->assertNull($result->referenceNumber);
        $this->assertStringContainsString('nie jest skonfigurowany', (string) $result->errorMessage);

        Http::assertNothingSent();
    }

    public function test_submit_happy_path_persists_submitted_state(): void
    {
        $this->configureKsefCert($this->tenant);
        $this->configureMfPublicKey();
        $this->tenant->refresh();

        Http::fake([
            'ksef-test.mf.gov.pl/api/online/Session/AuthorisationChallenge*' => Http::response([
                'challenge' => 'svr-challenge-xyz',
                'timestamp' => '2026-05-15T12:00:00Z',
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Session/InitSigned*' => Http::response([
                'sessionToken' => ['token' => 'SESSION-TOKEN-ABC'],
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Invoice/Send*' => Http::response([
                'elementReferenceNumber' => 'MF-REF-INV-987',
            ], 200),
        ]);

        $invoice = $this->makeIssuedInvoice();

        $result = app(TenantKsefSubmissionService::class)->submit($invoice);

        $this->assertSame(TenantKsefSubmissionService::STATUS_SUBMITTED, $result->status);
        $this->assertSame('MF-REF-INV-987', $result->referenceNumber);
        $this->assertTrue($result->isSuccess());

        $invoice->refresh();
        $this->assertSame(TenantKsefSubmissionService::STATUS_SUBMITTED, $invoice->ksef_status);
        $this->assertSame('MF-REF-INV-987', $invoice->ksef_reference_number);
        $this->assertSame('MF-REF-INV-987', $invoice->ksef_reference);
        $this->assertNotNull($invoice->ksef_submitted_at);
        $this->assertNotNull($invoice->ksef_sent_at);
        $this->assertSame('test', $invoice->ksef_environment);
        $this->assertStringContainsString('<Faktura', (string) $invoice->ksef_xml);
        $this->assertNull($invoice->ksef_error_payload);
    }

    public function test_submit_returns_rejected_on_http_4xx_from_send(): void
    {
        $this->configureKsefCert($this->tenant);
        $this->configureMfPublicKey();
        $this->tenant->refresh();

        Http::fake([
            'ksef-test.mf.gov.pl/api/online/Session/AuthorisationChallenge*' => Http::response([
                'challenge' => 'svr', 'timestamp' => '2026-05-15T12:00:00Z',
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Session/InitSigned*' => Http::response([
                'sessionToken' => ['token' => 'TKN'],
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Invoice/Send*' => Http::response([
                'error' => 'Invalid invoice structure',
            ], 400),
        ]);

        $invoice = $this->makeIssuedInvoice();

        $result = app(TenantKsefSubmissionService::class)->submit($invoice);

        $this->assertSame(TenantKsefSubmissionService::STATUS_REJECTED, $result->status);
        $this->assertNull($result->referenceNumber);
        $this->assertNotNull($result->errorMessage);

        $invoice->refresh();
        $this->assertSame(TenantKsefSubmissionService::STATUS_REJECTED, $invoice->ksef_status);
        $this->assertNotNull($invoice->ksef_error_payload);
    }

    public function test_submit_returns_error_on_http_5xx_from_send(): void
    {
        $this->configureKsefCert($this->tenant);
        $this->configureMfPublicKey();
        $this->tenant->refresh();

        Http::fake([
            'ksef-test.mf.gov.pl/api/online/Session/AuthorisationChallenge*' => Http::response([
                'challenge' => 'svr', 'timestamp' => '2026-05-15T12:00:00Z',
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Session/InitSigned*' => Http::response([
                'sessionToken' => ['token' => 'TKN'],
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Invoice/Send*' => Http::response('MF down', 503),
        ]);

        $invoice = $this->makeIssuedInvoice();

        $result = app(TenantKsefSubmissionService::class)->submit($invoice);

        $this->assertSame(TenantKsefSubmissionService::STATUS_ERROR, $result->status);

        $invoice->refresh();
        $this->assertSame(TenantKsefSubmissionService::STATUS_ERROR, $invoice->ksef_status);
    }

    public function test_submit_persists_error_when_auth_fails(): void
    {
        $this->configureKsefCert($this->tenant);
        $this->configureMfPublicKey();
        $this->tenant->refresh();

        Http::fake([
            'ksef-test.mf.gov.pl/api/online/Session/AuthorisationChallenge*' => Http::response([
                'error' => 'invalid context',
            ], 401),
        ]);

        $invoice = $this->makeIssuedInvoice();

        $result = app(TenantKsefSubmissionService::class)->submit($invoice);

        $this->assertSame(TenantKsefSubmissionService::STATUS_ERROR, $result->status);
        $this->assertStringContainsString('auth failed', (string) $result->errorMessage);

        $invoice->refresh();
        $this->assertSame(TenantKsefSubmissionService::STATUS_ERROR, $invoice->ksef_status);
    }

    public function test_submit_returns_error_when_send_response_missing_reference(): void
    {
        $this->configureKsefCert($this->tenant);
        $this->configureMfPublicKey();
        $this->tenant->refresh();

        Http::fake([
            'ksef-test.mf.gov.pl/api/online/Session/AuthorisationChallenge*' => Http::response([
                'challenge' => 'svr', 'timestamp' => '2026-05-15T12:00:00Z',
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Session/InitSigned*' => Http::response([
                'sessionToken' => ['token' => 'TKN'],
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Invoice/Send*' => Http::response([
                // brak elementReferenceNumber / referenceNumber
                'message' => 'ok',
            ], 200),
        ]);

        $invoice = $this->makeIssuedInvoice();

        $result = app(TenantKsefSubmissionService::class)->submit($invoice);

        $this->assertSame(TenantKsefSubmissionService::STATUS_ERROR, $result->status);
    }

    public function test_submit_caches_xml_on_invoice_before_send(): void
    {
        $this->configureKsefCert($this->tenant);
        $this->configureMfPublicKey();
        $this->tenant->refresh();

        // Send fails -> ale XML powinien być w invoice z step'u "build + cache".
        Http::fake([
            'ksef-test.mf.gov.pl/api/online/Session/AuthorisationChallenge*' => Http::response('boom', 500),
        ]);

        $invoice = $this->makeIssuedInvoice();
        $this->assertNull($invoice->ksef_xml);

        app(TenantKsefSubmissionService::class)->submit($invoice);

        $invoice->refresh();
        $this->assertStringContainsString('<Faktura', (string) $invoice->ksef_xml);
        $this->assertStringContainsString('<P_2>'.$invoice->number.'</P_2>', (string) $invoice->ksef_xml);
    }

    // ---- HELPERS (skopiowane z KsefServicesTest pattern) ----

    private function configureKsefCert(Tenant $tenant): void
    {
        $settings = (array) ($tenant->settings ?? []);
        $settings['ksef'] = [
            'env' => 'test',
            'context_nip' => '5260250274',
            'identifier_type' => 'certificateSubject',
            'cert_format' => 'pfx',
            'cert_pfx_encrypted' => Crypt::encryptString(base64_encode($this->testCert['pfx'])),
            'cert_password_encrypted' => Crypt::encryptString($this->testCert['password']),
            'cert_metadata' => KsefCertificateService::parsePfx($this->testCert['pfx'], $this->testCert['password']),
        ];
        $tenant->forceFill(['settings' => $settings])->save();
    }

    private function configureMfPublicKey(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($resource);
        $publicPem = (string) $details['key'];

        Storage::fake('local');
        Storage::disk('local')->put('ksef/public-key-test.pem', $publicPem);
        config()->set('services.ksef.public_key_disk', 'local');
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 'tksef-'.$u,
            'name' => 'TenantKsef Stable '.$u,
            'legal_name' => 'TenantKsef Stable sp. z o.o.',
            'tax_id' => '5260250274',
            'type' => TenantType::Stable,
            'db_name' => 'tksef_'.$u,
            'db_username' => 'tksef_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);
    }

    private function makeIssuedInvoice(): Invoice
    {
        $invoiceId = (string) Str::ulid();
        $number = 'FV/1/05/2026';
        DB::connection('tenant')->table('invoices')->insert([
            'id' => $invoiceId,
            'number' => $number,
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Issued->value,
            'client_id' => (string) Str::ulid(),
            'seller_name' => 'TenantKsef sp. z o.o.',
            'seller_nip' => '5260250274',
            'buyer_name' => 'Buyer',
            'buyer_nip' => '1234567890',
            'issued_at' => '2026-05-15',
            'sale_date' => '2026-05-15',
            'currency' => 'PLN',
            'subtotal_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('invoice_items')->insert([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoiceId,
            'position' => 1,
            'name' => 'Usługa',
            'unit' => 'szt.',
            'quantity' => 1,
            'unit_price_cents' => 100000,
            'net_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'vat_rate' => '23',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Invoice::with(['items', 'advances'])->find($invoiceId);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
            $t->string('corrects_invoice_id', 26)->nullable();
            $t->string('final_invoice_id', 26)->nullable()->index();
            $t->string('seller_name');
            $t->string('seller_nip', 16)->nullable();
            $t->string('seller_address')->nullable();
            $t->string('seller_postal_code', 16)->nullable();
            $t->string('seller_city', 120)->nullable();
            $t->string('seller_country', 2)->default('PL');
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->string('buyer_country', 2)->default('PL');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->decimal('exchange_rate', 12, 6)->nullable();
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->string('ksef_reference', 191)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->string('ksef_reference_number', 191)->nullable();
            $t->timestamp('ksef_submitted_at')->nullable();
            $t->timestamp('ksef_accepted_at')->nullable();
            $t->longText('ksef_xml')->nullable();
            $t->json('ksef_error_payload')->nullable();
            $t->string('ksef_environment', 8)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('invoice_items', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('invoice_id', 26);
            $t->unsignedSmallInteger('position')->default(1);
            $t->string('name');
            $t->decimal('quantity', 10, 3)->default(1);
            $t->string('unit', 16)->default('szt.');
            $t->string('vat_rate', 8)->default('23');
            $t->bigInteger('unit_price_cents');
            $t->bigInteger('net_cents');
            $t->bigInteger('vat_cents');
            $t->bigInteger('total_cents');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * @return array{cert:string, key:string, password:string, pfx:string}
     */
    private function generateSelfSignedCert(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $dn = [
            'commonName' => 'Test Stable',
            'organizationName' => 'Test Stable Sp. z o.o.',
            'organizationIdentifier' => '5260250274',
            'countryName' => 'PL',
        ];
        $csr = openssl_csr_new($dn, $key);
        $x509 = openssl_csr_sign($csr, null, $key, days: 365, options: ['digest_alg' => 'sha256']);

        $certPem = '';
        openssl_x509_export($x509, $certPem);
        $keyPem = '';
        openssl_pkey_export($key, $keyPem);

        $password = 'test-password-123';
        $pfx = '';
        openssl_pkcs12_export($certPem, $pfx, $keyPem, $password);

        return [
            'cert' => $certPem,
            'key' => $keyPem,
            'password' => $password,
            'pfx' => $pfx,
        ];
    }
}
