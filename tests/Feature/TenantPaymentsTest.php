<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Payments\InitiatePayment;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Payment;
use App\Services\Payments\PaymentProviderException;
use App\Services\Payments\PaymentProviderRegistry;
use App\Services\Payments\Providers\MolliePaymentProvider;
use App\Services\Payments\Providers\P24PaymentProvider;
use App\Services\Payments\Providers\PayUPaymentProvider;
use App\Services\Payments\Providers\StripePaymentProvider;
use App\Services\Payments\Providers\StubPaymentProvider;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class TenantPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_pay_').'.sqlite';
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

    public function test_registry_resolves_each_provider(): void
    {
        $reg = app(PaymentProviderRegistry::class);

        $this->assertInstanceOf(StubPaymentProvider::class, $reg->for(PaymentProvider::Stub));
        $this->assertInstanceOf(P24PaymentProvider::class, $reg->for(PaymentProvider::P24));
        $this->assertInstanceOf(PayUPaymentProvider::class, $reg->for(PaymentProvider::PayU));
        $this->assertInstanceOf(StripePaymentProvider::class, $reg->for(PaymentProvider::Stripe));
        $this->assertInstanceOf(MolliePaymentProvider::class, $reg->for(PaymentProvider::Mollie));
    }

    public function test_registry_throws_for_none_provider(): void
    {
        $this->expectException(PaymentProviderException::class);
        app(PaymentProviderRegistry::class)->for(PaymentProvider::None);
    }

    public function test_default_for_reads_tenant_settings(): void
    {
        $this->setDefaultProvider('stub');

        $provider = app(PaymentProviderRegistry::class)->defaultFor($this->tenant);

        $this->assertSame('stub', $provider->id());
    }

    public function test_default_for_falls_back_to_none_when_unset(): void
    {
        // No payments key in settings — should be treated as None
        $this->expectException(PaymentProviderException::class);
        app(PaymentProviderRegistry::class)->defaultFor($this->tenant);
    }

    public function test_initiate_creates_payment_and_returns_checkout_url(): void
    {
        $this->setDefaultProvider('stub');

        $payment = app(InitiatePayment::class)->execute(
            $this->tenant,
            $this->client,
            amountCents: 12500,
            currency: 'PLN',
            context: ['metadata' => ['note' => 'lekcja indywidualna']],
        );

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(PaymentStatus::Processing, $payment->status);
        $this->assertSame(PaymentProvider::Stub, $payment->provider);
        $this->assertSame(12500, $payment->amount_cents);
        $this->assertNotNull($payment->checkout_url);
        $this->assertStringContainsString($payment->id, $payment->checkout_url);
        $this->assertSame('lekcja indywidualna', data_get($payment->metadata, 'note'));
    }

    public function test_initiate_marks_failed_when_provider_misconfigured(): void
    {
        // Pick P24 (real provider stub) which throws notImplemented + notConfigured
        $this->setDefaultProvider('p24');

        try {
            app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 5000);
            $this->fail('Expected PaymentProviderException');
        } catch (PaymentProviderException) {
            // expected
        }

        $payment = Payment::query()->latest()->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertNotEmpty(data_get($payment->metadata, 'init_error'));
    }

    public function test_stub_webhook_marks_payment_succeeded(): void
    {
        $this->setDefaultProvider('stub');
        $payment = app(InitiatePayment::class)->execute($this->tenant, $this->client, amountCents: 5000);

        $response = $this->postJson(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'stub']),
            ['ref' => $payment->provider_ref, 'status' => 'succeeded'],
        );

        $response->assertOk();
        $payment->refresh();
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_webhook_404_for_unknown_provider(): void
    {
        $this->postJson(
            route('public.payments.webhook', ['slug' => $this->tenant->slug, 'provider' => 'wat']),
            [],
        )->assertNotFound();
    }

    public function test_webhook_404_for_unknown_tenant_slug(): void
    {
        $this->postJson('/s/nonexistent/payments/stub/webhook', [])->assertNotFound();
    }

    public function test_payment_status_enum_terminal_set(): void
    {
        $this->assertTrue(PaymentStatus::Succeeded->isTerminal());
        $this->assertTrue(PaymentStatus::Failed->isTerminal());
        $this->assertTrue(PaymentStatus::Refunded->isTerminal());
        $this->assertFalse(PaymentStatus::Pending->isTerminal());
        $this->assertFalse(PaymentStatus::Processing->isTerminal());
    }

    public function test_settings_credentials_round_trip_encrypted(): void
    {
        // Simulate what PaymentSettings::save does — store encrypted, then
        // verify a Crypt::decryptString round-trips the original value.
        $secret = 'sk_live_super_secret_xyz';
        $encrypted = Crypt::encryptString($secret);

        $this->tenant->forceFill([
            'settings' => ['payments' => ['default_provider' => 'stripe', 'stripe' => ['secret_key' => $encrypted]]],
        ])->save();

        $stored = data_get($this->tenant->fresh()->settings, 'payments.stripe.secret_key');
        $this->assertNotSame($secret, $stored);
        $this->assertSame($secret, Crypt::decryptString($stored));
    }

    private function setDefaultProvider(string $code): void
    {
        $settings = (array) ($this->tenant->settings ?? []);
        $settings['payments'] = array_merge(
            (array) ($settings['payments'] ?? []),
            ['default_provider' => $code],
        );
        $this->tenant->forceFill(['settings' => $settings])->save();
        $this->tenant->refresh();
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'pay-'.$u,
            'name' => 'Pay Stable',
            'db_name' => 'pay_'.$u,
            'db_username' => 'pay_'.substr($u, -8),
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

        Schema::connection('tenant')->create('calendar_entries', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 32);
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('client_id', 26)->nullable();
            $t->string('status', 32);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('passes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('client_id', 26);
            $t->string('name', 120);
            $t->unsignedSmallInteger('total_uses');
            $t->smallInteger('remaining_uses');
            $t->string('status', 32)->default('active');
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
