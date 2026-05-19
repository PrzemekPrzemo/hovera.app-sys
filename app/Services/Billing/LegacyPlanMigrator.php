<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Central\Plan;
use App\Models\Central\SystemSetting;
use App\Models\Central\Tenant;
use App\Notifications\TenantPlanMigratedNotification;
use App\Services\MasterAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RuntimeException;
use Stripe\StripeClient;

/**
 * Master-admin operacja: przepięcie tenant'a z planu legacy
 * (transport_solo_legacy / transport_pro_legacy / transport_fleet_legacy)
 * na nowy plan ze spec'a marketing'owego z 2026-05-18.
 *
 * Rekomendowane mapowanie (cena IDZIE W GÓRĘ — wymaga zgody klienta;
 * docs/TRANSPORT.md §15 lock-in 12mc gwarancji ceny):
 *
 *   transport_solo_legacy  → transport_start     (149 → 250 PLN)
 *   transport_pro_legacy   → transport_pro       (349 → 549 PLN)
 *   transport_fleet_legacy → transport_business  (699 → 999 PLN)
 *
 * Operacja jest **non-rollback w danych** (po przepięciu wracamy ręcznie).
 * Audit log + email do owner'a to bezpiecznik formalny.
 *
 * Stripe: gdy tenant ma `stripe_subscription_id`, możemy opcjonalnie
 * przepiąć subskrypcję na nowy price ID (proration_behavior='none' →
 * nowa cena dopiero od kolejnego billing cycle, więc klient nie dostanie
 * niespodziewanej faktury "wyrównawczej"). Bez Stripe sub → tylko swap
 * `plan_id` + email.
 */
class LegacyPlanMigrator
{
    private ?StripeClient $stripe = null;

    /**
     * Legacy code → recommended new code. Hardcoded — to nie jest config,
     * tylko biznesowa decyzja z PR #229 / docs/TRANSPORT.md §2 D2.
     *
     * @var array<string, string>
     */
    public const MAPPING = [
        'transport_solo_legacy' => 'transport_start',
        'transport_pro_legacy' => 'transport_pro',
        'transport_fleet_legacy' => 'transport_business',
    ];

    public function __construct(
        private readonly MasterAuditLogger $audit,
    ) {}

    /**
     * Wszystkie kody legacy obecne w `MAPPING`. Używane przez UI do
     * filtrowania listy tenant'ów oraz przez seeder/test setup.
     *
     * @return list<string>
     */
    public static function legacyCodes(): array
    {
        return array_keys(self::MAPPING);
    }

    /**
     * Zwraca rekomendowany nowy Plan dla zadanego tenant'a (po jego
     * obecnym kodzie planu). Zwraca null gdy plan tenant'a nie jest
     * legacy ALBO mapowanie nie pasuje (np. plan stable).
     */
    public function recommendedNewPlan(Tenant $tenant): ?Plan
    {
        $current = $tenant->plan_id !== null ? Plan::find($tenant->plan_id) : null;
        if ($current === null) {
            return null;
        }

        $newCode = self::MAPPING[$current->code] ?? null;
        if ($newCode === null) {
            return null;
        }

        return Plan::where('code', $newCode)->first();
    }

