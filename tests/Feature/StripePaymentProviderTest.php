<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\InitiatePayment;
use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Payment;
use App\Services\Payments\PaymentProviderException;
use App\Services\Payments\Providers\StripePaymentProvider;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class StripePaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_stripe_').'.sqlite';
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

    public function test_initiate_creates_checkout_session_and_returns_url(): void
    {
        $this->configureStripe(enabledMethods: ['card', 'blik', 'p24']);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_abc123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc123',
                'mode' => 'payment',
            ], 200),
        ]);

        $payment = app(InitiatePayment::class)->execute(
            $this->tenant,
            $this->client,
            amountCents: 12500,
        );

        $this->assertSame('cs_test_abc123', $payment->provider_ref);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_abc123', $payment->checkout_url);
        $this->assertSame(PaymentStatus::Processing, $payment->status);

        Http::assertSent(function ($request) {
            $body = (string) $request->body();

            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && str_contains($body, 'payment_method_types%5B0%5D=card')
                && str_contains($body, 'payment_method_types%5B1%5D=blik')
                && str_contains($body, 'payment_method_types%5B2%5D=p24');
        });
    }

    public function test_initiate_falls_back_to_card_when_no_methods_enabled(): void
    {
        $this->configureStripe(enabledMethods: []);

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'cs_test_x',
                'url' => 'https://stripe.test/x',
            ], 200),
        ]);

        app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 1000);

        Http::assertSent(fn ($r) => str_contains((string) $r->body(), 'payment_method_types%5B0%5D=card'));
    }

    public function test_initiate_filters_unsupported_methods(): void
    {
        // Bogus method 'xyz' should be filtered out — only valid Stripe methods pass through
        $this->configureStripe(enabledMethods: ['card', 'xyz', 'blik']);

        Http::fake([
            'api.stripe.com/*' => Http::response(['id' => 'cs_x', 'url' => 'https://s.test/x'], 200),
        ]);

        app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 1000);

        Http::assertSent(function ($r) {
            $body = (string) $r->body();

            return str_contains($body, 'payment_method_types%5B0%5D=card')
                && str_contains($body, 'payment_method_types%5B1%5D=blik')
                && ! str_contains($body, 'xyz');
        });
    }

    public function test_initiate_marks_failed_on_stripe_api_error(): void
    {
        $this->configureStripe();

        Http::fake([
            'api.stripe.com/*' => Http::response([
                'error' => ['message' => 'Invalid API key', 'type' => 'invalid_request_error'],
            ], 401),
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

    public function test_webhook_with_valid_signature_marks_succeeded(): void
    {
        $secret = 'whsec_test_secret';
        $this->configureStripe(webhookSecret: $secret);

        $payment = $this->seedPayment('cs_test_abc');

        $body = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_abc', 'status' => 'complete']],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $response = $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'stripe']),
            content: $body,
            server: [
                'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
        );

        $response->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->configureStripe(webhookSecret: 'whsec_correct');

        $payment = $this->seedPayment('cs_test_abc');

        $body = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_abc']]]);
        $signature = hash_hmac('sha256', time().'.'.$body, 'whsec_WRONG');

        $response = $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'stripe']),
            content: $body,
            server: [
                'HTTP_Stripe-Signature' => 't='.time().',v1='.$signature,
                'CONTENT_TYPE' => 'application/json',
            ],
        );

        $response->assertStatus(400);
        // Payment status nie zmienia się
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_webhook_rejects_stale_signature(): void
    {
        $secret = 'whsec_test';
        $this->configureStripe(webhookSecret: $secret);
        $this->seedPayment('cs_test_abc');

        $body = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_abc']]]);
        $oldTimestamp = time() - 600; // 10 minutes ago, > 5 min tolerance
        $signature = hash_hmac('sha256', $oldTimestamp.'.'.$body, $secret);

        $response = $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'stripe']),
            content: $body,
            server: [
                'HTTP_Stripe-Signature' => "t={$oldTimestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
        );

        $response->assertStatus(400);
    }

    public function test_webhook_idempotent_does_not_reset_succeeded_to_failed(): void
    {
        $secret = 'whsec_test';
        $this->configureStripe(webhookSecret: $secret);

        $payment = $this->seedPayment('cs_test_abc', status: PaymentStatus::Succeeded);

        $body = json_encode([
            'type' => 'checkout.session.expired',
            'data' => ['object' => ['id' => 'cs_test_abc']],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $this->call(
            'POST',
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'stripe']),
            content: $body,
            server: [
                'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
                'CONTENT_TYPE' => 'application/json',
            ],
        )->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_method_options_returns_polish_labels(): void
    {
        $opts = StripePaymentProvider::methodOptions();
        $this->assertArrayHasKey('card', $opts);
        $this->assertArrayHasKey('blik', $opts);
        $this->assertArrayHasKey('p24', $opts);
        $this->assertStringContainsString('BLIK', $opts['blik']);
    }

    private function seedPayment(string $providerRef, PaymentStatus $status = PaymentStatus::Processing): Payment
    {
        return Payment::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'amount_cents' => 12500,
            'currency' => 'PLN',
            'provider' => 'stripe',
            'provider_ref' => $providerRef,
            'status' => $status->value,
        ]);
    }

    private function configureStripe(
        ?array $enabledMethods = null,
        string $webhookSecret = 'whsec_test',
    ): void {
        $stripeCfg = [
            'secret_key' => Crypt::encryptString('sk_test_xxxxxx'),
            'webhook_secret' => Crypt::encryptString($webhookSecret),
        ];
        if ($enabledMethods !== null) {
            $stripeCfg['enabled_methods'] = $enabledMethods;
        }

        $this->tenant->forceFill([
            'settings' => [
                'payments' => [
                    'default_provider' => 'stripe',
                    'stripe' => $stripeCfg,
                ],
            ],
        ])->save();
        $this->tenant->refresh();
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'stripe-'.$u,
            'name' => 'Stripe Stable',
            'db_name' => 'stripe_'.$u,
            'db_username' => 'stripe_'.substr($u, -8),
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
