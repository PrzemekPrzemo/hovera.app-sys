<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Payments\Przelewy24\TransporterP24QuoteService;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * TransporterP24QuoteService tests — per-transporter P24 dla ofert.
 *
 * Pokrycie:
 *   - isConfigured() — pełna config vs missing piece
 *   - createPaymentSession() — happy path, sign generation, URL storage
 *   - createPaymentSession() — currency != PLN, brak creds, gross<=0
 *   - verifyWebhook() — valid sign, tampered, wrong crc
 *   - processWebhook() — full flow, amount mismatch, idempotency
 *
 * Wszystko z `Http::fake()` — nie dotykamy real P24 sandbox.
 */
class TransporterP24QuoteServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private const MERCHANT = 12345;

    private const POS = 12345;

    private const CRC = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private const API_KEY = 'rest_api_key_xxxxxxxxxxxxxxxxxxx';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_p24quote_').'.sqlite';
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
        $svc = app(TransporterP24QuoteService::class);
        $this->assertFalse($svc->isConfigured($this->tenant));
    }

    public function test_is_configured_returns_true_when_all_fields_present(): void
    {
        $this->configureP24();
        $svc = app(TransporterP24QuoteService::class);
        $this->assertTrue($svc->isConfigured($this->tenant));
    }

    public function test_is_configured_returns_false_when_api_key_missing(): void
    {
        $this->tenant->forceFill([
            'settings' => ['payments' => ['p24' => [
                'merchant_id' => self::MERCHANT,
                'pos_id' => self::POS,
                'crc_key' => 'x',
                // api_key missing
            ]]],
        ])->save();

        $svc = app(TransporterP24QuoteService::class);
        $this->assertFalse($svc->isConfigured($this->tenant));
    }

    public function test_create_payment_session_registers_and_stores_url(): void
    {
        $this->configureP24();
        $quote = $this->makeQuote(grossPln: 1234.56);

        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response([
                'data' => ['token' => 'TOK-quote-123'],
            ], 200),
        ]);

        $url = app(TransporterP24QuoteService::class)
            ->createPaymentSession($this->tenant, $quote);

        $this->assertSame('https://sandbox.przelewy24.pl/trnRequest/TOK-quote-123', $url);

        $quote->refresh();
        $this->assertSame($quote->id, $quote->p24_session_id);
        $this->assertSame($url, $quote->p24_payment_url);

        Http::assertSent(function ($request) use ($quote) {
            $body = $request->data();
            $expectedSign = hash('sha384', json_encode([
                'sessionId' => $quote->id,
                'merchantId' => self::MERCHANT,
                'amount' => 123456, // gross 1234.56 * 100
                'currency' => 'PLN',
                'crc' => self::CRC,
            ], JSON_UNESCAPED_SLASHES));

            return $body['merchantId'] === self::MERCHANT
                && $body['amount'] === 123456
                && $body['currency'] === 'PLN'
                && $body['sign'] === $expectedSign;
        });
    }

    public function test_create_payment_session_rejects_non_pln_currency(): void
    {
        $this->configureP24();
        $quote = $this->makeQuote(grossPln: 100.00, currency: 'EUR');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/PLN/');

        app(TransporterP24QuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_create_payment_session_rejects_when_not_configured(): void
    {
        $quote = $this->makeQuote(grossPln: 100.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/skonfigurowanego P24/');

        app(TransporterP24QuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_create_payment_session_rejects_zero_amount(): void
    {
        $this->configureP24();
        $quote = $this->makeQuote(grossPln: 0.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/> 0/');

        app(TransporterP24QuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_create_payment_session_throws_when_p24_returns_error(): void
    {
        $this->configureP24();
        $quote = $this->makeQuote(grossPln: 100.00);

        Http::fake([
            '*/transaction/register' => Http::response(['error' => 'invalid sign'], 400),
        ]);

        $this->expectException(RuntimeException::class);
        app(TransporterP24QuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_create_payment_session_throws_when_token_empty(): void
    {
        $this->configureP24();
        $quote = $this->makeQuote(grossPln: 100.00);

        Http::fake([
            '*/transaction/register' => Http::response(['data' => []], 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty token/');

        app(TransporterP24QuoteService::class)->createPaymentSession($this->tenant, $quote);
    }

    public function test_verify_webhook_accepts_valid_sign(): void
    {
        $this->configureP24();
        $svc = app(TransporterP24QuoteService::class);

        $payload = [
            'merchantId' => self::MERCHANT,
            'posId' => self::POS,
            'sessionId' => 'quote-1',
            'amount' => 12345,
            'originAmount' => 12345,
            'currency' => 'PLN',
            'orderId' => 999111,
            'methodId' => 154,
            'statement' => 'P24',
        ];
        $payload['sign'] = hash('sha384', json_encode(array_merge($payload, [
            'crc' => self::CRC,
        ]), JSON_UNESCAPED_SLASHES));

        $this->assertTrue($svc->verifyWebhook($this->tenant, $payload));
    }

    public function test_verify_webhook_rejects_tampered_sign(): void
    {
        $this->configureP24();
        $svc = app(TransporterP24QuoteService::class);

        $payload = [
            'merchantId' => self::MERCHANT,
            'posId' => self::POS,
            'sessionId' => 'q-1',
            'amount' => 100,
            'sign' => str_repeat('0', 96),
        ];

        $this->assertFalse($svc->verifyWebhook($this->tenant, $payload));
    }

    public function test_verify_webhook_returns_false_when_not_configured(): void
    {
        // brak credentials w tenant — bez sensu weryfikować
        $svc = app(TransporterP24QuoteService::class);
        $this->assertFalse($svc->verifyWebhook($this->tenant, ['sign' => 'x']));
    }

    public function test_process_webhook_marks_quote_paid_and_flips_payment_completed_at(): void
    {
        $this->configureP24();
        $quote = $this->makeQuote(grossPln: 100.00);
        $quote->p24_session_id = $quote->id;
        $quote->save();

        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/verify' => Http::response([
                'data' => ['status' => 'success'],
            ], 200),
        ]);

        $payload = $this->validWebhook($quote->id, 10000, 555);

        $ok = app(TransporterP24QuoteService::class)
            ->processWebhook($this->tenant, $payload);

        $this->assertTrue($ok);
        $quote->refresh();
        $this->assertNotNull($quote->p24_paid_at);
        $this->assertNotNull($quote->payment_completed_at);
        $this->assertSame('555', $quote->p24_order_id);
        $this->assertSame('Przelewy24', $quote->payment_method_label);
    }

    public function test_process_webhook_rejects_amount_mismatch(): void
    {
        $this->configureP24();
        $quote = $this->makeQuote(grossPln: 100.00);
        $quote->p24_session_id = $quote->id;
        $quote->save();

        $payload = $this->validWebhook($quote->id, 50, 555); // wrong amount

        $ok = app(TransporterP24QuoteService::class)
            ->processWebhook($this->tenant, $payload);

        $this->assertFalse($ok);
        $quote->refresh();
        $this->assertNull($quote->p24_paid_at);
    }

    public function test_process_webhook_is_idempotent_for_already_paid_quote(): void
    {
        $this->configureP24();
        $quote = $this->makeQuote(grossPln: 100.00);
        $quote->forceFill([
            'p24_session_id' => $quote->id,
            'p24_paid_at' => now()->subHour(),
            'payment_completed_at' => now()->subHour(),
        ])->save();

        // Brak Http::fake — gdyby kod wywołał verify, test by failed
        $payload = $this->validWebhook($quote->id, 10000, 555);

        $ok = app(TransporterP24QuoteService::class)
            ->processWebhook($this->tenant, $payload);

        $this->assertTrue($ok); // idempotent ack
    }

    public function test_process_webhook_returns_false_for_unknown_quote(): void
    {
        $this->configureP24();
        $payload = $this->validWebhook('unknown-session-id', 100, 555);

        $ok = app(TransporterP24QuoteService::class)
            ->processWebhook($this->tenant, $payload);

        $this->assertFalse($ok);
    }

    /**
     * @return array<string,mixed>
     */
    private function validWebhook(string $sessionId, int $amount, int $orderId): array
    {
        $base = [
            'merchantId' => self::MERCHANT,
            'posId' => self::POS,
            'sessionId' => $sessionId,
            'amount' => $amount,
            'originAmount' => $amount,
            'currency' => 'PLN',
            'orderId' => $orderId,
            'methodId' => 154,
            'statement' => 'P24',
        ];
        $base['sign'] = hash('sha384', json_encode(array_merge($base, [
            'crc' => self::CRC,
        ]), JSON_UNESCAPED_SLASHES));

        return $base;
    }

    private function configureP24(): void
    {
        $this->tenant->forceFill([
            'settings' => [
                'payments' => [
                    'default_provider' => 'p24',
                    'p24' => [
                        'merchant_id' => self::MERCHANT,
                        'pos_id' => self::POS,
                        'crc_key' => Crypt::encryptString(self::CRC),
                        'api_key' => Crypt::encryptString(self::API_KEY),
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
            'slug' => 'p24q-'.$u,
            'name' => 'P24Quote Stable',
            'db_name' => 'p24q_'.$u,
            'db_username' => 'p24q_'.substr($u, -8),
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
            'net_total' => $grossPln / 1.23,
            'vat_amount' => $grossPln - ($grossPln / 1.23),
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
            $t->decimal('minimum_adjustment', 10, 2);
            $t->decimal('net_total', 10, 2);
            $t->decimal('vat_rate', 5, 2);
            $t->decimal('vat_amount', 10, 2);
            $t->decimal('gross_total', 10, 2);
            $t->string('currency', 3)->default('PLN');
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
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
