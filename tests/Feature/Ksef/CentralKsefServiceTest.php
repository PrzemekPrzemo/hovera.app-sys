<?php

declare(strict_types=1);

namespace Tests\Feature\Ksef;

use App\Models\Central\Invoice;
use App\Models\Central\SystemSetting;
use App\Models\Central\Tenant;
use App\Services\Ksef\CentralKsefService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralKsefServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_not_ready_without_cert(): void
    {
        $this->assertFalse((new CentralKsefService)->isReady());
    }

    public function test_is_ready_when_cert_and_nip_configured(): void
    {
        SystemSetting::setSecret('ksef_central.cert_pfx', 'dummy-bytes');
        SystemSetting::setValue('ksef_central.context_nip', '1234567890');

        $this->assertTrue((new CentralKsefService)->isReady());
    }

    public function test_push_invoice_throws_when_not_configured(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant);

        $this->expectException(\RuntimeException::class);
        (new CentralKsefService)->pushInvoice($invoice);
    }

    public function test_push_invoice_marks_pending_and_returns_reference(): void
    {
        SystemSetting::setSecret('ksef_central.cert_pfx', 'dummy-bytes');
        SystemSetting::setValue('ksef_central.context_nip', '1234567890');

        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant);

        $ref = (new CentralKsefService)->pushInvoice($invoice);

        $this->assertStringStartsWith('HVR-LOCAL-', $ref);
        $invoice->refresh();
        $this->assertSame(CentralKsefService::STATUS_PENDING, $invoice->ksef_status);
        $this->assertSame($ref, $invoice->ksef_reference);
        $this->assertNotNull($invoice->ksef_pushed_at);
    }

    public function test_build_xml_contains_required_fa3_elements(): void
    {
        SystemSetting::setValue('ksef_central.context_nip', '5252111222');

        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant);

        $xml = (new CentralKsefService)->buildInvoiceXml($invoice);

        $this->assertStringContainsString('<KodFormularza kodSystemowy="FA (3)"', $xml);
        $this->assertStringContainsString('<NIP>5252111222</NIP>', $xml);
        $this->assertStringContainsString('<P_2>'.$invoice->number.'</P_2>', $xml);
        $this->assertStringContainsString('<KodWaluty>PLN</KodWaluty>', $xml);
    }

    private function makeTenant(): Tenant
    {
        $t = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'legal_name' => 'Acme Sp. z o.o.',
            'tax_id' => '9999999999',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $t->db_password = 'x';
        $t->save();

        return $t;
    }

    private function makeInvoice(Tenant $tenant): Invoice
    {
        // `plan_code` i `period` to NOT NULL w schemacie (migracja
        // 2026_05_10_202700) — fixture musi je dostarczyć, bo Invoice
        // model nie ma sensownych defaultów (model jest cienki, defaults
        // siedzą w BillingService).
        return Invoice::create([
            'tenant_id' => $tenant->id,
            'number' => 'HVR/2026/05/0001',
            'kind' => 'regular',
            'plan_code' => 'starter',
            'period' => 'monthly',
            'currency' => 'PLN',
            'subtotal_cents' => 20244,
            'vat_cents' => 4656,
            'total_cents' => 24900,
            'vat_rate' => 23,
            'status' => 'open',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
    }
}
