<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\Plan;
use App\Models\Central\SystemSetting;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\StripeClient;

/**
 * One-shot wizard: bierze nasz `Plan` i tworzy odpowiadającą mu strukturę
 * w Stripe — jedno `Product` + N par `Price` (monthly + yearly) na walutę.
 *
 * Source of truth POST-creation: Stripe Dashboard. My przechowujemy tylko
 * `stripe_price_*_id` żeby Checkout wiedział, którego price ID użyć. Edycja
 * nazwy / opisu produktu = ręcznie w Stripe, nie tu.
 *
 * Idempotencja: jeśli `Plan::stripe_price_monthly_id` jest już ustawione,
 * service rzuca — caller (UI) ma to złapać i nie pokazać przycisku
 * w pierwszej kolejności. Nie pozwalamy nadpisać istniejących Stripe IDs
 * bo to by zerwało subscriptions klientów.
 *
 * Enterprise (price = 0/null + features.is_custom_pricing=true) → skip,
 * bo nie ma fixed pricingu — kontrakty są custom.
 */
class StripeProductCreator
{
    private ?StripeClient $stripe = null;

    /**
     * @return array{
     *     product_id: string,
     *     base_currency: string,
     *     prices: array<string, array{monthly: string|null, yearly: string|null}>
     * }
     */
    public function createForPlan(Plan $plan): array
    {
        if ($this->isEnterprise($plan)) {
            throw new RuntimeException('plan.stripe.enterprise_skipped');
        }

        if ($plan->stripe_price_monthly_id !== null && $plan->stripe_price_monthly_id !== '') {
            throw new RuntimeException('plan.stripe.already_created');
        }

        $stripe = $this->client();

        // 1) Product — jedyny, wspólny dla wszystkich walut. Stripe pozwala
        // by jeden Product miał wiele Price'ów (każdy ze swoim currency).
        $product = $stripe->products->create([
            'name' => (string) $plan->name,
            'description' => $this->descriptionFor($plan),
            'metadata' => [
                'plan_code' => (string) $plan->code,
                'audience' => (string) ($plan->audience ?? ''),
                'managed_by' => 'hovera_plan_wizard',
            ],
        ]);

        $productId = (string) $product->id;

        // 2) Base currency — z Plan::currency (zwykle PLN). Bierzemy
        // bezpośrednio price_monthly_cents / price_yearly_cents.
        $baseCurrency = strtoupper((string) ($plan->currency ?? 'PLN'));
        $created = [];

        $created[$baseCurrency] = [
            'monthly' => $this->createPrice($productId, $baseCurrency, (int) $plan->price_monthly_cents, 'month'),
            'yearly' => $this->createPrice($productId, $baseCurrency, (int) $plan->price_yearly_cents, 'year'),
        ];

        // 3) Pozostałe waluty z prices_per_currency overlay.
        $overlay = $plan->prices_per_currency ?? [];
        $updatedOverlay = $overlay;

        foreach ($overlay as $currency => $row) {
            $currency = strtoupper((string) $currency);
            if ($currency === $baseCurrency) {
                continue;
            }
            if (! is_array($row)) {
                continue;
            }

            $monthlyCents = (int) ($row['monthly_cents'] ?? 0);
            $yearlyCents = (int) ($row['yearly_cents'] ?? 0);

            $monthlyId = $this->createPrice($productId, $currency, $monthlyCents, 'month');
            $yearlyId = $this->createPrice($productId, $currency, $yearlyCents, 'year');

            $created[$currency] = ['monthly' => $monthlyId, 'yearly' => $yearlyId];

            // Wstrzykujemy Stripe price IDs do JSON overlay'a — żeby
            // future checkout w innej walucie znalazł odpowiednik.
            $updatedOverlay[$currency] = array_merge($row, [
                'stripe_price_monthly_id' => $monthlyId,
                'stripe_price_yearly_id' => $yearlyId,
            ]);
        }

        // 4) Zapis na Plan.
        $plan->forceFill([
            'stripe_price_monthly_id' => $created[$baseCurrency]['monthly'],
            'stripe_price_yearly_id' => $created[$baseCurrency]['yearly'],
            'prices_per_currency' => $updatedOverlay !== [] ? $updatedOverlay : $plan->prices_per_currency,
        ])->save();

        Log::info('Stripe product created via wizard', [
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'stripe_product_id' => $productId,
            'currencies' => array_keys($created),
        ]);

        return [
            'product_id' => $productId,
            'base_currency' => $baseCurrency,
            'prices' => $created,
        ];
    }

    /**
     * Build a description from the marketing audience hint. Stripe pokazuje
     * to klientowi w Customer Portal i w Checkout — czytelność > kompletność.
     */
    private function descriptionFor(Plan $plan): string
    {
        $hint = data_get($plan->features ?? [], 'audience_hint');
        if (is_string($hint) && $hint !== '') {
            return $hint;
        }

        $audience = (string) ($plan->audience ?? '');

        return $audience !== '' ? "Hovera plan ({$audience})" : 'Hovera plan';
    }

    /**
     * @param  'month'|'year'  $interval
     */
    private function createPrice(string $productId, string $currency, int $unitAmount, string $interval): ?string
    {
        if ($unitAmount <= 0) {
            return null;
        }

        $price = $this->client()->prices->create([
            'product' => $productId,
            'currency' => strtolower($currency),
            'unit_amount' => $unitAmount,
            'recurring' => ['interval' => $interval],
            'metadata' => [
                'managed_by' => 'hovera_plan_wizard',
                'interval' => $interval,
            ],
        ]);

        return (string) $price->id;
    }

    private function isEnterprise(Plan $plan): bool
    {
        if (! empty($plan->features['is_custom_pricing'])) {
            return true;
        }
        if (data_get($plan->features ?? [], 'marketing_cta') === 'contact_sales') {
            return true;
        }

        return ((int) $plan->price_monthly_cents) <= 0
            && ((int) $plan->price_yearly_cents) <= 0;
    }

    private function client(): StripeClient
    {
        if ($this->stripe !== null) {
            return $this->stripe;
        }

        // Priorytet: SystemSetting (master admin UI), fallback env.
        $secret = SystemSetting::getSecret('stripe.secret_key')
            ?? (string) config('services.stripe.secret', '');

        if ($secret === '') {
            throw new RuntimeException('plan.stripe.missing_api_key');
        }

        return $this->stripe = new StripeClient($secret);
    }

    /**
     * Test hook — pozwala zainjectować mockowanego StripeClient'a bez
     * patrzenia na SystemSetting / env. Używane przez feature testy.
     */
    public function setClient(StripeClient $client): void
    {
        $this->stripe = $client;
    }
}
