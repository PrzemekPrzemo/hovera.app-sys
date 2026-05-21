<?php

declare(strict_types=1);

namespace Tests\Feature\Owner\Invoicing;

use App\Domain\Invoicing\Owner\OwnerInvoicePaymentService;
use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\PaymentProviderRegistry;
use App\Tenancy\TenantManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Pokrywa C.6 z OWNER-STABLE-ROADMAP — owner klika "Zapłać" na fakturze,
 * service kreuje payment session w stable tenant context, zwraca
 * checkout URL.
 *
 * Provider P24/PayU mockowany (bo realnie biłby do sandbox HTTP API).
 */
class OwnerInvoicePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $stableDbPath;

    private Tenant $stable;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stableDbPath = tempnam(sys_get_temp_dir(), 'hov_pay_').'.sqlite';
        touch($this->stableDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->stableDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpStableSchema();

        $held = null;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('tenantOrFail')->andReturnUsing(fn () => $held);
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
            $m->shouldReceive('execute')->andReturnUsing(function (Tenant $t, callable $cb) use (&$held) {
                $prev = $held;
                $held = $t;
                try {
                    return $cb($t);
                } finally {
                    $held = $prev;
                }
            });
        });

        $this->owner = User::create(['name' => 'Jan', 'email' => 'jan-'.uniqid().'@x.test', 'password' => bcrypt('x')]);

        $this->stable = $this->makeStable([
            'payments' => ['default_provider' => 'p24'],
        ]);

        // Mock payment provider — returns hardcoded checkout URL.
        $fakeProvider = Mockery::mock(PaymentProviderInterface::class);
        $fakeProvider->shouldReceive('id')->andReturn('p24');
        $fakeProvider->shouldReceive('initiate')->andReturnUsing(function ($tenant, $payment) {
            $payment->forceFill([
                'checkout_url' => 'https://secure.przelewy24.pl/trnRequest/test-token',
                'status' => 'processing',
            ])->save();

            return 'https://secure.przelewy24.pl/trnRequest/test-token';
        });

        $this->mock(PaymentProviderRegistry::class, function ($m) use ($fakeProvider) {
            $m->shouldReceive('defaultFor')->andReturn($fakeProvider);
            $m->shouldReceive('for')->andReturn($fakeProvider);
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->stableDbPath);
        Mockery::close();
        parent::tearDown();
    }

    public function test_initiate_returns_checkout_url_for_valid_invoice(): void
    {
        $client = $this->seedClient();
        $invoice = $this->seedInvoice($client, InvoiceStatus::Issued);

        $url = app(OwnerInvoicePaymentService::class)
            ->initiate($this->owner, $this->stable->id, $invoice);

        $this->assertStringContainsString('przelewy24.pl', $url);
    }

    public function test_initiate_throws_when_stable_has_no_payment_provider(): void
    {
        // Different stable z `payments.default_provider = none`.
        $brokenStable = $this->makeStable(['payments' => ['default_provider' => 'none']]);
        $this->expectExceptionMessage(__('owner/invoices.pay.provider_not_configured'));

        app(OwnerInvoicePaymentService::class)
            ->initiate($this->owner, $brokenStable->id, 'fake-invoice-id');
    }

    public function test_initiate_throws_when_invoice_belongs_to_other_owner(): void
    {
        $otherUser = User::create(['name' => 'X', 'email' => 'x-'.uniqid().'@x.test', 'password' => bcrypt('x')]);
        $otherClient = $this->seedClient($otherUser->id);
        $invoice = $this->seedInvoice($otherClient, InvoiceStatus::Issued);

        $this->expectException(AuthorizationException::class);

        app(OwnerInvoicePaymentService::class)
            ->initiate($this->owner, $this->stable->id, $invoice);
    }

    public function test_initiate_throws_when_invoice_already_paid(): void
    {
        $client = $this->seedClient();
        $invoice = $this->seedInvoice($client, InvoiceStatus::Paid);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('owner/invoices.pay.already_paid'));

        app(OwnerInvoicePaymentService::class)
            ->initiate($this->owner, $this->stable->id, $invoice);
    }

    public function test_initiate_throws_when_invoice_draft(): void
    {
        $client = $this->seedClient();
        $invoice = $this->seedInvoice($client, InvoiceStatus::Draft);

        $this->expectException(RuntimeException::class);

        app(OwnerInvoicePaymentService::class)
            ->initiate($this->owner, $this->stable->id, $invoice);
    }

    public function test_stable_supports_payments_returns_correctly(): void
    {
        $service = app(OwnerInvoicePaymentService::class);

        $this->assertTrue($service->stableSupportsPayments($this->stable));

        $broken = $this->makeStable(['payments' => ['default_provider' => 'none']]);
        $this->assertFalse($service->stableSupportsPayments($broken));

        $noSettings = $this->makeStable([]);
        $this->assertFalse($service->stableSupportsPayments($noSettings));
    }

    private function seedClient(?string $centralUserId = null): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('clients')->insert([
            'id' => $id,
            'name' => 'Jan Owner',
            'central_user_id' => $centralUserId ?? $this->owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function seedInvoice(string $clientId, InvoiceStatus $status): string
    {
        $id = (string) Str::ulid();
        DB::connection('tenant')->table('invoices')->insert([
            'id' => $id,
            'number' => 'FV/'.random_int(1000, 9999),
            'kind' => InvoiceKind::Fv->value,
            'status' => $status->value,
            'client_id' => $clientId,
            'seller_name' => 'Stable',
            'buyer_name' => 'Jan',
            'currency' => 'PLN',
            'subtotal_cents' => 100000,
            'vat_cents' => 23000,
            'total_cents' => 123000,
            'issued_at' => $status !== InvoiceStatus::Draft ? now()->toDateString() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string,mixed> $settings */
    private function makeStable(array $settings): Tenant
    {
        return Tenant::create([
            'slug' => 'pay-'.Str::random(6),
            'name' => 'Pay Stable',
            'type' => TenantType::Stable,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_'.Str::random(8),
            'db_username' => 'hovera_t_'.Str::random(8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => $settings,
        ]);
    }

    private function setUpStableSchema(): void
    {
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 24)->default('individual');
            $t->string('name', 200);
            $t->string('email')->nullable();
            $t->string('central_user_id', 26)->nullable();
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
            $t->string('buyer_name');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('payments', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('client_id', 26);
            $t->string('calendar_entry_id', 26)->nullable();
            $t->string('pass_id', 26)->nullable();
            $t->string('invoice_id', 26)->nullable();
            $t->bigInteger('amount_cents');
            $t->char('currency', 3)->default('PLN');
            $t->string('provider', 32);
            $t->string('provider_ref', 191)->nullable();
            $t->string('status', 32);
            $t->json('provider_data')->nullable();
            $t->string('checkout_url', 1000)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('refunded_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
