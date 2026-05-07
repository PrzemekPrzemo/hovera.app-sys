<?php

declare(strict_types=1);

namespace App\Services\Master;

use App\Enums\CalendarEntryStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Client;
use App\Models\Tenant\HealthRecord;
use Illuminate\Support\Carbon;

/**
 * Heavyweight per-tenant health snapshotter — talks to the tenant DB.
 *
 * Run from a daily cron (SnapshotTenantHealthCommand). The result is
 * a 0–100 score plus an array of raw signals stored in tenant
 * metadata so support can drill into "why is this tenant red".
 *
 * MUST be called inside a TenantManager context (the active `tenant`
 * connection has to point at this tenant's database). The calculator
 * itself doesn't switch contexts to keep it composable; the caller
 * decides whether to call setCurrent / execute.
 *
 * Score weighting (0–100, clamped):
 *   +25  base for "active / trialing / past_due"
 *   +25  any booking in last 7d
 *   +15  any booking in last 30d (cumulative with above)
 *   +15  >=3 active clients (booked in last 90d)
 *   +10  zero overdue vet records
 *   +10  mature account (created 30+ days ago)
 *   -25  past_due
 *   -50  suspended
 *   -10  zero bookings ever (cold-start signal)
 *
 * Tunables intentionally simple — premature precision here would
 * just be noise. We can re-weight after watching real customers.
 */
class TenantHealthCalculator
{
    /**
     * @return array{score:int, last_activity_at:?Carbon, signals:array<string,mixed>}
     */
    public function snapshot(Tenant $tenant): array
    {
        $now = Carbon::now();

        $totalBookings = CalendarEntry::query()->count();
        $bookingsLast7d = CalendarEntry::query()
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->count();
        $bookingsLast30d = CalendarEntry::query()
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->count();

        $latestEntry = CalendarEntry::query()
            ->whereIn('status', [
                CalendarEntryStatus::Confirmed->value,
                CalendarEntryStatus::Completed->value,
                CalendarEntryStatus::Requested->value,
            ])
            ->latest('created_at')
            ->first();

        $activeClients = Client::query()
            ->whereIn('id', CalendarEntry::query()
                ->where('starts_at', '>=', $now->copy()->subDays(90))
                ->whereNotNull('client_id')
                ->distinct()
                ->pluck('client_id'))
            ->count();

        $overdueHealth = HealthRecord::query()
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<', $now->toDateString())
            ->count();

        $isActive = in_array($tenant->status, ['active', 'trialing'], true);
        $isPastDue = $tenant->status === 'past_due';
        $isSuspended = in_array($tenant->status, ['suspended', 'churned'], true);
        $mature = $tenant->created_at?->isBefore($now->copy()->subDays(30)) ?? false;

        $score = 0;
        if ($isActive || $isPastDue) {
            $score += 25;
        }
        if ($bookingsLast7d > 0) {
            $score += 25;
        }
        if ($bookingsLast30d > 0) {
            $score += 15;
        }
        if ($activeClients >= 3) {
            $score += 15;
        }
        if ($overdueHealth === 0) {
            $score += 10;
        }
        if ($mature) {
            $score += 10;
        }

        if ($isPastDue) {
            $score -= 25;
        }
        if ($isSuspended) {
            $score -= 50;
        }
        if ($totalBookings === 0) {
            $score -= 10;
        }

        $score = max(0, min(100, $score));

        $lastActivity = $latestEntry?->created_at;

        return [
            'score' => $score,
            'last_activity_at' => $lastActivity,
            'signals' => [
                'total_bookings' => $totalBookings,
                'bookings_last_7d' => $bookingsLast7d,
                'bookings_last_30d' => $bookingsLast30d,
                'active_clients_90d' => $activeClients,
                'overdue_health_records' => $overdueHealth,
                'is_active' => $isActive,
                'is_past_due' => $isPastDue,
                'is_suspended' => $isSuspended,
                'mature' => $mature,
                'computed_at' => $now->toIso8601String(),
            ],
        ];
    }
}
