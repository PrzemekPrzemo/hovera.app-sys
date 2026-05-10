<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Services\Billing\Przelewy24Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Przelewy24ServiceTest extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT = 12345;

    private const POS = 12345;

    private const CRC = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private const API_KEY = 'rest_test_xxxxxxxxxxxxxxxxxxxxxx';

    public function test_create_payment_registers_in_p24_and_returns_url(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);

        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response([
                'data' => ['token' => 'TOKEN-XYZ'],
            ], 200),
        ]);

        $svc = $this->service();
        $url = $svc->createPayment($invoice);

        $this->assertSame('https://sandbox.przelewy24.pl/trnRequest/TOKEN-XYZ', $url);
        $invoice->refresh();
        $this->assertSame($invoice->id, $invoice->p24_session_id);
        $this->assertSame($url, $invoice->p24_payment_url);

        Http::assertSent(function ($r) use ($invoice) {
            return str_contains($r->url(), '/api/v1/transaction/register')
                && $r['sessionId'] === $invoice->id
                && $r['amount'] === 24900
                && $r['currency'] === 'PLN'
                && ! empty($r['sign']);
        });
    }

    public function test_verify_webhook_accepts_valid_signature(): void
    {
        $svc = $this->service();

        $payload = [
            'merchantId' => self::MERCHANT,
            'posId' => self::POS,
            'sessionId' => 'sess-1',
            'amount' => 24900,
            'originAmount' => 24900,
            'currency' => 'PLN',
            'orderId' => 999111,
            'methodId' => 154,
            'statement' => 'P24',
        ];
        $payload['sign'] = hash('sha384', json_encode([
            'merchantId' => self::MERCHANT,
            'posId' => self::POS,
            'sessionId' => 'sess-1',
            'amount' => 24900,
            'originAmount' => 24900,
            'currency' => 'PLN',
            'orderId' => 999111,
            'methodId' => 154,
            'statement' => 'P24',
            'crc' => self::CRC,
        ], JSON_UNESCAPED_SLASHES));

        $this->assertTrue($svc->verifyWebhook($payload));
    }

    public function test_verify_webhook_rejects_tampered_signature(): void
    {
        $svc = $this->service();

        $payload = [
            'merchantId' => self::MERCHANT,
            'posId' => self::POS,
            'sessionId' => 'sess-1',
            'amount' => 24900,
            'sign' => str_repeat('0', 96),
        ];

        $this->assertFalse($svc->verifyWebhook($payload));
    }

    public function test_process_webhook_marks_invoice_paid(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);
        $invoice->p24_session_id = $invoice->id;
        $invoice->save();

        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/verify' => Http::response([
                'data' => ['status' => 'success'],
            ], 200),
        ]);

        $payload = $this->validWebhookPayload($invoice->id, 24900, 555);

        $ok = $this->service()->processWebhook($payload);

        $this->assertTrue($ok);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertNotNull($invoice->p24_paid_at);
        $this->assertSame('555', $invoice->p24_order_id);
    }

    public function test_process_webhook_rejects_wrong_amount(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);
        $invoice->p24_session_id = $invoice->id;
        $invoice->save();

        $payload = $this->validWebhookPayload($invoice->id, 100, 555); // amount mismatch

        $this->assertFalse($this->service()->processWebhook($payload));
        $invoice->refresh();
        $this->assertNull($invoice->paid_at);
    }

    private function service(): Przelewy24Service
    {
        return new Przelewy24Service(
            merchantId: self::MERCHANT,
            posId: self::POS,
            apiKey: self::API_KEY,
            crc: self::CRC,
            env: 'sandbox',
        );
    }

    private function validWebhookPayload(string $sessionId, int $amount, int $orderId): array
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

    private function makeTenant(): Tenant
    {
        $t = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $t->db_password = 'x';
        $t->save();

        return $t;
    }

    private function makeInvoice(Tenant $tenant, int $total): Invoice
    {
        return Invoice::create([
            'tenant_id' => $tenant->id,
            'number' => 'HVR/2026/05/0001',
            'kind' => 'regular',
            'currency' => 'PLN',
            'subtotal_cents' => (int) round($total / 1.23),
            'vat_cents' => $total - (int) round($total / 1.23),
            'total_cents' => $total,
            'vat_rate' => 23,
            'status' => 'open',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
    }
}
