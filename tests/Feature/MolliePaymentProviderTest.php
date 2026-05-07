<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\InitiatePayment;
use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Payment;
use App\Services\Payments\PaymentProviderException;
use App\Services\Payments\Providers\MolliePaymentProvider;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class MolliePaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_mollie_').'.sqlite';
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

    public function test_initiate_creates_mollie_payment_returns_checkout_url(): void
    {
        $this->configureMollie(enabledMethods: ['ideal', 'creditcard']);

        Http::fake([
            'api.mollie.com/v2/payments' => Http::response([
                'id' => 'tr_test_abc',
                'status' => 'open',
                '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/tr_test_abc']],
            ], 201),
        ]);

        $payment = app(InitiatePayment::class)->execute(
            $this->tenant,
            $this->client,
            amountCents: 12500,
            currency: 'PLN',
        );

        $this->assertSame('tr_test_abc', $payment->provider_ref);
        $this->assertSame('https://www.mollie.com/checkout/tr_test_abc', $payment->checkout_url);
        $this->assertSame(PaymentStatus::Processing, $payment->status);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.mollie.com/v2/payments'
                && $body['amount']['currency'] === 'PLN'
                && $body['amount']['value'] === '125.00'
                // Multiple methods array
                && is_array($body['method'])
                && in_array('ideal', $body['method'], true)
                && in_array('creditcard', $body['method'], true);
        });
    }

    public function test_initiate_single_method_passed_as_string(): void
    {
        $this->configureMollie(enabledMethods: ['blik']);

        Http::fake([
            'api.mollie.com/*' => Http::response([
                'id' => 'tr_x',
                '_links' => ['checkout' => ['href' => 'https://m.test/x']],
            ], 201),
        ]);

        app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 1000);

        Http::assertSent(function ($r) {
            return $r->data()['method'] === 'blik'; // string, not array
        });
    }

    public function test_initiate_no_method_field_when_empty_list(): void
    {
        $this->configureMollie(enabledMethods: []);

        Http::fake([
            'api.mollie.com/*' => Http::response([
                'id' => 'tr_x',
                '_links' => ['checkout' => ['href' => 'https://m.test/x']],
            ], 201),
        ]);

        app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 1000);

        Http::assertSent(fn ($r) => ! array_key_exists('method', $r->data()));
    }

    public function test_initiate_marks_failed_on_api_error(): void
    {
        $this->configureMollie();

        Http::fake([
            'api.mollie.com/*' => Http::response([
                'status' => 401,
                'title' => 'Unauthorized Request',
                'detail' => 'Invalid API key',
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

    public function test_webhook_fetches_and_marks_succeeded_when_paid(): void
    {
        $this->configureMollie();

        $payment = $this->seedPayment('tr_test_paid');

        Http::fake([
            'api.mollie.com/v2/payments/tr_test_paid' => Http::response([
                'id' => 'tr_test_paid',
                'status' => 'paid',
                'amount' => ['currency' => 'PLN', 'value' => '125.00'],
            ], 200),
        ]);

        $response = $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'mollie']),
            ['id' => 'tr_test_paid'],
        );

        $response->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_webhook_marks_failed_on_canceled_status(): void
    {
        $this->configureMollie();
        $payment = $this->seedPayment('tr_canceled');

        Http::fake([
            'api.mollie.com/*' => Http::response(['id' => 'tr_canceled', 'status' => 'canceled'], 200),
        ]);

        $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'mollie']),
            ['id' => 'tr_canceled'],
        )->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_webhook_open_status_does_not_change_payment(): void
    {
        $this->configureMollie();
        $payment = $this->seedPayment('tr_open');

        Http::fake([
            'api.mollie.com/*' => Http::response(['id' => 'tr_open', 'status' => 'open'], 200),
        ]);

        $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'mollie']),
            ['id' => 'tr_open'],
        )->assertOk();

        // 'open' is non-terminal, status pozostaje Processing
        $this->assertSame(PaymentStatus::Processing, $payment->fresh()->status);
    }

    public function test_webhook_returns_400_on_missing_id(): void
    {
        $this->configureMollie();

        $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'mollie']),
            [],
        )->assertStatus(400);
    }

    public function test_webhook_returns_404_for_unknown_payment(): void
    {
        $this->configureMollie();

        Http::fake([
            'api.mollie.com/*' => Http::response(['id' => 'tr_orphan', 'status' => 'paid'], 200),
        ]);

        $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'mollie']),
            ['id' => 'tr_orphan'],
        )->assertStatus(404);
    }

    public function test_webhook_idempotent_does_not_undo_terminal(): void
    {
        $this->configureMollie();
        $payment = $this->seedPayment('tr_paid_already', status: PaymentStatus::Succeeded);

        Http::fake([
            'api.mollie.com/*' => Http::response(['id' => 'tr_paid_already', 'status' => 'failed'], 200),
        ]);

        $this->post(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'mollie']),
            ['id' => 'tr_paid_already'],
        )->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_method_options_returns_polish_labels(): void
    {
        $opts = MolliePaymentProvider::methodOptions();
        $this->assertArrayHasKey('blik', $opts);
        $this->assertArrayHasKey('ideal', $opts);
        $this->assertStringContainsString('NL', $opts['ideal']);
    }

    private function seedPayment(string $providerRef, PaymentStatus $status = PaymentStatus::Processing): Payment
    {
        return Payment::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'amount_cents' => 12500,
            'currency' => 'PLN',
            'provider' => 'mollie',
            'provider_ref' => $providerRef,
            'status' => $status->value,
        ]);
    }

    private function configureMollie(?array $enabledMethods = null): void
    {
        $cfg = ['api_key' => Crypt::encryptString('test_apiKey_xxx')];
        if ($enabledMethods !== null) {
            $cfg['enabled_methods'] = $enabledMethods;
        }
        $this->tenant->forceFill([
            'settings' => [
                'payments' => [
                    'default_provider' => 'mollie',
                    'mollie' => $cfg,
                ],
            ],
        ])->save();
        $this->tenant->refresh();
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'mollie-'.$u,
            'name' => 'Mollie Stable',
            'db_name' => 'mollie_'.$u,
            'db_username' => 'mollie_'.substr($u, -8),
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
