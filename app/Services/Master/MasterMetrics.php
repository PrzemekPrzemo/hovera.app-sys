<?php

declare(strict_types=1);

namespace App\Services\Master;

use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for /admin dashboard metrics.
 *
 * Cheap by design: aggregates run on the central DB only — never opens
 * a tenant connection from this class. Anything that needs per-tenant
 * activity data should pre-aggregate into central via a heartbeat job
 * (out of scope for this iteration; we only have last_activity_at and
 * status today).
 *
 * Numbers normalised to monthly when reporting MRR — yearly subs count
 * as price_yearly_cents / 12.
 */
class MasterMetrics
{
    /**
     * Statuses that contribute to recurring revenue. Trialing is
     * included because most trials convert and we want to see the
     * potential pipeline next to firm revenue.
     *
     * @var list<string>
     */
    public const REVENUE_STATUSES = ['active', 'trialing', 'past_due'];

    /**
     * @return array<string,int>
     */
    public function tenantCountsByStatus(): array
    {
        $rows = Tenant::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $statuses = ['provisioning', 'trialing', 'active', 'past_due', 'suspended', 'churned'];

        return array_combine(
            $statuses,
            array_map(fn (string $s) => (int) ($rows[$s] ?? 0), $statuses),
        );
    }

    public function totalActiveTenants(): int
    {
        return Tenant::query()
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->count();
    }

    /**
     * Monthly Recurring Revenue in cents. Yearly subs amortised /12.
     */
    public function mrrCents(): int
    {
        $subs = Subscription::query()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->get();

        $total = 0;
        foreach ($subs as $sub) {
            $plan = $sub->plan;
            if (! $plan) {
                continue;
            }
            $total += $sub->billing_cycle === 'yearly'
                ? (int) round($plan->price_yearly_cents / 12)
                : (int) $plan->price_monthly_cents;
        }

        return $total;
    }

    public function arrCents(): int
    {
        return $this->mrrCents() * 12;
    }

    /**
     * Churn rate over the trailing window — tenants whose status
     * became `churned` (or subscription `cancelled`) in the last
     * $days days, divided by the number active at the window start.
     *
     * Returns a 0..1 ratio. Returns 0.0 when there's nothing to
     * divide by (no tenants at the start of the window).
     */
    public function churnRate(int $days = 30): float
    {
        $windowStart = Carbon::now()->subDays($days);

        $churnedInWindow = Tenant::query()
            ->where('status', 'churned')
            ->where('updated_at', '>=', $windowStart)
            ->count();

        // "Active at window start" = anything that wasn't already churned by then
        $activeAtStart = Tenant::query()
            ->where('created_at', '<', $windowStart)
            ->where(function ($q) use ($windowStart) {
                $q->where('status', '!=', 'churned')
                    ->orWhere('updated_at', '>=', $windowStart);
            })
            ->count();

        if ($activeAtStart === 0) {
            return 0.0;
        }

        return round($churnedInWindow / $activeAtStart, 4);
    }

    /**
     * Most recently active tenants. Used as the "needs love?" panel
     * on the dashboard — paired with last_activity_at.
     *
     * @return Collection<int, Tenant>
     */
    public function recentlyActiveTenants(int $limit = 10): Collection
    {
        return Tenant::query()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Tenants going stale — no activity in $sinceDays. Top suspect
     * for churn risk; act early, save the account.
     *
     * @return Collection<int, Tenant>
     */
    public function staleTenants(int $sinceDays = 14, int $limit = 10): Collection
    {
        $cutoff = Carbon::now()->subDays($sinceDays);

        return Tenant::query()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<', $cutoff);
            })
            ->orderBy('last_activity_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Lightweight per-tenant health score 0–100 derived from public
     * signals (no tenant DB access needed). Higher = healthier.
     *
     *   +50  active subscription / not past_due
     *   +30  any activity in last 7 days
     *   +20  in business 30+ days (past honeymoon)
     *   -25  past_due
     *   -50  suspended
     *
     * Returned as a clamped int. Persisted score takes precedence
     * if a periodic job has stamped one — this method is the
     * fallback for live computation in the dashboard.
     *
     * @return array{score:int, signals:array<string,bool>}
     */
    public function liveHealth(Tenant $tenant): array
    {
        $signals = [
            'active' => in_array($tenant->status, ['active', 'trialing'], true),
            'past_due' => $tenant->status === 'past_due',
            'suspended' => in_array($tenant->status, ['suspended', 'churned'], true),
            'recent_activity' => $tenant->last_activity_at?->isAfter(now()->subDays(7)) ?? false,
            'mature' => $tenant->created_at?->isBefore(now()->subDays(30)) ?? false,
        ];

        $score = 0;
        $score += $signals['active'] ? 50 : 0;
        $score += $signals['recent_activity'] ? 30 : 0;
        $score += $signals['mature'] ? 20 : 0;
        $score -= $signals['past_due'] ? 25 : 0;
        $score -= $signals['suspended'] ? 50 : 0;

        return [
            'score' => max(0, min(100, $score)),
            'signals' => $signals,
        ];
    }

    public function formatCents(int $cents, string $currency = 'PLN'): string
    {
        return number_format($cents / 100, 2, ',', ' ').' '.$currency;
    }
}
