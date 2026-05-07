<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\InitiatePayment;
use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Payment;
use App\Services\Payments\PaymentProviderException;
use App\Services\Payments\Providers\PayUPaymentProvider;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class PayUPaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    private const POS_ID = 145227;

    private const CLIENT_ID = 'oauth-client-id';

    private const CLIENT_SECRET = 'oauth-client-secret';

    private const MD5_KEY = 'second-md5-key-xxxxxxxxxxxxxxxx';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_payu_').'.sqlite';
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

    public function test_initiate_oauth2_then_create_order_returns_redirect_uri(): void
    {
        $this->configurePayU();

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'access_token' => 'tok-abc',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'secure.snd.payu.com/api/v2_1/orders' => Http::response([
                'status' => ['statusCode' => 'SUCCESS'],
                'redirectUri' => 'https://secure.snd.payu.com/pay/?orderId=ORDER-XYZ',
                'orderId' => 'ORDER-XYZ',
                'extOrderId' => 'extWillBeReplacedByPayment',
            ], 302),
        ]);

        $payment = app(InitiatePayment::class)->execute(
            $this->tenant,
            $this->client,
            amountCents: 12500,
        );

        $this->assertSame('ORDER-XYZ', $payment->provider_ref);
        $this->assertSame('https://secure.snd.payu.com/pay/?orderId=ORDER-XYZ', $payment->checkout_url);
        $this->assertSame(PaymentStatus::Processing, $payment->status);

        Http::assertSent(function ($request) use ($payment) {
            // OAuth call
            if (str_contains((string) $request->url(), '/oauth/authorize')) {
                return true;
            }

            // Order create
            return str_contains((string) $request->url(), '/api/v2_1/orders')
                && $request->data()['extOrderId'] === $payment->id
                && $request->data()['totalAmount'] === '12500'
                && $request->data()['currencyCode'] === 'PLN'
                && $request->data()['merchantPosId'] === (string) self::POS_ID;
        });
    }

    public function test_initiate_includes_force_method_when_set(): void
    {
        $this->configurePayU(forceMethod: 'blik');

        Http::fake([
            '*/oauth/authorize' => Http::response(['access_token' => 'tok'], 200),
            '*/api/v2_1/orders' => Http::response([
                'status' => ['statusCode' => 'SUCCESS'],
                'redirectUri' => 'https://x',
                'orderId' => 'ORD',
            ], 302),
        ]);

        app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 1000);

        Http::assertSent(function ($r) {
            if (! str_contains((string) $r->url(), '/orders')) {
                return true;
            }

            return $r->data()['payMethods']['payMethod']['type'] === 'PBL'
                && $r->data()['payMethods']['payMethod']['value'] === 'blik';
        });
    }

    public function test_initiate_marks_failed_when_oauth_fails(): void
    {
        $this->configurePayU();

        Http::fake([
            '*/oauth/authorize' => Http::response(['error' => 'invalid_client'], 401),
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

    public function test_initiate_marks_failed_on_order_status_not_success(): void
    {
        $this->configurePayU();

        Http::fake([
            '*/oauth/authorize' => Http::response(['access_token' => 'tok'], 200),
            '*/orders' => Http::response([
                'status' => ['statusCode' => 'WARNING_CONTINUE_3DS', 'statusDesc' => 'Need 3DS'],
            ], 200),
        ]);

        $this->expectException(PaymentProviderException::class);
        try {
            app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 1000);
        } finally {
            $this->assertSame(PaymentStatus::Failed, Payment::query()->latest()->first()->status);
        }
    }

    public function test_webhook_with_valid_md5_signature_marks_succeeded(): void
    {
        $this->configurePayU();
        $payment = $this->seedPayment();

        $body = json_encode([
            'order' => [
                'orderId' => 'ORDER-PAID-123',
                'extOrderId' => $payment->id,
                'status' => 'COMPLETED',
                'totalAmount' => '12500',
                'currencyCode' => 'PLN',
            ],
        ]);
        $signature = md5($body.self::MD5_KEY);

        $response = $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'payu']),
            content: $body,
            server: [
                'HTTP_OpenPayU-Signature' => "sender=145227;signature={$signature};algorithm=MD5;content=DOCUMENT",
                'CONTENT_TYPE' => 'application/json',
            ],
        );

        $response->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->configurePayU();
        $payment = $this->seedPayment();

        $body = json_encode(['order' => ['orderId' => 'X', 'extOrderId' => $payment->id, 'status' => 'COMPLETED']]);
        $bogus = md5($body.'WRONG_KEY');

        $response = $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'payu']),
            content: $body,
            server: [
                'HTTP_OpenPayU-Signature' => "signature={$bogus};algorithm=MD5",
                'CONTENT_TYPE' => 'application/json',
            ],
        );

        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_webhook_canceled_marks_failed(): void
    {
        $this->configurePayU();
        $payment = $this->seedPayment();

        $body = json_encode(['order' => ['orderId' => 'X', 'extOrderId' => $payment->id, 'status' => 'CANCELED']]);
        $sig = md5($body.self::MD5_KEY);

        $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'payu']),
            content: $body,
            server: [
                'HTTP_OpenPayU-Signature' => "signature={$sig};algorithm=MD5",
                'CONTENT_TYPE' => 'application/json',
            ],
        )->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_webhook_pending_status_does_not_change_payment(): void
    {
        $this->configurePayU();
        $payment = $this->seedPayment();

        $body = json_encode(['order' => ['orderId' => 'X', 'extOrderId' => $payment->id, 'status' => 'PENDING']]);
        $sig = md5($body.self::MD5_KEY);

        $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'payu']),
            content: $body,
            server: [
                'HTTP_OpenPayU-Signature' => "signature={$sig};algorithm=MD5",
                'CONTENT_TYPE' => 'application/json',
            ],
        )->assertOk();

        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_webhook_idempotent_on_duplicate_terminal(): void
    {
        $this->configurePayU();
        $payment = $this->seedPayment(status: PaymentStatus::Succeeded);

        $body = json_encode(['order' => ['orderId' => 'X', 'extOrderId' => $payment->id, 'status' => 'CANCELED']]);
        $sig = md5($body.self::MD5_KEY);

        $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'payu']),
            content: $body,
            server: [
                'HTTP_OpenPayU-Signature' => "signature={$sig};algorithm=MD5",
                'CONTENT_TYPE' => 'application/json',
            ],
        )->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_method_options_returns_polish_labels(): void
    {
        $opts = PayUPaymentProvider::methodOptions();
        $this->assertArrayHasKey('blik', $opts);
        $this->assertArrayHasKey('jp', $opts);
        $this->assertSame('BLIK', $opts['blik']);
    }

    private function seedPayment(PaymentStatus $status = PaymentStatus::Processing): Payment
    {
        return Payment::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'amount_cents' => 12500,
            'currency' => 'PLN',
            'provider' => 'payu',
            'provider_ref' => 'ORD-x',
            'status' => $status->value,
        ]);
    }

    private function configurePayU(?string $forceMethod = null): void
    {
        $cfg = [
            'pos_id' => self::POS_ID,
            'client_id' => self::CLIENT_ID,
            'client_secret' => Crypt::encryptString(self::CLIENT_SECRET),
            'md5_key' => Crypt::encryptString(self::MD5_KEY),
            'sandbox' => true,
        ];
        if ($forceMethod !== null) {
            $cfg['force_method'] = $forceMethod;
        }

        $this->tenant->forceFill([
            'settings' => [
                'payments' => [
                    'default_provider' => 'payu',
                    'payu' => $cfg,
                ],
            ],
        ])->save();
        $this->tenant->refresh();
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'payu-'.$u,
            'name' => 'PayU Stable',
            'db_name' => 'payu_'.$u,
            'db_username' => 'payu_'.substr($u, -8),
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
