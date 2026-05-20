<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Payments\PayU\TransporterPayUQuoteService;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * TransporterPayUQuoteService tests — per-transporter PayU dla ofert.
 *
 * Pokrycie:
 *   - isConfigured() — pełna config vs missing piece
 *   - createPaymentSession() — happy path, OAuth + order create + URL storage
 *   - createPaymentSession() — currency != PLN, brak creds, gross<=0
 *   - verifyWebhook() — valid SHA256, tampered, missing header
 *   - processWebhook() — COMPLETED flip, PENDING ack, amount mismatch, idempotency
 *
 * Wszystko z Http::fake() + sqlite-in-memory tenant — nie dotykamy real PayU sandbox.
 */
class TransporterPayUQuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private const POS_ID = 300746;

    private const OAUTH_CLIENT_ID = '300746';

    private const OAUTH_CLIENT_SECRET = '2ee86a66e5d97e3fadc400c9f19b065d';

    private const MD5_KEY = 'b6ca15b0d1020e8094d9b5f8d163db54';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_payuq_').'.sqlite';
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
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_is_configured_returns_false_when_no_credentials(): void
    {
        $svc = app(TransporterPayUQuoteService::class);
        $this->assertFalse($svc->isConfigured($this->tenant));
    }

    public function test_is_configured_returns_true_when_all_fields_present(): void
    {
        $this->configurePayU();
        $svc = app(TransporterPayUQuoteService::class);
        $this->assertTrue($svc->isConfigured($this->tenant));
    }

    public function test_is_configured_returns_false_when_md5_key_missing(): void
    {
        $this->tenant->forceFill([
            'settings' => ['payments' => ['payu' => [
                'pos_id' => self::POS_ID,
                'oauth_client_id' => self::OAUTH_CLIENT_ID,
                'oauth_client_secret' => self::OAUTH_CLIENT_SECRET,
                // md5_key missing
            ]]],
        ])->save();

        $svc = app(TransporterPayUQuoteService::class);
        $this->assertFalse($svc->isConfigured($this->tenant));
    }

    public function test_create_payment_session_exchanges_oauth_and_creates_order(): void
    {
        $this->configurePayU();
        $quote = $this->makeQuote(grossPln: 1234.56);

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'access_token' => 'TENANT-TOKEN-XYZ', 'expires_in' => 43199,
            ], 200),
            'secure.snd.payu.com/api/v2_1/orders' => Http::response([
                'orderId' => 'WZHF5FFDRJ140731TRANS',
                'redirectUri' => 'https://secure.snd.payu.com/np/summary/transporter',
                'status' => ['statusCode' => 'SUCCESS'],
            ], 200),
        ]);

        $url = app(TransporterPayUQuoteService::class)
            ->createPaymentSession($this->tenant, $quote);

        $this->assertSame('https://secure.snd.payu.com/np/summary/transporter', $url);
        $quote->refresh();
        $this->assertSame('WZHF5FFDRJ140731TRANS', $quote->payu_order_id);
        $this->assertSame($quote->id, $quote->payu_ext_order_id);
        $this->assertSame($url, $quote->payu_payment_url);

        Http::assertSent(function ($r) use ($quote) {
            if (! str_contains($r->url(), '/api/v2_1/orders')) {
                return false;
            }

            // Quote gross_total = 1234.56 PLN → 123456 grosze (cast'owany przez Eloquent na decimal'a)
            $expectedAmount = (int) round((float) $quote->gross_total * 100);

            return $r['extOrderId'] === $quote->id
                && $r['totalAmount'] === (string) $expectedAmount
                && $r['currencyCode'] === 'PLN'
                && $r['merchantPosId'] === (string) self::POS_ID
                && ($r['buyer']['email'] ?? null) === 'kontakt@acme.test';
        });
    }

    public function test_create_payment_session_rejects_non_pln_currency(): void
    {
        $this->configurePayU();
        $quote = $this->makeQuote(grossPln: 100.00, currency: 'EUR');

        $this->expectException(\InvalidArgumentException::class);
        app(TransporterPayUQuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_create_payment_session_rejects_when_not_configured(): void
    {
        $quote = $this->makeQuote(grossPln: 100.00);

        $this->expectException(\InvalidArgumentException::class);
        app(TransporterPayUQuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_create_payment_session_rejects_zero_gross(): void
    {
        $this->configurePayU();
        $quote = $this->makeQuote(grossPln: 0);

        $this->expectException(\InvalidArgumentException::class);
        app(TransporterPayUQuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_create_payment_session_throws_when_oauth_fails(): void
    {
        $this->configurePayU();
        $quote = $this->makeQuote(grossPln: 100.00);

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'error_description' => 'invalid_client',
            ], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        app(TransporterPayUQuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_verify_webhook_accepts_valid_signature(): void
    {
        $this->configurePayU();
        $svc = app(TransporterPayUQuoteService::class);

        $rawBody = '{"order":{"orderId":"WZHF5FFDRJ140731","status":"COMPLETED"}}';
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256;sender=checkout";

        $this->assertTrue($svc->verifyWebhook($this->tenant, $rawBody, $header));
    }

    public function test_verify_webhook_rejects_tampered_signature(): void
    {
        $this->configurePayU();
        $svc = app(TransporterPayUQuoteService::class);

        $rawBody = '{"order":{"orderId":"X","status":"COMPLETED"}}';
        $header = 'signature='.str_repeat('0', 64).';algorithm=SHA-256';

        $this->assertFalse($svc->verifyWebhook($this->tenant, $rawBody, $header));
    }

    public function test_verify_webhook_returns_false_when_not_configured(): void
    {
        $svc = app(TransporterPayUQuoteService::class);
        $this->assertFalse($svc->verifyWebhook($this->tenant, '{}', 'signature=abc'));
    }

    public function test_process_webhook_marks_quote_paid_when_completed(): void
    {
        $this->configurePayU();
        $quote = $this->makeQuote(grossPln: 100.00);
        $quote->forceFill(['payu_order_id' => 'PAYU-ORDER-1'])->save();

        $rawBody = json_encode(['order' => [
            'orderId' => 'PAYU-ORDER-1',
            'extOrderId' => $quote->id,
            'totalAmount' => 10000,
            'status' => 'COMPLETED',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256";

        $ok = app(TransporterPayUQuoteService::class)
            ->processWebhook($this->tenant, json_decode($rawBody, true), $header, $rawBody);

        $this->assertTrue($ok);
        $quote->refresh();
        $this->assertNotNull($quote->payu_paid_at);
        $this->assertNotNull($quote->payment_completed_at);
        $this->assertSame('PayU', $quote->payment_method_label);
    }

    public function test_process_webhook_ignores_non_completed_status(): void
    {
        $this->configurePayU();
        $quote = $this->makeQuote(grossPln: 100.00);
        $quote->forceFill(['payu_order_id' => 'PAYU-ORDER-2'])->save();

        $rawBody = json_encode(['order' => [
            'orderId' => 'PAYU-ORDER-2',
            'extOrderId' => $quote->id,
            'totalAmount' => 10000,
            'status' => 'PENDING',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256";

        $ok = app(TransporterPayUQuoteService::class)
            ->processWebhook($this->tenant, json_decode($rawBody, true), $header, $rawBody);

        $this->assertTrue($ok); // ack ale brak flipu
        $quote->refresh();
        $this->assertNull($quote->payu_paid_at);
    }

    public function test_process_webhook_rejects_amount_mismatch(): void
    {
        $this->configurePayU();
        $quote = $this->makeQuote(grossPln: 100.00);
        $quote->forceFill(['payu_order_id' => 'PAYU-ORDER-3'])->save();

        $rawBody = json_encode(['order' => [
            'orderId' => 'PAYU-ORDER-3',
            'extOrderId' => $quote->id,
            'totalAmount' => 99900,  // mismatch
            'status' => 'COMPLETED',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256";

        $ok = app(TransporterPayUQuoteService::class)
            ->processWebhook($this->tenant, json_decode($rawBody, true), $header, $rawBody);

        $this->assertFalse($ok);
        $quote->refresh();
        $this->assertNull($quote->payu_paid_at);
    }

    public function test_process_webhook_is_idempotent_for_already_paid_quote(): void
    {
        $this->configurePayU();
        $quote = $this->makeQuote(grossPln: 100.00);
        $quote->forceFill([
            'payu_order_id' => 'PAYU-ORDER-4',
            'payu_paid_at' => now()->subHour(),
        ])->save();

        $rawBody = json_encode(['order' => [
            'orderId' => 'PAYU-ORDER-4',
            'extOrderId' => $quote->id,
            'totalAmount' => 10000,
            'status' => 'COMPLETED',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256";

        $ok = app(TransporterPayUQuoteService::class)
            ->processWebhook($this->tenant, json_decode($rawBody, true), $header, $rawBody);

        $this->assertTrue($ok); // idempotent ack
    }

    public function test_process_webhook_returns_false_for_unknown_quote(): void
    {
        $this->configurePayU();

        $rawBody = json_encode(['order' => [
            'orderId' => 'UNKNOWN-ORDER',
            'totalAmount' => 10000,
            'status' => 'COMPLETED',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256";

        $ok = app(TransporterPayUQuoteService::class)
            ->processWebhook($this->tenant, json_decode($rawBody, true), $header, $rawBody);

        $this->assertFalse($ok);
    }

    private function configurePayU(): void
    {
        $this->tenant->forceFill([
            'settings' => [
                'payments' => [
                    'payu' => [
                        'pos_id' => self::POS_ID,
                        'oauth_client_id' => self::OAUTH_CLIENT_ID,
                        'oauth_client_secret' => Crypt::encryptString(self::OAUTH_CLIENT_SECRET),
                        'md5_key' => Crypt::encryptString(self::MD5_KEY),
                        'sandbox' => true,
                    ],
                ],
            ],
        ])->save();
        $this->tenant->refresh();
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'payuq-'.$u,
            'name' => 'PayU Stable',
            'db_name' => 'payuq_'.$u,
            'db_username' => 'payuq_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
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

    private function makeQuote(float $grossPln, string $currency = 'PLN'): Quote
    {
        return Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'Q-'.uniqid(),
            'status' => 'draft',
            'customer_name' => 'Acme Sp. z o.o.',
            'customer_email' => 'kontakt@acme.test',
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'distance_km' => 300,
            'currency' => $currency,
            'gross_total' => $grossPln,
            'net_total' => $grossPln > 0 ? $grossPln / 1.23 : 0,
            'vat_amount' => $grossPln > 0 ? $grossPln - ($grossPln / 1.23) : 0,
            'vat_rate' => 23,
            'rate_per_km' => 4.50,
            'base_cost' => 1350,
            'fuel_surcharge' => 0,
            'minimum_adjustment' => 0,
        ]);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('quotes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 32);
            $t->string('status', 32);
            $t->string('customer_name', 200);
            $t->string('customer_email', 200)->nullable();
            $t->string('customer_phone', 50)->nullable();
            $t->string('customer_company', 200)->nullable();
            $t->string('customer_tax_id', 32)->nullable();
            $t->text('customer_address')->nullable();
            $t->text('pickup_address');
            $t->float('pickup_lat')->nullable();
            $t->float('pickup_lng')->nullable();
            $t->text('dropoff_address');
            $t->float('dropoff_lat')->nullable();
            $t->float('dropoff_lng')->nullable();
            $t->date('preferred_date');
            $t->time('preferred_time')->nullable();
            $t->boolean('round_trip')->default(false);
            $t->boolean('loaded')->default(false);
            $t->unsignedTinyInteger('horses_count')->default(1);
            $t->string('vehicle_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->decimal('distance_km', 10, 2)->nullable();
            $t->integer('duration_seconds')->nullable();
            $t->json('routing_provider')->nullable();
            $t->text('polyline')->nullable();
            $t->decimal('rate_per_km', 10, 2);
            $t->decimal('base_cost', 10, 2);
            $t->decimal('fuel_surcharge', 10, 2);
            $t->decimal('extra_horse_fee_snapshot', 10, 2)->default(0);
            $t->json('fixed_fees_snapshot')->nullable();
            $t->decimal('surcharge_percent_snapshot', 5, 2)->nullable();
            $t->decimal('surcharge_amount_snapshot', 10, 2)->nullable();
            $t->decimal('minimum_adjustment', 10, 2);
            $t->decimal('net_total', 10, 2);
            $t->decimal('vat_rate', 5, 2);
            $t->decimal('vat_amount', 10, 2);
            $t->decimal('gross_total', 10, 2);
            $t->string('currency', 3)->default('PLN');
            $t->decimal('exchange_rate_to_pln', 10, 4)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->date('valid_until')->nullable();
            $t->string('accept_token', 80)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('expired_at')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->string('lead_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->text('pdf_url')->nullable();
            $t->string('payment_url', 2048)->nullable();
            $t->string('payment_method_label', 80)->nullable();
            $t->timestamp('payment_completed_at')->nullable();
            $t->text('payment_notes')->nullable();
            $t->string('p24_session_id', 100)->nullable();
            $t->text('p24_payment_url')->nullable();
            $t->string('p24_order_id', 32)->nullable();
            $t->timestamp('p24_paid_at')->nullable();
            $t->string('payu_order_id', 64)->nullable();
            $t->string('payu_ext_order_id', 64)->nullable();
            $t->text('payu_payment_url')->nullable();
            $t->timestamp('payu_paid_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
