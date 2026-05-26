<?php

declare(strict_types=1);

namespace App\Jobs\Billing;

use App\Models\Central\Invoice;
use App\Models\Central\Subscription;
use App\Services\Billing\PayUService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily job — pobiera cykliczne opłaty z aktywnych PayU subskrypcji
 * których `current_period_end` już minął.
 *
 * Per subskrypcję:
 *   1. Skip jeśli brak `payu_recurring_token` (nie PayU subskrypcja).
 *   2. Skip jeśli już istnieje recurring invoice na ten okres (idempotent
 *      across job retries — `payu_ext_order_id` zawiera period suffix).
 *   3. Tworzy nową `Invoice` (amount = plan.price_{monthly|yearly}_cents,
 *      BEZ onboarding_fee — to tylko pierwsza FV).
 *   4. PayUService::chargeRecurring() → server-to-server charge tokenem.
 *   5. Wynik leci przez webhook (markChargeSucceeded / markChargeFailed).
 *
 * Soft-fail per subskrypcja — exception na jednej nie blokuje pozostałych.
 *
 * Schedule: codziennie 02:00 Europe/Warsaw (po `transport:expire-featured`,
 * przed czasem pracy klientów). Patrz routes/console.php.
 */
class ChargeRecurringPayUSubscriptionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(PayUService $payu): void
    {
        $now = now();
        $due = Subscription::query()
            ->whereNotNull('payu_recurring_token')
            ->where('current_period_end', '<=', $now)
            ->whereIn('status', ['active', 'past_due'])
            ->with('plan', 'tenant')
            ->get();

        $charged = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($due as $sub) {
            try {
                if ($this->alreadyChargedForCurrentPeriod($sub)) {
                    $skipped++;

                    continue;
                }

                $invoice = $this->createRecurringInvoice($sub);
                $payu->chargeRecurring($invoice, $sub);
                $charged++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('PayU recurring charge failed (job)', [
                    'subscription_id' => $sub->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('PayU recurring charge job complete', [
            'charged' => $charged,
            'skipped' => $skipped,
            'failed' => $failed,
            'total_due' => $due->count(),
        ]);
    }

    /**
     * Idempotency guard — jeśli na ten okres (YYYY-MM) już mamy invoice z
     * `payu_ext_order_id` = `recur_{sub}_{period}`, nie tworzymy duplikatu.
     */
    private function alreadyChargedForCurrentPeriod(Subscription $sub): bool
    {
        $period = now()->format('Y-m');
        $expected = PayUService::EXT_ORDER_PREFIX_RECURRING.$sub->id.'_'.$period;

        return Invoice::query()
            ->where('subscription_id', $sub->id)
            ->where('payu_ext_order_id', $expected)
            ->exists();
    }

    private function createRecurringInvoice(Subscription $sub): Invoice
    {
        $plan = $sub->plan;
        if ($plan === null) {
            throw new \RuntimeException("Subscription {$sub->id} has no plan.");
        }

        $totalCents = $sub->billing_cycle === 'yearly'
            ? (int) ($plan->price_yearly_cents ?? 0)
            : (int) ($plan->price_monthly_cents ?? 0);

        if ($totalCents <= 0) {
            throw new \RuntimeException("Plan {$plan->code} has no price for cycle {$sub->billing_cycle}.");
        }

        // VAT 23% PL — net = total / 1.23 (per StripeBillingService convention).
        $vatRate = 23;
        $netCents = (int) round($totalCents * 100 / (100 + $vatRate));
        $vatCents = $totalCents - $netCents;

        return Invoice::create([
            'tenant_id' => $sub->tenant_id,
            'subscription_id' => $sub->id,
            'number' => $this->nextInvoiceNumber(),
            'kind' => 'regular',
            'plan_code' => $plan->code,
            'period' => $sub->billing_cycle,
            'currency' => (string) ($plan->currency ?? 'PLN'),
            'amount_cents' => $netCents,
            'vat_cents' => $vatCents,
            'total_cents' => $totalCents,
            'vat_rate' => $vatRate,
            'status' => 'open',
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
    }

    /**
     * HVR/{YYYY}/{MM}/{NNNN} — convention z StripeBillingService.
     * Race-safe na poziomie uniqueness index na `number`.
     */
    private function nextInvoiceNumber(): string
    {
        $prefix = sprintf('HVR/%s/%s/', now()->format('Y'), now()->format('m'));
        $last = Invoice::where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $next = 1;
        if (is_string($last) && $last !== '') {
            $parts = explode('/', $last);
            $tail = (int) end($parts);
            $next = max($next, $tail + 1);
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
