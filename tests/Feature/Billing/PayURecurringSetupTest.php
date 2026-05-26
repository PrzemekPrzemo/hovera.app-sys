<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Billing\PayUService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Setup (pierwsza) płatność subskrypcji PayU — tokenizuje kartę i
 * aktywuje subskrypcję. Test pokrywa:
 *  - createRecurringSetup wysyła payload z `recurring=FIRST` + `cardOnFile=FIRST`
 *  - webhook po COMPLETED zapisuje token z `payMethods[0]` i aktywuje sub
 *  - token jest encrypted at-rest (raw DB value ≠ plaintext)
 *  - drugi webhook idempotentny — nie nadpisuje już zapisanego tokenu
 */
class PayURecurringSetupTest extends TestCase
{
    use RefreshDatabase;

    private const POS_ID = 300746;

    private const OAUTH_CLIENT_ID = '300746';

    private const OAUTH_CLIENT_SECRET = '2ee86a66e5d97e3fadc400c9f19b065d';

    private const MD5_KEY = 'b6ca15b0d1020e8094d9b5f8d163db54';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_recurring_setup_sends_first_flag_and_stores_order_on_invoice(): void
    {
        [$sub, $invoice] = $this->makeSubscriptionAndInvoice(total: 49800);

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'access_token' => 'TOKEN', 'expires_in' => 43199,
            ], 200),
            'secure.snd.payu.com/api/v2_1/orders' => Http::response([
                'orderId' => 'WZSETUP00000000000001',
                'redirectUri' => 'https://secure.snd.payu.com/setup/1',
                'status' => ['statusCode' => 'SUCCESS'],
            ], 200),
        ]);

        $url = $this->service()->createRecurringSetup($invoice, $sub);

        $this->assertSame('https://secure.snd.payu.com/setup/1', $url);

        $invoice->refresh();
        $this->assertSame('WZSETUP00000000000001', $invoice->payu_order_id);
        $this->assertSame(PayUService::EXT_ORDER_PREFIX_SETUP.$sub->id, $invoice->payu_ext_order_id);

        Http::assertSent(function ($r) use ($invoice, $sub) {
            if (! str_contains($r->url(), '/api/v2_1/orders')) {
                return false;
            }

            return $r['extOrderId'] === PayUService::EXT_ORDER_PREFIX_SETUP.$sub->id
                && $r['recurring'] === 'FIRST'
                && $r['cardOnFile'] === 'FIRST'
                && $r['totalAmount'] === (string) $invoice->total_cents;
        });
    }

    public function test_setup_rejects_invoice_not_linked_to_subscription(): void
    {
        [$sub, $invoice] = $this->makeSubscriptionAndInvoice(total: 49800);
        $invoice->subscription_id = null;
        $invoice->save();

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->createRecurringSetup($invoice, $sub);
    }

    public function test_setup_rejects_zero_total(): void
    {
        [$sub, $invoice] = $this->makeSubscriptionAndInvoice(total: 0);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->createRecurringSetup($invoice, $sub);
    }

    public function test_webhook_parses_card_token_and_activates_subscription(): void
    {
        [$sub, $invoice] = $this->makeSubscriptionAndInvoice(total: 49800);
        $extOrderId = PayUService::EXT_ORDER_PREFIX_SETUP.$sub->id;
        $invoice->forceFill([
            'payu_order_id' => 'WZSETUP00000000000001',
            'payu_ext_order_id' => $extOrderId,
        ])->save();

        $payload = ['order' => [
            'orderId' => 'WZSETUP00000000000001',
            'extOrderId' => $extOrderId,
            'totalAmount' => 49800,
            'status' => 'COMPLETED',
            'payMethod' => [[
                'type' => 'CARD_TOKEN',
                'value' => 'TOK-REC-CC-ABCDEFGH',
                'card' => [
                    'number' => '4444333322221111',
                    'brand' => 'VISA',
                    'expirationMonth' => 12,
                    'expirationYear' => 2030,
                ],
            ]],
        ]];
        $rawBody = (string) json_encode($payload);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);
        $header = "signature={$signature};algorithm=SHA-256;sender=checkout";

        $this->assertTrue($this->service()->processWebhook($payload, $header, $rawBody));

        $invoice->refresh();
        $this->assertNotNull($invoice->paid_at);

        $sub->refresh();
        $this->assertSame('TOK-REC-CC-ABCDEFGH', $sub->payu_recurring_token);
        $this->assertSame('4444333322221111', $sub->payu_card_mask);
        $this->assertSame('VISA', $sub->payu_card_brand);
        $this->assertSame('2030-12-31', $sub->payu_card_expires_at?->toDateString());
        $this->assertSame('active', $sub->status);
        $this->assertNotNull($sub->current_period_start);
        $this->assertNotNull($sub->current_period_end);
        $this->assertTrue(
            $sub->current_period_end->greaterThan($sub->current_period_start),
            'current_period_end should be after current_period_start',
        );
    }

    public function test_token_is_stored_encrypted_at_rest(): void
    {
        [$sub] = $this->makeSubscriptionAndInvoice(total: 49800);

        $sub->forceFill(['payu_recurring_token' => 'PLAIN-TOKEN-12345'])->save();

        $raw = DB::connection('central')
            ->table('subscriptions')
            ->where('id', $sub->id)
            ->value('payu_recurring_token');

        $this->assertNotSame('PLAIN-TOKEN-12345', $raw);
        $this->assertNotEmpty($raw);

        // Re-read przez Eloquent → decrypt → plaintext.
        $this->assertSame('PLAIN-TOKEN-12345', $sub->fresh()->payu_recurring_token);
    }

    public function test_second_webhook_does_not_overwrite_existing_token(): void
    {
        [$sub, $invoice] = $this->makeSubscriptionAndInvoice(total: 49800);
        $extOrderId = PayUService::EXT_ORDER_PREFIX_SETUP.$sub->id;
        $invoice->forceFill([
            'payu_order_id' => 'WZSETUP00000000000001',
            'payu_ext_order_id' => $extOrderId,
        ])->save();
        $sub->forceFill([
            'payu_recurring_token' => 'EXISTING-TOKEN',
            'payu_card_mask' => 'OLD-MASK',
            'status' => 'active',
        ])->save();

        $payload = ['order' => [
            'orderId' => 'WZSETUP00000000000001',
            'extOrderId' => $extOrderId,
            'totalAmount' => 49800,
            'status' => 'COMPLETED',
            'payMethod' => [[
                'type' => 'CARD_TOKEN',
                'value' => 'NEW-TOKEN-WOULD-BE-WRONG',
                'card' => ['number' => 'NEW-MASK', 'brand' => 'MC'],
            ]],
        ]];
        $rawBody = (string) json_encode($payload);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);

        $this->service()->processWebhook(
            $payload,
            "signature={$signature};algorithm=SHA-256;sender=checkout",
            $rawBody,
        );

        $sub->refresh();
        $this->assertSame('EXISTING-TOKEN', $sub->payu_recurring_token);
        $this->assertSame('OLD-MASK', $sub->payu_card_mask);
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

    /**
     * @return array{0: Subscription, 1: Invoice}
     */
    private function makeSubscriptionAndInvoice(int $total): array
    {
        $tenant = new Tenant([
            'slug' => 'rec-'.uniqid(),
            'name' => 'Recurring Test',
            'db_name' => 'hovera_t_'.uniqid(),
            'db_username' => 'hovera_t_'.uniqid(),
            'status' => 'active',
        ]);
        $tenant->db_password = 'x';
        $tenant->save();

        $plan = Plan::create([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Plan',
            'currency' => 'PLN',
            'price_monthly_cents' => 39800,
            'onboarding_fee_cents' => 10000,
            'is_active' => true,
            'audience' => 'stable',
        ]);

        $sub = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'billing_cycle' => 'monthly',
        ]);

        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $sub->id,
            'number' => 'HVR/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'kind' => 'regular',
            'plan_code' => $plan->code,
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

        return [$sub, $invoice];
    }
}
