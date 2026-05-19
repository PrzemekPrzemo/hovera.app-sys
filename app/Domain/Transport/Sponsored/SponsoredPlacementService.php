<?php

declare(strict_types=1);

namespace App\Domain\Transport\Sponsored;

use App\Domain\Transport\Public\TransporterRankingService;
use App\Models\Central\AddonPurchase;
use App\Services\MasterAuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * Sponsored placements — aplikuje featured boost po paid `sponsored_*` addon.
 * Patrz docs/TRANSPORT.md §16.
 *
 * Master admin (przez TransporterResource action „Sprzedaj wyróżnienie")
 * tworzy `AddonPurchase` z `addon_code=sponsored_30d|_60d|_90d` +
 * `side_effect_metadata.featured_days=N`. Webhook P24/PayU po sukcesie
 * wywołuje `applyFromPurchase()` który:
 *   1. Markuje tenant'a jako featured z `featured_until = now() + N days`
 *      (rolling extension jeśli już featured)
 *   2. Audit log entry `tenant.featured_sponsored_purchased`
 *   3. Flush `TransporterRankingService` cache (top 10 widget)
 *   4. Markuje `side_effect_applied_at` żeby nie aplikować duplikatu
 *      gdy webhook dochodzi 2x (idempotency)
 */
class SponsoredPlacementService
{
    public function __construct(
        private readonly MasterAuditLogger $audit,
        private readonly TransporterRankingService $ranking,
    ) {}

    /**
     * Aplikuje side-effect po paid AddonPurchase. Idempotent — gdy
     * `side_effect_applied_at` jest set, nie robi nic (drugi webhook
     * tej samej transakcji byłby duplikatem).
     *
     * Wywoływane przez:
     *   - `Przelewy24Service::processAddonWebhook` po flipie status=paid
     *   - `PayUService::processAddonWebhook` po flipie status=paid
     *   - Test (bezpośrednio z fixture'em)
     */
    public function applyFromPurchase(AddonPurchase $purchase): bool
    {
        if (! $purchase->isSponsored()) {
            return false;
        }

        if ($purchase->side_effect_applied_at !== null) {
            // Już zaaplikowane — kolejny webhook to retransmisja P24/PayU.
            return false;
        }

        if (! $purchase->isPaid()) {
            // Defensive — flow powinien wywołać po `status=paid`, ale na
            // wszelki wypadek skip żeby nie dawać featured za nieopłacone.
            return false;
        }

        $days = $purchase->featuredDays();
        if ($days <= 0) {
            Log::warning('Sponsored placement: featured_days missing/zero', [
                'purchase_id' => $purchase->id,
                'addon_code' => $purchase->addon_code,
            ]);

            return false;
        }

        $tenant = $purchase->tenant;
        if ($tenant === null) {
            Log::warning('Sponsored placement: tenant not found', [
                'purchase_id' => $purchase->id,
            ]);

            return false;
        }

        $tenant->markFeaturedUntil(
            until: now()->addDays($days),
            byUserId: $purchase->created_by_user_id,
        );

        $purchase->forceFill(['side_effect_applied_at' => now()])->save();

        $this->audit->record(
            action: 'tenant.featured_sponsored_purchased',
            targetType: 'Tenant',
            targetId: (string) $tenant->id,
            tenantId: (string) $tenant->id,
            payload: [
                'purchase_id' => $purchase->id,
                'addon_code' => $purchase->addon_code,
                'days' => $days,
                'featured_until' => $tenant->fresh()->featured_until?->toIso8601String(),
                'amount' => $purchase->amountFormatted(),
            ],
        );

        // Top 10 widget na landing `/transport` i `/przewoznicy` cache'uje
        // featured list — bust żeby nowy featured pojawił się od razu.
        $this->ranking->flushTopCache();

        return true;
    }
}
