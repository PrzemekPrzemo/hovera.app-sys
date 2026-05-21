<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Services\Ksef\KsefCertificateService;
use App\Services\Ksef\KsefClient;
use App\Services\Ksef\KsefInvoiceXmlBuilder;
use App\Services\Ksef\KsefSigningService;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class KsefServicesTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    /** @var array{cert:string, key:string, password:string, pfx:string} */
    private array $testCert;

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

        $this->setUpTenantTables();
        $this->tenant = $this->makeTenant();
        $this->testCert = $this->generateSelfSignedCert();

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_cert_service_validates_nip_checksum(): void
    {
        $this->assertTrue(KsefCertificateService::isValidNip('5260250274'));
        $this->assertFalse(KsefCertificateService::isValidNip('5260250275'));
        $this->assertFalse(KsefCertificateService::isValidNip('123'));
    }

    public function test_cert_service_parses_pfx_and_extracts_metadata(): void
    {
        $meta = KsefCertificateService::parsePfx($this->testCert['pfx'], $this->testCert['password']);

        $this->assertSame('Test Stable', $meta['subject_cn']);
        $this->assertSame('5260250274', $meta['subject_nip']); // z Subject DN
        $this->assertNotEmpty($meta['fingerprint']);
        $this->assertFalse($meta['is_expired']);
        $this->assertGreaterThan(0, $meta['days_until_expiry']);
        $this->assertTrue($meta['has_private_key']);
        $this->assertSame('seal', $meta['cert_type']); // NIP bez PESEL → seal
    }

    public function test_cert_service_throws_on_wrong_password(): void
    {
        $this->expectException(\RuntimeException::class);
        KsefCertificateService::parsePfx($this->testCert['pfx'], 'wrong-password');
    }

    public function test_cert_service_parses_pem_pair(): void
    {
        $meta = KsefCertificateService::parsePemPair($this->testCert['cert'], $this->testCert['key']);

        $this->assertSame('Test Stable', $meta['subject_cn']);
        $this->assertSame('5260250274', $meta['subject_nip']);
        $this->assertTrue($meta['has_private_key']);
    }

    public function test_signing_service_builds_auth_token_request_xml(): void
    {
        $xml = app(KsefSigningService::class)
            ->buildAuthTokenRequest('challenge-abc-123', '5260250274');

        $this->assertStringContainsString('<AuthTokenRequest', $xml);
        $this->assertStringContainsString('<Challenge>challenge-abc-123</Challenge>', $xml);
        $this->assertStringContainsString('<Nip>5260250274</Nip>', $xml);
        $this->assertStringContainsString('<SubjectIdentifierType>certificateSubject</SubjectIdentifierType>', $xml);
    }

    public function test_signing_service_signs_xml_with_pfx_and_produces_valid_dsig(): void
    {
        $signing = app(KsefSigningService::class);
        $xml = $signing->buildAuthTokenRequest('test-challenge', '5260250274');

        $signed = $signing->signAuthTokenRequest($xml, $this->testCert['pfx'], $this->testCert['password']);

        $this->assertStringContainsString('<ds:Signature', $signed);
        $this->assertStringContainsString('<ds:SignedInfo', $signed);
        $this->assertStringContainsString('<ds:SignatureValue>', $signed);
        $this->assertStringContainsString('<xades:SignedProperties Id="SignedProperties">', $signed);
        $this->assertStringContainsString('<xades:SigningCertificate>', $signed);

        // Verify XML jest dobrze zformatowany
        $doc = new \DOMDocument;
        $loaded = $doc->loadXML($signed);
        $this->assertTrue($loaded);

        // SignedInfo + Reference → DigestValue dla dokumentu
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $sigValue = $xpath->query('//ds:SignatureValue');
        $this->assertSame(1, $sigValue->length);
        $this->assertNotEmpty(trim($sigValue->item(0)->nodeValue));
    }

    public function test_signing_service_signs_xml_with_pem_pair(): void
    {
        $signing = app(KsefSigningService::class);
        $xml = $signing->buildAuthTokenRequest('challenge-pem', '5260250274');

        $signed = $signing->signAuthTokenRequest(
            $xml,
            $this->testCert['key'],
            $this->testCert['cert'],
            isPem: true,
        );

        $this->assertStringContainsString('<ds:Signature', $signed);
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($signed));
    }

    public function test_invoice_xml_builder_generates_fa3_xml(): void
    {
        $invoice = $this->makeInvoiceWithItem();
        $xml = app(KsefInvoiceXmlBuilder::class)->build($invoice);

        $this->assertStringContainsString('<Faktura xmlns="http://crd.gov.pl/wzor/2023/06/29/12648/"', $xml);
        $this->assertStringContainsString('<KodFormularza kodSystemowy="FA (3)"', $xml);
        $this->assertStringContainsString('<NIP>5260250274</NIP>', $xml); // sprzedawca
        $this->assertStringContainsString('<P_2>FV/1/05/2026</P_2>', $xml);   // numer
        $this->assertStringContainsString('<KodWaluty>PLN</KodWaluty>', $xml);
        $this->assertStringContainsString('<P_15>123.00</P_15>', $xml);  // brutto

        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($xml));
    }

    public function test_invoice_xml_builder_handles_individual_buyer_without_nip(): void
    {
        $invoice = $this->makeInvoiceWithItem(buyerNip: null, buyerName: 'Jan Kowalski');
        $xml = app(KsefInvoiceXmlBuilder::class)->build($invoice);

        // Buyer side bez NIP → BrakID
        $this->assertStringContainsString('<Podmiot2>', $xml);
        $this->assertStringContainsString('<BrakID>1</BrakID>', $xml);
        $this->assertStringContainsString('Jan Kowalski', $xml);
    }

    public function test_ksef_client_is_ready_only_when_cert_and_nip_set(): void
    {
        $client = app(KsefClient::class);

        $this->assertFalse($client->isReady($this->tenant)); // świeży tenant

        $this->configureKsefCert($this->tenant);
        $this->assertTrue($client->isReady($this->tenant->fresh()));
    }

    public function test_ksef_client_authenticate_full_flow_with_mocked_http(): void
    {
        $this->configureKsefCert($this->tenant);

        Http::fake([
            'ksef-test.mf.gov.pl/api/online/Session/AuthorisationChallenge*' => Http::response([
                'challenge' => 'svr-challenge-xyz',
                'timestamp' => '2026-05-15T12:00:00Z',
            ], 200),
            'ksef-test.mf.gov.pl/api/online/Session/InitSigned*' => Http::response([
                'sessionToken' => ['token' => 'SESSION-TOKEN-123'],
            ], 200),
        ]);

        $token = app(KsefClient::class)->authenticate($this->tenant->fresh());

        $this->assertSame('SESSION-TOKEN-123', $token);

        Http::assertSent(function ($request) {
            // Drugi call (InitSigned) musi zawierać podpisany XML jako body
            if (str_contains((string) $request->url(), '/InitSigned')) {
                $body = (string) $request->body();

                return str_contains($body, '<AuthTokenRequest')
                    && str_contains($body, '<ds:Signature');
            }

            return true;
        });
    }

    public function test_ksef_client_throws_when_not_configured(): void
    {
        $this->expectException(\RuntimeException::class);
        app(KsefClient::class)->authenticate($this->tenant);
    }

    /**
     * Generuje minimal self-signed PFX + PEM pair z NIP w Subject —
     * używamy w testach żeby nie zależeć od żadnego external file.
     *
     * @return array{cert:string, key:string, password:string, pfx:string}
     */
    private function generateSelfSignedCert(): array
    {
        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $key = openssl_pkey_new($config);
        $this->assertNotFalse($key);

        $dn = [
            'commonName' => 'Test Stable',
            'organizationName' => 'Test Stable Sp. z o.o.',
            'organizationIdentifier' => '5260250274',
            'countryName' => 'PL',
        ];
        $csr = openssl_csr_new($dn, $key);
        $this->assertNotFalse($csr);

        $x509 = openssl_csr_sign($csr, null, $key, days: 365, options: ['digest_alg' => 'sha256']);
        $this->assertNotFalse($x509);

        $certPem = '';
        openssl_x509_export($x509, $certPem);
        $keyPem = '';
        openssl_pkey_export($key, $keyPem);

        $password = 'test-pfx-password';
        $pfx = '';
        openssl_pkcs12_export($x509, $pfx, $key, $password);

        return [
            'cert' => $certPem,
            'key' => $keyPem,
            'password' => $password,
            'pfx' => $pfx,
        ];
    }

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
        $tenant->refresh();
    }

    private function makeInvoiceWithItem(?string $buyerNip = '5260250274', string $buyerName = 'Stadnina Klienta'): Invoice
    {
        $client = Client::create([
            'id' => '01HCLI0000000000000000001',
            'name' => $buyerName,
            'tax_id' => $buyerNip,
        ]);
        $invoice = Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Issued->value,
            'number' => 'FV/1/05/2026',
            'client_id' => $client->id,
            'seller_name' => 'Stadnina Bucefał',
            'seller_nip' => '5260250274',
            'seller_address' => 'ul. Kasztanowa 7',
            'seller_postal_code' => '00-001',
            'seller_city' => 'Warszawa',
            'seller_country' => 'PL',
            'buyer_name' => $buyerName,
            'buyer_nip' => $buyerNip,
            'buyer_address' => 'ul. Klonowa 1',
            'buyer_postal_code' => '00-002',
            'buyer_city' => 'Kraków',
            'buyer_country' => 'PL',
            'issued_at' => '2026-05-15',
            'sale_date' => '2026-05-15',
            'currency' => 'PLN',
            'subtotal_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
        ]);
        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'name' => 'Lekcja jeździecka',
            'quantity' => 1,
            'unit' => 'szt.',
            'vat_rate' => '23',
            'unit_price_cents' => 10000,
            'net_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
        ]);

        return $invoice->load('items');
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'ksef-'.$u,
            'name' => 'KSeF Stable',
            'db_name' => 'ksef_'.$u,
            'db_username' => 'ksef_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
            'tax_id' => '5260250274',
            'country' => 'PL',
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
            $t->string('seller_name');
            $t->string('seller_nip', 16)->nullable();
            $t->string('seller_address')->nullable();
            $t->string('seller_postal_code', 16)->nullable();
            $t->string('seller_city', 120)->nullable();
            $t->char('seller_country', 2)->default('PL');
            $t->string('buyer_name');
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->char('buyer_country', 2)->default('PL');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->string('ksef_reference', 191)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('invoice_items', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('invoice_id', 26);
            $t->string('horse_id', 26)->nullable()->index();
            $t->unsignedSmallInteger('position')->default(1);
            $t->string('name');
            $t->string('description')->nullable();
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
}
