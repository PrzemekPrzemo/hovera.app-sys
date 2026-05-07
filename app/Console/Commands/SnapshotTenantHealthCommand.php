<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Master\TenantHealthCalculator;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily snapshot of every tenant's health. Writes back to central
 * tenants.health_score / last_activity_at + a metadata.health_signals
 * payload that powers the dashboard's drill-down view.
 *
 * Tolerant per-tenant: if one tenant's DB is unreachable we log it
 * and move on — the rest of the cohort still gets snapshotted.
 *
 * Skip-if-same-tenant pattern matches the booking reminders command
 * so this stays usable inside an already-active context (tests, queue
 * workers that pre-resolve tenancy).
 */
class SnapshotTenantHealthCommand extends Command
{
    protected $signature = 'tenants:snapshot-health
        {--tenant= : Only snapshot this tenant slug}';

    protected $description = 'Recompute health_score + last_activity_at from each tenant DB.';

    public function handle(
        TenantManager $tenants,
        TenantHealthCalculator $calculator,
        TenantAuditLogger $audit,
    ): int {
        $query = Tenant::query();
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        } else {
            $query->whereIn('status', ['trialing', 'active', 'past_due', 'suspended']);
        }

        $list = $query->get();
        if ($list->isEmpty()) {
            $this->info('No tenants to snapshot.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($list as $tenant) {
            try {
                $snapshot = $tenants->current()?->id === $tenant->id
                    ? $calculator->snapshot($tenant)
                    : $tenants->execute($tenant, fn () => $calculator->snapshot($tenant));

                $this->persist($tenant, $snapshot);
                $audit->record(
                    'tenant.health_snapshot',
                    'Tenant',
                    (string) $tenant->id,
                    ['score' => $snapshot['score']],
                );
                $this->line("→ {$tenant->slug}: {$snapshot['score']}/100");
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("× {$tenant->slug}: {$e->getMessage()}");
                report($e);
            }
        }

        $this->info("Snapshotted: {$ok}, failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * @param  array{score:int, last_activity_at:?Carbon, signals:array<string,mixed>}  $snapshot
     */
    private function persist(Tenant $tenant, array $snapshot): void
    {
        $metadata = $tenant->settings ?? [];
        $metadata['health_signals'] = $snapshot['signals'];

        $tenant->forceFill([
            'health_score' => $snapshot['score'],
            'last_activity_at' => $snapshot['last_activity_at'] ?? $tenant->last_activity_at,
            'settings' => $metadata,
        ])->save();
    }
}