    /**
     * Wykonaj migrację. Idempotentne na poziomie "no-op" — jeśli tenant
     * już jest na docelowym planie, zwracamy bez zmiany.
     *
     * @param  'immediate'|'next_cycle'  $effective
     * @return array{changed:bool, old_plan_code:?string, new_plan_code:string, stripe_updated:bool}
     */
    public function migrate(
        Tenant $tenant,
        Plan $newPlan,
        string $effective = 'next_cycle',
        ?string $reason = null,
        bool $sendEmail = true,
    ): array {
        $oldPlan = $tenant->plan_id !== null ? Plan::find($tenant->plan_id) : null;
        $oldCode = $oldPlan?->code;

        if ($oldPlan !== null && $oldPlan->id === $newPlan->id) {
            return [
                'changed' => false,
                'old_plan_code' => $oldCode,
                'new_plan_code' => $newPlan->code,
                'stripe_updated' => false,
            ];
        }

        $stripeUpdated = false;

        DB::connection('central')->transaction(function () use ($tenant, $newPlan) {
            $tenant->forceFill(['plan_id' => $newPlan->id])->save();
        });

        // Stripe subscription swap (best-effort; nie blokujemy migracji
        // jeśli Stripe się wykrzaczy — admin dostanie warning w logu i
        // może ręcznie naprawić w Dashboard).
        if ($tenant->stripe_subscription_id !== null
            && $newPlan->stripe_price_monthly_id !== null
            && $newPlan->stripe_price_monthly_id !== ''
        ) {
            try {
                $stripeUpdated = $this->swapStripeSubscription(
                    subscriptionId: (string) $tenant->stripe_subscription_id,
                    newPriceId: (string) $newPlan->stripe_price_monthly_id,
                    effective: $effective,
                );
            } catch (\Throwable $e) {
                Log::warning('LegacyPlanMigrator Stripe swap failed', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $tenant->stripe_subscription_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->audit->record(
            action: 'plan.legacy_migrated',
            targetType: Tenant::class,
            targetId: (string) $tenant->id,
            tenantId: (string) $tenant->id,
            payload: [
                'old_plan_code' => $oldCode,
                'new_plan_code' => $newPlan->code,
                'effective' => $effective,
                'stripe_updated' => $stripeUpdated,
                'reason' => $reason,
            ],
        );

        if ($sendEmail) {
            $this->notifyOwner($tenant, $oldPlan, $newPlan, $effective);
        }

        return [
            'changed' => true,
            'old_plan_code' => $oldCode,
            'new_plan_code' => $newPlan->code,
            'stripe_updated' => $stripeUpdated,
        ];
    }

    /**
     * Stripe: zaktualizuj subskrypcję na nowy price ID. Wymaga
     * pobrania subscription items (Stripe API wymaga `items[0].id` żeby
     * podmienić price na konkretnym line item — nie można po prostu
     * "set price" jak w przypadku jednorazowej oferty).
     */
    private function swapStripeSubscription(string $subscriptionId, string $newPriceId, string $effective): bool
    {
        $stripe = $this->client();
        $sub = $stripe->subscriptions->retrieve($subscriptionId, []);
        $items = $sub->items->data ?? [];

        if ($items === []) {
            throw new RuntimeException("Subscription {$subscriptionId} has no items");
        }

        // Bierzemy pierwszy item — Hovera tworzy subskrypcje z jednym
        // recurring price + opcjonalnym one-time onboarding fee który
        // jest zamknięty po pierwszej fakturze, więc na running sub
        // zawsze jest tylko jeden item.
        $itemId = (string) $items[0]->id;

        $prorationBehavior = $effective === 'immediate' ? 'create_prorations' : 'none';

        $stripe->subscriptions->update($subscriptionId, [
            'items' => [[
                'id' => $itemId,
                'price' => $newPriceId,
            ]],
            'proration_behavior' => $prorationBehavior,
            'metadata' => [
                'migrated_at' => (string) now()->toIso8601String(),
                'migrated_by' => 'hovera_legacy_migrator',
            ],
        ]);

        return true;
    }

    private function notifyOwner(Tenant $tenant, ?Plan $oldPlan, Plan $newPlan, string $effective): void
    {
        $ownerEmail = $this->ownerEmailFor($tenant);
        if ($ownerEmail === null) {
            return;
        }

        try {
            NotificationFacade::route('mail', $ownerEmail)->notify(
                new TenantPlanMigratedNotification(
                    tenantName: $tenant->name,
                    oldPlanName: $oldPlan?->name ?? '—',
                    newPlanName: $newPlan->name,
                    newPriceFormatted: $this->formatPrice($newPlan),
                    effective: $effective,
                    lockInUntil: now()->addMonths(12)->startOfDay(),
                )
            );
        } catch (\Throwable $e) {
            Log::warning('TenantPlanMigratedNotification failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function ownerEmailFor(Tenant $tenant): ?string
    {
        $email = $tenant->memberships()
            ->where('role', 'owner')
            ->whereNull('revoked_at')
            ->orderBy('joined_at')
            ->limit(1)
            ->get()
            ->map(fn ($m) => $m->user?->email)
            ->filter()
            ->first();

        return is_string($email) ? $email : null;
    }

    private function formatPrice(Plan $plan): string
    {
        $cents = (int) ($plan->price_monthly_cents ?? 0);
        if ($cents <= 0) {
            return '—';
        }

        $currency = strtoupper((string) ($plan->currency ?? 'PLN'));

        return number_format($cents / 100, 0, ',', ' ').' '.$currency.' / mc';
    }

    private function client(): StripeClient
    {
        if ($this->stripe !== null) {
            return $this->stripe;
        }

        $secret = SystemSetting::getSecret('stripe.secret_key')
            ?? (string) config('services.stripe.secret', '');

        if ($secret === '') {
            throw new RuntimeException('plan.stripe.missing_api_key');
        }

        return $this->stripe = new StripeClient($secret);
    }

    public function setClient(StripeClient $client): void
    {
        $this->stripe = $client;
    }
}
