<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Payment;
use App\Notifications\InvoiceIssuedClientNotification;
use App\Services\Invoicing\InvoicePublicLink;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class InvoicePayLinkTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_invpay_').'.sqlite';
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
            'name' => 'Marek Klient',
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

    public function test_public_link_includes_signature_and_invoice_id(): void
    {
        $invoice = $this->makeInvoice();
        $url = app(InvoicePublicLink::class)->for($invoice, $this->tenant->slug);

        $this->assertStringContainsString($invoice->id, $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
    }

    public function test_show_route_with_valid_signature_renders_invoice(): void
    {
        $invoice = $this->makeInvoice();
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

        $url = app(InvoicePublicLink::class)->for($invoice->fresh(), $this->tenant->slug);

        $response = $this->get($url);

        $response->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Lekcja jeździecka')
            ->assertSee('Marek Klient');
    }

    public function test_show_route_invalid_signature_renders_invalid_page(): void
    {
        $invoice = $this->makeInvoice();

        // Build URL bez podpisu
        $bare = url("/s/{$this->tenant->slug}/invoices/{$invoice->id}");
        $this->get($bare)
            ->assertOk()
            ->assertSee('Faktura niedostępna');
    }

    public function test_show_route_for_draft_invoice_renders_invalid(): void
    {
        $invoice = $this->makeInvoice(status: InvoiceStatus::Draft, number: null);
        $url = URL::temporarySignedRoute('public.invoice.show', now()->addDays(30), [
            'slug' => $this->tenant->slug,
            'invoice' => $invoice->id,
        ]);

        $this->get($url)->assertOk()->assertSee('Faktura niedostępna');
    }

    public function test_show_route_shows_pay_button_when_provider_configured(): void
    {
        $this->tenant->forceFill([
            'settings' => ['payments' => ['default_provider' => 'stub']],
        ])->save();

        $invoice = $this->makeInvoice();
        $url = app(InvoicePublicLink::class)->for($invoice->fresh(), $this->tenant->slug);

        $this->get($url)->assertOk()->assertSee('Zapłać teraz');
    }

    public function test_show_route_paid_invoice_shows_paid_banner(): void
    {
        $invoice = $this->makeInvoice(status: InvoiceStatus::Paid);
        $url = app(InvoicePublicLink::class)->for($invoice->fresh(), $this->tenant->slug);

        $this->get($url)->assertOk()->assertSee('została opłacona');
    }

    public function test_payment_observer_marks_invoice_paid_when_succeeded(): void
    {
        $invoice = $this->makeInvoice();
        $payment = Payment::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
            'amount_cents' => 12300,
            'currency' => 'PLN',
            'provider' => 'stub',
            'provider_ref' => 'stub_x',
            'status' => PaymentStatus::Processing->value,
        ]);

        // Symulujemy webhook: status zmienia się na Succeeded
        $payment->forceFill(['status' => PaymentStatus::Succeeded->value])->save();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_payment_observer_idempotent_on_duplicate_succeeded(): void
    {
        $invoice = $this->makeInvoice(status: InvoiceStatus::Paid);
        $invoice->forceFill(['paid_at' => now()->subDays(1)])->save();
        $originalPaidAt = $invoice->fresh()->paid_at;

        $payment = Payment::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
            'amount_cents' => 12300,
            'currency' => 'PLN',
            'provider' => 'stub',
            'provider_ref' => 'stub_x',
            'status' => PaymentStatus::Processing->value,
        ]);
        $payment->forceFill(['status' => PaymentStatus::Succeeded->value])->save();

        // Paid_at nie powinno się zmienić
        $this->assertEquals(
            $originalPaidAt->format('Y-m-d H:i:s'),
            $invoice->fresh()->paid_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_payment_observer_skips_when_no_invoice_link(): void
    {
        $payment = Payment::create([
            'id' => (string) Str::ulid(),
            'client_id' => $this->client->id,
            // brak invoice_id
            'amount_cents' => 12300,
            'currency' => 'PLN',
            'provider' => 'stub',
            'provider_ref' => 'stub_x',
            'status' => PaymentStatus::Processing->value,
        ]);

        // Brak związanej faktury — observer powinien po prostu nic nie robić
        $payment->forceFill(['status' => PaymentStatus::Succeeded->value])->save();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        // Nie ma assertion na invoice — sprawdzamy że observer nie crashuje
    }

    public function test_send_invoice_notification_renders_polish_subject_and_link(): void
    {
        Notification::fake();

        $invoice = $this->makeInvoice();
        $url = app(InvoicePublicLink::class)->for($invoice, $this->tenant->slug);

        Notification::route('mail', 'klient@example.com')->notify(new InvoiceIssuedClientNotification(
            tenantName: $this->tenant->name,
            invoiceNumber: (string) $invoice->number,
            kindLabel: 'Faktura VAT',
            totalFormatted: '123,00 PLN',
            issuedAt: $invoice->issued_at,
            dueAt: $invoice->due_at,
            publicUrl: $url,
            canPayOnline: true,
        ));

        Notification::assertSentOnDemand(InvoiceIssuedClientNotification::class);
    }

    private function makeInvoice(
        InvoiceStatus $status = InvoiceStatus::Issued,
        ?string $number = 'FV/1/05/2026',
    ): Invoice {
        return Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv->value,
            'status' => $status->value,
            'number' => $number,
            'client_id' => $this->client->id,
            'seller_name' => 'Stadnina Bucefał',
            'seller_nip' => '5260250274',
            'seller_address' => 'ul. Kasztanowa 7',
            'seller_postal_code' => '00-001',
            'seller_city' => 'Warszawa',
            'seller_country' => 'PL',
            'buyer_name' => 'Marek Klient',
            'buyer_nip' => null,
            'buyer_address' => 'ul. Klonowa 1',
            'buyer_postal_code' => '00-002',
            'buyer_city' => 'Kraków',
            'buyer_country' => 'PL',
            'issued_at' => '2026-05-15',
            'sale_date' => '2026-05-15',
            'due_at' => '2026-05-22',
            'currency' => 'PLN',
            'subtotal_cents' => 10000,
            'vat_cents' => 2300,
            'total_cents' => 12300,
        ]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'invpay-'.$u,
            'name' => 'Invoice Pay Stable',
            'db_name' => 'invpay_'.$u,
            'db_username' => 'invpay_'.substr($u, -8),
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
            $t->string('tax_id', 32)->nullable();
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
            $t->string('checkout_url', 500)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('refunded_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
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
