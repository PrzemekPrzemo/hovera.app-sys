<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Central\AddonPurchase;
use App\Models\Central\PlanAddon;
use App\Models\Central\Tenant;
use App\Services\Billing\Przelewy24Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * AddonPurchase P24 flow — Hovera as merchant of record.
 *
 * Pokrycie:
 *   - chargeAddon() — happy path, sign, URL storage, status pending
 *   - chargeAddon() — rejects non-PLN, zero amount, terminal status
 *   - processAddonWebhook() — full flow, idempotency, amount mismatch
 *   - webhook endpoint (POST /webhooks/przelewy24/addon)
 */
class AddonPurchaseP24Test extends TestCase
{
    use RefreshDatabase;

    private const MERCHANT = 12345;

    private const POS = 12345;

    private const CRC = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private const API_KEY = 'rest_test_xxxxxxxxxxxxxxxxxxxxxx';

    public function test_charge_addon_registers_session_and_stores_url(): void
    {
        $purchase = $this->makePurchase(amountCents: 49900); // 499 PLN

        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/register' => Http::response([
                'data' => ['token' => 'ADDON-TOK-1'],
            ], 200),
        ]);

        $url = $this->service()->chargeAddon($purchase);

        $this->assertSame('https://sandbox.przelewy24.pl/trnRequest/ADDON-TOK-1', $url);
        $purchase->refresh();
        $this->assertSame($purchase->id, $purchase->p24_session_id);
        $this->assertSame($url, $purchase->p24_payment_url);
        $this->assertSame(AddonPurchase::STATUS_PENDING, $purchase->status);

        Http::assertSent(function ($r) use ($purchase) {
            $body = $r->data();
            $expectedSign = hash('sha384', json_encode([
                'sessionId' => $purchase->id,
                'merchantId' => self::MERCHANT,
                'amount' => 49900,
                'currency' => 'PLN',
                'crc' => self::CRC,
            ], JSON_UNESCAPED_SLASHES));

            return $body['sessionId'] === $purchase->id
                && $body['amount'] === 49900
                && $body['currency'] === 'PLN'
                && $body['sign'] === $expectedSign;
        });
    }

    public function test_charge_addon_rejects_non_pln(): void
    {
        $purchase = $this->makePurchase(amountCents: 10000, currency: 'EUR');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->chargeAddon($purchase);
    }

    public function test_charge_addon_rejects_zero_amount(): void
    {
        $purchase = $this->makePurchase(amountCents: 0);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->chargeAddon($purchase);
    }

    public function test_charge_addon_rejects_terminal_purchase(): void
    {
        $purchase = $this->makePurchase(amountCents: 10000);
        $purchase->forceFill(['status' => AddonPurchase::STATUS_PAID])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/terminalnym/');
        $this->service()->chargeAddon($purchase);
    }

    public function test_process_addon_webhook_marks_paid(): void
    {
        $purchase = $this->makePurchase(amountCents: 49900);
        $purchase->forceFill(['p24_session_id' => $purchase->id])->save();

        Http::fake([
            'sandbox.przelewy24.pl/api/v1/transaction/verify' => Http::response([
                'data' => ['status' => 'success'],
            ], 200),
        ]);

        $payload = $this->validWebhookPayload($purchase->id, 49900, 12345);

        $ok = $this->service()->processAddonWebhook($payload);

        $this->assertTrue($ok);
        $purchase->refresh();
        $this->assertSame(AddonPurchase::STATUS_PAID, $purchase->status);
        $this->assertNotNull($purchase->paid_at);
        $this->assertNotNull($purchase->p24_paid_at);
        $this->assertSame('12345', $purchase->p24_order_id);
    }

    public function test_process_addon_webhook_is_idempotent(): void
    {
        $purchase = $this->makePurchase(amountCents: 49900);
        $purchase->forceFill([
            'p24_session_id' => $purchase->id,
            'status' => AddonPurchase::STATUS_PAID,
            'paid_at' => now(),
        ])->save();

        // Brak Http::fake — gdyby kod wywołał verify by failed
        $payload = $this->validWebhookPayload($purchase->id, 49900, 12345);

        $this->assertTrue($this->service()->processAddonWebhook($payload));
    }

    public function test_process_addon_webhook_rejects_amount_mismatch(): void
    {
        $purchase = $this->makePurchase(amountCents: 49900);
        $purchase->forceFill(['p24_session_id' => $purchase->id])->save();

        $payload = $this->validWebhookPayload($purchase->id, 100, 12345); // wrong

        $this->assertFalse($this->service()->processAddonWebhook($payload));
        $this->assertNotSame(AddonPurchase::STATUS_PAID, $purchase->fresh()->status);
    }

    public function test_process_addon_webhook_rejects_invalid_sign(): void
    {
        $purchase = $this->makePurchase(amountCents: 49900);
        $purchase->forceFill(['p24_session_id' => $purchase->id])->save();

        $this->assertFalse($this->service()->processAddonWebhook([
            'sessionId' => $purchase->id,
            'sign' => str_repeat('0', 96),
        ]));
    }

    public function test_webhook_endpoint_returns_400_for_missing_payload(): void
    {
        $response = $this->post('/webhooks/przelewy24/addon', []);
        $response->assertStatus(400);
    }

    public function test_webhook_endpoint_returns_200_for_valid_webhook(): void
    {
        $purchase = $this->makePurchase(amountCents: 49900);
        $purchase->forceFill(['p24_session_id' => $purchase->id])->save();

        Http::fake([
            '*/transaction/verify' => Http::response(['data' => ['status' => 'success']], 200),
        ]);

        $payload = $this->validWebhookPayload($purchase->id, 49900, 999);

        $response = $this->post('/webhooks/przelewy24/addon', $payload);

        $response->assertOk();
        $response->assertJson(['received' => true]);
        $this->assertSame(AddonPurchase::STATUS_PAID, $purchase->fresh()->status);
    }

    public function test_webhook_endpoint_returns_400_for_invalid_sign(): void
    {
        $purchase = $this->makePurchase(amountCents: 49900);
        $purchase->forceFill(['p24_session_id' => $purchase->id])->save();

        $response = $this->post('/webhooks/przelewy24/addon', [
            'sessionId' => $purchase->id,
            'sign' => str_repeat('z', 96),
            'amount' => 49900,
        ]);

        $response->assertStatus(400);
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

    protected function setUp(): void
    {
        parent::setUp();
        // Override singleton tak żeby webhook endpoint używał naszej config
        // (env może mieć inne creds).
        $this->app->singleton(Przelewy24Service::class, fn () => $this->service());
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

    private function makePurchase(int $amountCents, string $currency = 'PLN'): AddonPurchase
    {
        $tenant = $this->makeTenant();
        $addon = PlanAddon::create([
            'code' => 'migrate_excel',
            'name' => 'Migracja Excel',
            'addon_type' => PlanAddon::TYPE_ONE_TIME,
            'is_global' => true,
            'plan_id' => null,
            'currency' => 'PLN',
            'price_monthly_cents' => 49900,
            'price_yearly_cents' => 0,
            'is_active' => true,
        ]);

        return AddonPurchase::create([
            'tenant_id' => $tenant->id,
            'plan_addon_id' => $addon->id,
            'addon_code' => $addon->code,
            'addon_name' => $addon->name,
            'currency' => $currency,
            'amount_cents' => $amountCents,
            'status' => AddonPurchase::STATUS_PENDING,
        ]);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'addon-'.$u,
            'name' => 'Acme T',
            'db_name' => 'addon_'.$u,
            'db_username' => 'addon_'.substr($u, -8),
            'status' => 'active',
        ]);
        $t->db_password = 'x';
        $t->save();

        return $t;
    }
}
