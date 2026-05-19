<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Central\Invoice;
use App\Models\Central\Tenant;
use App\Services\Billing\PayUService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests dla PayUService — Hovera-as-merchant flow dla SaaS billing.
 * Patrz docs/TRANSPORT.md §16.
 *
 * Pełne testy integracji z PayU sandbox wymagałyby prawdziwych creds —
 * mock'ujemy HTTP layer i sprawdzamy że request shape + signature
 * verification są poprawne.
 */
class PayUServiceTest extends TestCase
{
    use RefreshDatabase;

    private const POS_ID = 300746;

    private const OAUTH_CLIENT_ID = '300746';

    private const OAUTH_CLIENT_SECRET = '2ee86a66e5d97e3fadc400c9f19b065d';

    private const MD5_KEY = 'b6ca15b0d1020e8094d9b5f8d163db54';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();  // OAuth token cache between testami
    }

    public function test_constructor_rejects_empty_pos_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PayUService(0, self::OAUTH_CLIENT_ID, self::OAUTH_CLIENT_SECRET, self::MD5_KEY, '', 'sandbox');
    }

    public function test_constructor_rejects_empty_oauth_creds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PayUService(self::POS_ID, '', self::OAUTH_CLIENT_SECRET, self::MD5_KEY, '', 'sandbox');
    }

    public function test_constructor_rejects_invalid_env(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PayUService(self::POS_ID, self::OAUTH_CLIENT_ID, self::OAUTH_CLIENT_SECRET, self::MD5_KEY, '', 'bad');
    }

    public function test_create_payment_exchanges_oauth_and_creates_order(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'access_token' => 'TEST-TOKEN-ABCDEF',
                'token_type' => 'bearer',
                'expires_in' => 43199,
            ], 200),
            'secure.snd.payu.com/api/v2_1/orders' => Http::response([
                'orderId' => 'WZHF5FFDRJ140731GUEST000P01',
                'redirectUri' => 'https://secure.snd.payu.com/np/summary/example',
                'status' => ['statusCode' => 'SUCCESS'],
            ], 200),
        ]);

        $url = $this->service()->createPayment($invoice);

        $this->assertSame('https://secure.snd.payu.com/np/summary/example', $url);
        $invoice->refresh();
        $this->assertSame('WZHF5FFDRJ140731GUEST000P01', $invoice->payu_order_id);
        $this->assertSame($invoice->id, $invoice->payu_ext_order_id);
        $this->assertSame($url, $invoice->payu_payment_url);

        // Sprawdza że POST do /api/v2_1/orders ma poprawny shape + Bearer auth.
        Http::assertSent(function ($r) use ($invoice) {
            if (! str_contains($r->url(), '/api/v2_1/orders')) {
                return false;
            }
            $this->assertSame('Bearer TEST-TOKEN-ABCDEF', $r->header('Authorization')[0] ?? null);

            return $r['extOrderId'] === $invoice->id
                && $r['totalAmount'] === '24900'
                && $r['currencyCode'] === 'PLN'
                && $r['merchantPosId'] === (string) self::POS_ID;
        });
    }

    public function test_create_payment_rejects_zero_amount(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 0);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->createPayment($invoice);
    }

    public function test_create_payment_throws_when_oauth_fails(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'error_description' => 'Invalid client credentials',
            ], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service()->createPayment($invoice);
    }

    public function test_create_payment_throws_when_create_order_fails(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'access_token' => 'TOKEN', 'expires_in' => 43199,
            ], 200),
            'secure.snd.payu.com/api/v2_1/orders' => Http::response([
                'status' => ['statusCode' => 'BUSINESS_ERROR', 'statusDesc' => 'POS_INACTIVE'],
            ], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service()->createPayment($invoice);
    }

    public function test_verify_webhook_signature_accepts_valid(): void
    {
        $svc = $this->service();
        $rawBody = '{"order":{"orderId":"WZHF5FFDRJ140731GUEST000P01","status":"COMPLETED"}}';
        $expected = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$expected};algorithm=SHA-256;sender=checkout";

        $this->assertTrue($svc->verifyWebhookSignature($rawBody, $header));
    }

    public function test_verify_webhook_signature_rejects_tampered(): void
    {
        $svc = $this->service();
        $rawBody = '{"order":{"orderId":"X","status":"COMPLETED"}}';
        $header = 'signature='.str_repeat('0', 64).';algorithm=SHA-256;sender=checkout';

        $this->assertFalse($svc->verifyWebhookSignature($rawBody, $header));
    }

    public function test_verify_webhook_signature_rejects_missing_header(): void
    {
        $this->assertFalse($this->service()->verifyWebhookSignature('{}', ''));
    }

    public function test_process_webhook_marks_invoice_paid_when_completed(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);
        $invoice->forceFill(['payu_order_id' => 'WZHF5FFDRJ140731'])->save();

        $rawBody = json_encode(['order' => [
            'orderId' => 'WZHF5FFDRJ140731',
            'extOrderId' => $invoice->id,
            'totalAmount' => 24900,
            'status' => 'COMPLETED',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256;sender=checkout";

        $result = $this->service()->processWebhook(json_decode($rawBody, true), $header, $rawBody);

        $this->assertTrue($result);
        $invoice->refresh();
        $this->assertNotNull($invoice->paid_at);
        $this->assertNotNull($invoice->payu_paid_at);
    }

    public function test_process_webhook_ignores_non_completed_status(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);
        $invoice->forceFill(['payu_order_id' => 'WZHF5FFDRJ140731'])->save();

        $rawBody = json_encode(['order' => [
            'orderId' => 'WZHF5FFDRJ140731',
            'extOrderId' => $invoice->id,
            'totalAmount' => 24900,
            'status' => 'PENDING',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256;sender=checkout";

        $result = $this->service()->processWebhook(json_decode($rawBody, true), $header, $rawBody);

        $this->assertTrue($result);  // ack ale brak flipu
        $invoice->refresh();
        $this->assertNull($invoice->paid_at);
    }

    public function test_process_webhook_is_idempotent_for_already_paid_invoice(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);
        $invoice->forceFill([
            'payu_order_id' => 'WZHF5FFDRJ140731',
            'paid_at' => now()->subHour(),
            'payu_paid_at' => now()->subHour(),
        ])->save();

        $originalPaidAt = $invoice->paid_at->copy();

        $rawBody = json_encode(['order' => [
            'orderId' => 'WZHF5FFDRJ140731',
            'extOrderId' => $invoice->id,
            'totalAmount' => 24900,
            'status' => 'COMPLETED',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256;sender=checkout";

        $result = $this->service()->processWebhook(json_decode($rawBody, true), $header, $rawBody);

        $this->assertTrue($result);
        $invoice->refresh();
        $this->assertTrue($invoice->paid_at->equalTo($originalPaidAt));
    }

    public function test_process_webhook_rejects_amount_mismatch(): void
    {
        $tenant = $this->makeTenant();
        $invoice = $this->makeInvoice($tenant, total: 24900);
        $invoice->forceFill(['payu_order_id' => 'WZHF5FFDRJ140731'])->save();

        $rawBody = json_encode(['order' => [
            'orderId' => 'WZHF5FFDRJ140731',
            'extOrderId' => $invoice->id,
            'totalAmount' => 99900,  // mismatch
            'status' => 'COMPLETED',
        ]]);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256;sender=checkout";

        $result = $this->service()->processWebhook(json_decode($rawBody, true), $header, $rawBody);

        $this->assertFalse($result);
        $invoice->refresh();
        $this->assertNull($invoice->paid_at);
    }

    public function test_access_token_is_cached_between_calls(): void
    {
        $tenant = $this->makeTenant();
        $invoice1 = $this->makeInvoice($tenant, total: 10000);
        $invoice2 = $this->makeInvoice($tenant, total: 20000);

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'access_token' => 'CACHED-TOKEN', 'expires_in' => 43199,
            ], 200),
            'secure.snd.payu.com/api/v2_1/orders' => Http::sequence()
                ->push(['orderId' => 'ORDER-1', 'redirectUri' => 'https://x.test/1', 'status' => ['statusCode' => 'SUCCESS']], 200)
                ->push(['orderId' => 'ORDER-2', 'redirectUri' => 'https://x.test/2', 'status' => ['statusCode' => 'SUCCESS']], 200),
        ]);

        $svc = $this->service();
        $svc->createPayment($invoice1);
        $svc->createPayment($invoice2);

        // OAuth wywołane tylko raz (cache hit dla drugiego wywołania).
        Http::assertSentCount(3);  // 1 OAuth + 2 orders
    }

    private function service(): PayUService
    {
        return new PayUService(
            posId: self::POS_ID,
            oauthClientId: self::OAUTH_CLIENT_ID,
            oauthClientSecret: self::OAUTH_CLIENT_SECRET,
            md5Key: self::MD5_KEY,
            secondKey: '',
            env: 'sandbox',
        );
    }

    private function makeTenant(): Tenant
    {
        $t = new Tenant([
            'slug' => 'acme-'.uniqid(),
            'name' => 'Acme',
            'db_name' => 'hovera_t_'.uniqid(),
            'db_username' => 'hovera_t_'.uniqid(),
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
            'number' => 'HVR/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'kind' => 'regular',
            'plan_code' => 'TRIAL',
            'period' => 'one_time',
            'currency' => 'PLN',
            'amount_cents' => $total > 0 ? (int) round($total / 1.23) : 0,
            'vat_cents' => $total > 0 ? $total - (int) round($total / 1.23) : 0,
            'total_cents' => $total,
            'vat_rate' => 23,
            'status' => 'open',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
    }
}
