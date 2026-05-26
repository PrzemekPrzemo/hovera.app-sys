<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Jobs\Billing\ChargeRecurringPayUSubscriptionsJob;
use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Notifications\Billing\PayuChargeFailedNotification;
use App\Notifications\Billing\PayuSubscriptionSuspendedNotification;
use App\Services\Billing\PayUService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cykliczny charge engine + dunning policy. Pokrycie:
 *  - chargeRecurring wysyła payload z STANDARD + CARD_TOKEN
 *  - job tworzy nowe invoice + woła chargeRecurring dla due subskrypcji
 *  - job skipuje subskrypcje bez tokenu i te z istniejącą FV na okres
 *  - webhook COMPLETED na recur_ → markChargeSucceeded advance period
 *  - webhook REJECTED na recur_ → dunning (failed_attempts++)
 *  - 1. fail → past_due + email + retry +3d
 *  - 3. fail → cancelled + email suspended
 *  - charge succeeded reset failed_attempts
 */
class PayURecurringChargeTest extends TestCase
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

    public function test_charge_recurring_sends_card_token_and_standard_flag(): void
    {
        [$sub, $invoice] = $this->makeActiveSubscriptionWithToken();

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'access_token' => 'TOKEN', 'expires_in' => 43199,
            ], 200),
            'secure.snd.payu.com/api/v2_1/orders' => Http::response([
                'orderId' => 'WZRECURRING000001',
                'redirectUri' => 'https://secure.snd.payu.com/recur/1',
                'status' => ['statusCode' => 'SUCCESS'],
            ], 200),
        ]);

        $this->assertTrue($this->service()->chargeRecurring($invoice, $sub));

        Http::assertSent(function ($r) use ($sub, $invoice) {
            if (! str_contains($r->url(), '/api/v2_1/orders')) {
                return false;
            }

            return $r['recurring'] === 'STANDARD'
                && $r['payMethods']['payMethod']['type'] === 'CARD_TOKEN'
                && $r['payMethods']['payMethod']['value'] === $sub->payu_recurring_token
                && str_starts_with($r['extOrderId'], 'recur_'.$sub->id.'_')
                && $r['totalAmount'] === (string) $invoice->total_cents;
        });

        $invoice->refresh();
        $this->assertSame('WZRECURRING000001', $invoice->payu_order_id);
    }

    public function test_charge_recurring_rejects_subscription_without_token(): void
    {
        [$sub, $invoice] = $this->makeActiveSubscriptionWithToken();
        $sub->forceFill(['payu_recurring_token' => null])->save();
        $sub->refresh();

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->chargeRecurring($invoice, $sub);
    }

    public function test_job_skips_subscriptions_without_token(): void
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan();
        $sub = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_end' => now()->subDay(),
            // brak payu_recurring_token
        ]);

        Http::fake();

        (new ChargeRecurringPayUSubscriptionsJob)->handle($this->service());

        $this->assertSame(0, Invoice::query()->where('subscription_id', $sub->id)->count());
        Http::assertNothingSent();
    }

    public function test_job_skips_subscriptions_already_charged_for_current_period(): void
    {
        $sub = $this->makeActiveSubscriptionOnly(periodEnd: now()->subDay());
        $period = now()->format('Y-m');

        // Already-existing recurring invoice na ten okres.
        Invoice::create($this->invoiceData($sub, total: 39800, extOrderId: 'recur_'.$sub->id.'_'.$period));

        Http::fake();

        (new ChargeRecurringPayUSubscriptionsJob)->handle($this->service());

        // Nie tworzymy drugiej FV i nie odpalamy charge.
        $this->assertSame(1, Invoice::query()->where('subscription_id', $sub->id)->count());
        Http::assertNothingSent();
    }

    public function test_job_creates_invoice_and_charges_due_subscriptions(): void
    {
        $sub = $this->makeActiveSubscriptionOnly(periodEnd: now()->subDay());

        Http::fake([
            'secure.snd.payu.com/pl/standard/user/oauth/authorize' => Http::response([
                'access_token' => 'TOKEN', 'expires_in' => 43199,
            ], 200),
            'secure.snd.payu.com/api/v2_1/orders' => Http::response([
                'orderId' => 'WZRECUR0001',
                'redirectUri' => 'https://x.test/recur',
                'status' => ['statusCode' => 'SUCCESS'],
            ], 200),
        ]);

        (new ChargeRecurringPayUSubscriptionsJob)->handle($this->service());

        $invoice = Invoice::query()->where('subscription_id', $sub->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(39800, $invoice->total_cents);  // plan.price_monthly, BEZ onboarding_fee
        $this->assertSame('open', $invoice->status);
        $this->assertSame('WZRECUR0001', $invoice->payu_order_id);
    }

    public function test_webhook_completed_advances_period_and_marks_paid(): void
    {
        [$sub, $invoice] = $this->makeActiveSubscriptionWithToken(periodEnd: now());
        $period = now()->format('Y-m');
        $extOrderId = 'recur_'.$sub->id.'_'.$period;
        $invoice->forceFill([
            'payu_order_id' => 'WZRECUR0001',
            'payu_ext_order_id' => $extOrderId,
        ])->save();

        $originalEnd = $sub->current_period_end;

        $payload = ['order' => [
            'orderId' => 'WZRECUR0001',
            'extOrderId' => $extOrderId,
            'totalAmount' => $invoice->total_cents,
            'status' => 'COMPLETED',
        ]];
        $rawBody = (string) json_encode($payload);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);

        $this->assertTrue($this->service()->processWebhook(
            $payload,
            "signature={$signature};algorithm=SHA-256;sender=checkout",
            $rawBody,
        ));

        $invoice->refresh();
        $sub->refresh();
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('active', $sub->status);
        $this->assertSame(0, (int) $sub->payu_failed_attempts);
        $this->assertTrue(
            $sub->current_period_end->greaterThan($originalEnd),
            'current_period_end should advance after successful recurring charge',
        );
    }

    public function test_first_failure_marks_past_due_and_sends_email(): void
    {
        Notification::fake();
        [$sub, $invoice] = $this->makeActiveSubscriptionWithToken();

        $this->service()->markChargeFailed($sub, $invoice, 'Test reason');

        $sub->refresh();
        $this->assertSame(1, (int) $sub->payu_failed_attempts);
        $this->assertSame('past_due', $sub->status);
        $this->assertNotNull($sub->payu_last_failed_at);
        $this->assertSame('failed', $sub->payu_last_charge_status);

        Notification::assertSentOnDemand(PayuChargeFailedNotification::class);
    }

    public function test_third_failure_suspends_subscription_and_sends_suspended_email(): void
    {
        Notification::fake();
        [$sub, $invoice] = $this->makeActiveSubscriptionWithToken();
        $sub->forceFill(['payu_failed_attempts' => 2])->save();

        $this->service()->markChargeFailed($sub, $invoice, 'Test reason');

        $sub->refresh();
        $this->assertSame(3, (int) $sub->payu_failed_attempts);
        $this->assertSame('cancelled', $sub->status);
        $this->assertNotNull($sub->cancelled_at);

        Notification::assertSentOnDemand(PayuSubscriptionSuspendedNotification::class);
        Notification::assertSentOnDemandTimes(PayuChargeFailedNotification::class, 0);
    }

    public function test_webhook_rejected_on_recur_routes_to_dunning(): void
    {
        Notification::fake();
        [$sub, $invoice] = $this->makeActiveSubscriptionWithToken();
        $extOrderId = 'recur_'.$sub->id.'_'.now()->format('Y-m');
        $invoice->forceFill([
            'payu_order_id' => 'WZFAIL0001',
            'payu_ext_order_id' => $extOrderId,
        ])->save();

        $payload = ['order' => [
            'orderId' => 'WZFAIL0001',
            'extOrderId' => $extOrderId,
            'totalAmount' => $invoice->total_cents,
            'status' => 'REJECTED',
        ]];
        $rawBody = (string) json_encode($payload);
        $signature = hash('sha256', $rawBody.self::MD5_KEY);

        $this->assertTrue($this->service()->processWebhook(
            $payload,
            "signature={$signature};algorithm=SHA-256;sender=checkout",
            $rawBody,
        ));

        $sub->refresh();
        $this->assertSame(1, (int) $sub->payu_failed_attempts);
        $this->assertSame('past_due', $sub->status);
        Notification::assertSentOnDemand(PayuChargeFailedNotification::class);
    }

    public function test_charge_succeeded_resets_failed_counter(): void
    {
        [$sub, $invoice] = $this->makeActiveSubscriptionWithToken();
        $sub->forceFill(['payu_failed_attempts' => 2, 'status' => 'past_due'])->save();

        $this->service()->markChargeSucceeded($sub, $invoice);

        $sub->refresh();
        $this->assertSame(0, (int) $sub->payu_failed_attempts);
        $this->assertSame('active', $sub->status);
        $this->assertSame('success', $sub->payu_last_charge_status);
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
            'slug' => 'rec-'.uniqid(),
            'name' => 'Recurring Test',
            'db_name' => 'hovera_t_'.uniqid(),
            'db_username' => 'hovera_t_'.uniqid(),
            'status' => 'active',
        ]);
        $t->db_password = 'x';
        $t->save();

        return $t;
    }

    private function makePlan(): Plan
    {
        return Plan::create([
            'code' => 'TEST-'.uniqid(),
            'name' => 'Test Plan',
            'currency' => 'PLN',
            'price_monthly_cents' => 39800,
            'onboarding_fee_cents' => 10000,
            'is_active' => true,
            'audience' => 'stable',
        ]);
    }

    /**
     * @return array{0: Subscription, 1: Invoice}
     */
    private function makeActiveSubscriptionWithToken(?Carbon $periodEnd = null): array
    {
        $sub = $this->makeActiveSubscriptionOnly($periodEnd);
        $invoice = Invoice::create($this->invoiceData($sub, total: 39800));

        return [$sub, $invoice];
    }

    private function makeActiveSubscriptionOnly(?Carbon $periodEnd = null): Subscription
    {
        $tenant = $this->makeTenant();
        $plan = $this->makePlan();
        $sub = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => $periodEnd ?? now()->addMonth(),
            'payu_recurring_token' => 'TOK-EXISTING-12345',
            'payu_card_mask' => '**** 1234',
            'payu_card_brand' => 'VISA',
        ]);

        return $sub->fresh();
    }

    /**
     * @return array<string,mixed>
     */
    private function invoiceData(Subscription $sub, int $total, ?string $extOrderId = null): array
    {
        return [
            'tenant_id' => $sub->tenant_id,
            'subscription_id' => $sub->id,
            'number' => 'HVR/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'kind' => 'regular',
            'plan_code' => $sub->plan?->code ?? 'TEST',
            'period' => $sub->billing_cycle,
            'currency' => 'PLN',
            'amount_cents' => (int) round($total / 1.23),
            'vat_cents' => $total - (int) round($total / 1.23),
            'total_cents' => $total,
            'vat_rate' => 23,
            'status' => 'open',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
            'payu_ext_order_id' => $extOrderId,
        ];
    }
}
