<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Transport\Public\TransporterRankingService;
use App\Models\Central\Tenant;
use App\Services\MasterAuditLogger;
use Illuminate\Console\Command;

/**
 * Daily cron: gasi sponsored featured boost dla tenantów którym
 * `featured_until` już minął. Patrz docs/TRANSPORT.md §16.
 *
 * Schedule: codziennie o 02:00 (off-peak hours). TransporterRankingService
 * sortuje real-time z computed `is_active_featured` więc real-world wpływ
 * cron'a to tylko czyszczenie `is_featured=false` żeby:
 *   1. Admin panel /admin/tenants filter „featured" pokazywał tylko aktywne
 *   2. Tenant.markFeaturedUntil() z rolling extension działał poprawnie
 *      (gdy stary featured wygasł, nowy zakup nie powinien być doliczany
 *      do nieistniejącego okresu)
 */
class ExpireFeaturedTenants extends Command
{
    protected $signature = 'transport:expire-featured';

    protected $description = 'Daily cron: flip is_featured=false dla tenantów z featured_until < NOW().';

    public function handle(MasterAuditLogger $audit, TransporterRankingService $ranking): int
    {
        $expired = Tenant::query()
            ->where('is_featured', true)
            ->whereNotNull('featured_until')
            ->where('featured_until', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired featured tenants.');

            return self::SUCCESS;
        }

        foreach ($expired as $tenant) {
            $tenant->forceFill([
                'is_featured' => false,
            ])->save();

            $audit->record(
                action: 'tenant.featured_expired',
                targetType: 'Tenant',
                targetId: (string) $tenant->id,
                tenantId: (string) $tenant->id,
                payload: [
                    'featured_until' => $tenant->featured_until?->toIso8601String(),
                ],
            );
        }

        $ranking->flushTopCache();

        $this->info(sprintf('Expired featured for %d tenant(s).', $expired->count()));

        return self::SUCCESS;
    }
}
