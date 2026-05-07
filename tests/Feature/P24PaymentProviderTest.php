<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\InitiatePayment;
use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Payment;
use App\Services\Payments\PaymentProviderException;
use App\Services\Payments\Providers\P24PaymentProvider;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class P24PaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    private const MERCHANT = 12345;

    private const POS = 12345;

    private const CRC = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private const API_KEY = 'rest_api_key_xxxxxxxxxxxxxxxxxxx';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_p24_').'.sqlite';
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

        $this->client = Client::create([
            'id' => '01HCLI0000000000000000001',
            'name' => 'Marek',
            'email' => 'marek@example.com',
        ]);

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_initiate_calls_register_and_redirects_to_p24(): void
    {
        $this->configureP24();

        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response([
                'data' => ['token' => 'TOK-abc-123'],
            ], 200),
        ]);

        $payment = app(InitiatePayment::class)->execute(
            $this->tenant,
            $this->client,
            amountCents: 12500,
            currency: 'PLN',
        );

        $this->assertSame('TOK-abc-123', $payment->provider_ref);
        $this->assertSame('https://sandbox.przelewy24.pl/trnRequest/TOK-abc-123', $payment->checkout_url);
        $this->assertSame(PaymentStatus::Processing, $payment->status);

        // Sign verification — replicate algo from production code
        Http::assertSent(function ($request) use ($payment) {
            $body = $request->data();
            $expected = hash('sha384', json_encode([
                'sessionId' => $payment->id,
                'merchantId' => self::MERCHANT,
                'amount' => 12500,
                'currency' => 'PLN',
                'crc' => self::CRC,
            ], JSON_UNESCAPED_SLASHES));

            return $body['merchantId'] === self::MERCHANT
                && $body['amount'] === 12500
                && $body['sign'] === $expected
                && ! array_key_exists('method', $body); // no force_method
        });
    }

    public function test_initiate_includes_force_method_when_set(): void
    {
        $this->configureP24(forceMethod: 154);

        Http::fake([
            '*/transaction/register' => Http::response(['data' => ['token' => 'TOK-x']], 200),
        ]);

        app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 1000);

        Http::assertSent(fn ($r) => $r->data()['method'] === 154);
    }

    public function test_initiate_marks_failed_on_p24_api_error(): void
    {
        $this->configureP24();

        Http::fake([
            '*/transaction/register' => Http::response(['error' => 'invalid sign'], 400),
        ]);

        $this->expectException(PaymentProviderException::class);

        try {
            app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 1000);
        } finally {
            $payment = Payment::query()->latest()->first();
            $this->assertNotNull($payment);
            $this->assertSame(PaymentStatus::Failed, $payment->status);
        }
    }

    public function test_webhook_with_valid_sign_calls_verify_and_marks_succeeded(): void
    {
        $this->configureP24();

        $payment = $this->seedPayment();

        Http::fake([
            '*/transaction/verify' => Http::response(['data' => ['status' => 'success']], 200),
        ]);

        $sign = $this->signWebhookPayload($payment);

        $response = $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'p24']),
            [
                'merchantId' => self::MERCHANT,
                'posId' => self::POS,
                'sessionId' => $payment->id,
                'amount' => 12500,
                'originAmount' => 12500,
                'currency' => 'PLN',
                'orderId' => 999888,
                'methodId' => 154,
                'statement' => 'P/00000/00/test',
                'sign' => $sign,
            ],
        );

        $response->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);

        Http::assertSent(function ($r) use ($payment) {
            $body = $r->data();
            $expected = hash('sha384', json_encode([
                'sessionId' => $payment->id,
                'orderId' => 999888,
                'amount' => 12500,
                'currency' => 'PLN',
                'crc' => self::CRC,
            ], JSON_UNESCAPED_SLASHES));

            return str_contains((string) $r->url(), '/transaction/verify')
                && $body['sign'] === $expected;
        });
    }

    public function test_webhook_rejects_invalid_sign(): void
    {
        $this->configureP24();
        $payment = $this->seedPayment();

        $response = $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'p24']),
            [
                'merchantId' => self::MERCHANT,
                'posId' => self::POS,
                'sessionId' => $payment->id,
                'amount' => 12500,
                'originAmount' => 12500,
                'currency' => 'PLN',
                'orderId' => 999888,
                'methodId' => 154,
                'statement' => 's',
                'sign' => 'BOGUS_SIGN',
            ],
        );

        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_webhook_rejects_merchant_mismatch(): void
    {
        $this->configureP24();
        $payment = $this->seedPayment();

        $response = $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'p24']),
            [
                'merchantId' => 99999, // not ours
                'posId' => self::POS,
                'sessionId' => $payment->id,
                'amount' => 12500,
                'originAmount' => 12500,
                'currency' => 'PLN',
                'orderId' => 1,
                'methodId' => 154,
                'statement' => 's',
                'sign' => 'whatever',
            ],
        );

        $response->assertStatus(400);
    }

    public function test_webhook_marks_failed_when_verify_fails(): void
    {
        $this->configureP24();
        $payment = $this->seedPayment();

        Http::fake([
            '*/transaction/verify' => Http::response(['data' => ['status' => 'failure']], 400),
        ]);

        $sign = $this->signWebhookPayload($payment);

        $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'p24']),
            [
                'merchantId' => self::MERCHANT,
                'posId' => self::POS,
                'sessionId' => $payment->id,
                'amount' => 12500,
                'originAmount' => 12500,
                'currency' => 'PLN',
                'orderId' => 999888,
                'methodId' => 154,
                'statement' => 'P/00000/00/test',
                'sign' => $sign,
            ],
        )->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_webhook_idempotent_does_not_undo_terminal(): void
    {
        $this->configureP24();
        $payment = $this->seedPayment(status: PaymentStatus::Succeeded);

        Http::fake([
            '*/transaction/verify' => Http::response(['data' => ['status' => 'failure']], 200),
        ]);

        $sign = $this->signWebhookPayload($payment);

        $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'p24']),
            [
                'merchantId' => self::MERCHANT,
                'posId' => self::POS,
                'sessionId' => $payment->id,
                'amount' => 12500,
                'originAmount' => 12500,
                'currency' => 'PLN',
                'orderId' => 999888,
                'methodId' => 154,
                'statement' => 'P/00000/00/test',
                'sign' => $sign,
            ],
        )->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_method_options_returns_polish_labels(): void
    {
        $opts = P24PaymentProvider::methodOptions();
        $this->assertArrayHasKey('154', $opts);
        $this->assertSame('BLIK', $opts['154']);
    }

    private function signWebhookPayload(Payment $payment): string
    {
        return hash('sha384', json_encode([
            'merchantId' => self::MERCHANT,
            'posId' => self::POS,
            'sessionId' => $payment->id,
            'amount' => 12500,
            'originAmount' => 12500,
            'currency' => 'PLN',
            'orderId' => 999888,
            'methodId' => 154,
            'statement' => 'P/00000/00/test',
            'crc' => self::CRC,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function seedPayment(PaymentStatus $status = PaymentStatus::Processing): Payment
    {
        return Payment::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'amount_cents' => 12500,
            'currency' => 'PLN',
            'provider' => 'p24',
            'provider_ref' => 'TOK-x',
            'status' => $status->value,
        ]);
    }

    private function configureP24(?int $forceMethod = null): void
    {
        $cfg = [
            'merchant_id' => self::MERCHANT,
            'pos_id' => self::POS,
            'crc_key' => Crypt::encryptString(self::CRC),
            'api_key' => Crypt::encryptString(self::API_KEY),
            'sandbox' => true,
        ];
        if ($forceMethod !== null) {
            $cfg['force_method'] = $forceMethod;
        }
        $this->tenant->forceFill([
            'settings' => [
                'payments' => [
                    'default_provider' => 'p24',
                    'p24' => $cfg,
                ],
            ],
        ])->save();
        $this->tenant->refresh();
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'p24-'.$u,
            'name' => 'P24 Stable',
            'db_name' => 'p24_'.$u,
            'db_username' => 'p24_'.substr($u, -8),
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

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('payments', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('client_id', 26);
            $t->string('calendar_entry_id', 26)->nullable();
            $t->string('pass_id', 26)->nullable();
            $t->unsignedBigInteger('amount_cents');
            $t->char('currency', 3)->default('PLN');
            $t->string('provider', 32);
            $t->string('provider_ref', 191)->nullable();
            $t->string('status', 32);
            $t->json('provider_data')->nullable();
            $t->string('checkout_url', 500)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('refunded_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
