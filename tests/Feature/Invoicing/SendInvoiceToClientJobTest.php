<?php

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Jobs\Stable\SendInvoiceToClientJob;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Notifications\InvoiceIssuedClientNotification;
use App\Services\Portal\ClientMessageJournal;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * SendInvoiceToClientJob — async wrapper na single-row email logic.
 * Test pokrywa: idempotency (skip gdy email_sent_at set), force flag,
 * draft skip (tylko posted FV), brak emaila → silent skip.
 */
class SendInvoiceToClientJobTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_jobinv_').'.sqlite';
        touch($this->tenantDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->bootTenant();

        // ClientMessageJournal is a noop in tests — tenant DB has no
        // client_messages table seeded here, and we don't need to assert
        // the journal write to verify mail dispatch.
        $this->app->instance(
            ClientMessageJournal::class,
            Mockery::mock(ClientMessageJournal::class)->shouldIgnoreMissing(),
        );

        Notification::fake();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_dispatches_mail_and_marks_email_sent_at(): void
    {
        $invoice = $this->makeInvoiceWithClient(status: InvoiceStatus::Issued);
        $this->assertNull($invoice->email_sent_at);

        (new SendInvoiceToClientJob(
            tenantId: $this->tenant->id,
            invoiceId: $invoice->id,
        ))->handle(app(TenantManager::class));

        Notification::assertSentOnDemand(InvoiceIssuedClientNotification::class);

        $invoice->refresh();
        $this->assertNotNull($invoice->email_sent_at);
    }

    public function test_skips_when_email_already_sent(): void
    {
        $invoice = $this->makeInvoiceWithClient(status: InvoiceStatus::Issued);
        $invoice->forceFill(['email_sent_at' => now()->subDay()])->save();

        (new SendInvoiceToClientJob(
            tenantId: $this->tenant->id,
            invoiceId: $invoice->id,
        ))->handle(app(TenantManager::class));

        Notification::assertNothingSent();
    }

    public function test_force_flag_resends_even_when_already_sent(): void
    {
        $invoice = $this->makeInvoiceWithClient(status: InvoiceStatus::Issued);
        $invoice->forceFill(['email_sent_at' => now()->subDay()])->save();

        (new SendInvoiceToClientJob(
            tenantId: $this->tenant->id,
            invoiceId: $invoice->id,
            force: true,
        ))->handle(app(TenantManager::class));

        Notification::assertSentOnDemand(InvoiceIssuedClientNotification::class);
    }

    public function test_skips_draft_invoices(): void
    {
        $invoice = $this->makeInvoiceWithClient(status: InvoiceStatus::Draft);

        (new SendInvoiceToClientJob(
            tenantId: $this->tenant->id,
            invoiceId: $invoice->id,
        ))->handle(app(TenantManager::class));

        Notification::assertNothingSent();
        $this->assertNull($invoice->fresh()->email_sent_at);
    }

    public function test_skips_when_client_has_no_email(): void
    {
        $invoice = $this->makeInvoiceWithClient(status: InvoiceStatus::Issued, clientEmail: null);

        (new SendInvoiceToClientJob(
            tenantId: $this->tenant->id,
            invoiceId: $invoice->id,
        ))->handle(app(TenantManager::class));

        Notification::assertNothingSent();
        $this->assertNull($invoice->fresh()->email_sent_at);
    }

    private function makeInvoiceWithClient(InvoiceStatus $status, ?string $clientEmail = 'klient@test.pl'): Invoice
    {
        $client = Client::create([
            'id' => '01HCLIENT0000000000000ABCD',
            'type' => 'individual',
            'name' => 'Anna Kowalska',
            'email' => $clientEmail,
        ]);

        return Invoice::create([
            'kind' => InvoiceKind::Fv->value,
            'status' => $status->value,
            'client_id' => $client->id,
            'number' => 'FV/2026/06/0001',
            'seller_name' => 'Stajnia',
            'buyer_name' => $client->name,
            'buyer_type' => 'individual',
            'currency' => 'PLN',
            'subtotal_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
            'issued_at' => now()->toDateString(),
            'due_at' => now()->addDays(14)->toDateString(),
        ]);
    }

    private function bootTenant(): void
    {
        $this->tenant = Tenant::create([
            'slug' => 'job-test',
            'name' => 'Job Test Stable',
            'db_name' => 'irrelevant',
            'db_username' => 'irrelevant',
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'settings' => [],
        ]);

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $this->tenant);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('clients', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('street')->nullable();
            $t->string('postal_code', 16)->nullable();
            $t->string('city', 120)->nullable();
            $t->char('country', 2)->default('PL');
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('invoices', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable()->unique();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26)->nullable();
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
            $t->string('buyer_type', 16)->default('individual');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('email_sent_at')->nullable();
            $t->string('currency', 3)->default('PLN');
            $t->integer('subtotal_cents')->default(0);
            $t->integer('vat_cents')->default(0);
            $t->integer('total_cents')->default(0);
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
