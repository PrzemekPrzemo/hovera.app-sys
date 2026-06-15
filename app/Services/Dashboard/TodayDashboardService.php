<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\CalendarEntryStatus;
use App\Enums\InvoiceStatus;
use App\Models\Tenant\Box;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Invoice;
use App\Tenancy\TenantManager;

/**
 * Aggregates the four "Today" KPIs for the /app dashboard widget:
 * bookings starting today, vacant boxes, overdue care items,
 * unpaid (issued) invoices total.
 *
 * Defensive pattern matches UpcomingHealthAlertsService — return
 * zeros instead of crashing when no tenant connection is bound.
 */
class TodayDashboardService
{
    /**
     * @return array{
     *     bookings_today:int,
     *     vacant_boxes:int,
     *     overdue_care:int,
     *     unpaid_invoices_count:int,
     *     unpaid_invoices_total_cents:int,
     * }
     */
    public function snapshot(): array
    {
        $empty = [
            'bookings_today' => 0,
            'vacant_boxes' => 0,
            'overdue_care' => 0,
            'unpaid_invoices_count' => 0,
            'unpaid_invoices_total_cents' => 0,
        ];

        if (! app(TenantManager::class)->hasTenant()) {
            return $empty;
        }

        try {
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();

            $bookings = CalendarEntry::query()
                ->whereBetween('starts_at', [$todayStart, $todayEnd])
                ->whereNotIn('status', [
                    CalendarEntryStatus::Cancelled->value,
                    CalendarEntryStatus::NoShow->value,
                ])
                ->count();

            // Vacant = is_active && capacity > assigned horses. We do
            // it in PHP because `freeSpots()` already encapsulates the
            // rule and box counts on a single tenant are tiny (<200).
            $vacant = Box::query()
                ->where('is_active', true)
                ->get()
                ->filter(fn (Box $b) => $b->isVacant())
                ->count();

            $overdue = HealthRecord::query()->overdue()->count();

            $unpaidQuery = Invoice::query()->where('status', InvoiceStatus::Issued->value);

            return [
                'bookings_today' => $bookings,
                'vacant_boxes' => $vacant,
                'overdue_care' => $overdue,
                'unpaid_invoices_count' => $unpaidQuery->count(),
                'unpaid_invoices_total_cents' => (int) $unpaidQuery->sum('total_cents'),
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * 7-day trend per KPI. Returns array of 7 daily values per metric,
     * index 0 = 6 days ago, index 6 = today. Used by the dashboard
     * sparklines + delta chip ("+1 vs yesterday").
     *
     * `vacant_boxes` is intentionally not in the trend — it's a
     * snapshot-only metric (no historical record of past occupancy).
     *
     * @return array{
     *     bookings_today: list<int>,
     *     overdue_care: list<int>,
     *     unpaid_invoices_count: list<int>,
     *     unpaid_invoices_total_cents: list<int>,
     * }
     */
    public function trend(int $days = 7): array
    {
        $empty = [
            'bookings_today' => array_fill(0, $days, 0),
            'overdue_care' => array_fill(0, $days, 0),
            'unpaid_invoices_count' => array_fill(0, $days, 0),
            'unpaid_invoices_total_cents' => array_fill(0, $days, 0),
        ];

        if (! app(TenantManager::class)->hasTenant() || $days < 1) {
            return $empty;
        }

        try {
            // Bookings per day — single GROUP BY query, then fill gaps.
            $start = now()->subDays($days - 1)->startOfDay();
            $bookingsByDate = CalendarEntry::query()
                ->where('starts_at', '>=', $start)
                ->where('starts_at', '<=', now()->endOfDay())
                ->whereNotIn('status', [
                    CalendarEntryStatus::Cancelled->value,
                    CalendarEntryStatus::NoShow->value,
                ])
                ->get(['starts_at'])
                ->countBy(fn ($e) => $e->starts_at->toDateString());

            // For the remaining 3 metrics we need historical counts
            // ("how many were overdue/issued-unpaid AS OF day X"), which
            // is per-day query territory. 7 queries × 2 metrics = 14
            // queries per refresh. Acceptable at single-tenant scale.
            $bookings = [];
            $overdue = [];
            $unpaidCount = [];
            $unpaidCents = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $day = now()->subDays($i)->startOfDay();
                $dayEnd = $day->copy()->endOfDay();
                $dateStr = $day->toDateString();

                $bookings[] = (int) ($bookingsByDate[$dateStr] ?? 0);

                $overdue[] = HealthRecord::query()
                    ->whereNotNull('next_due_at')
                    ->where('next_due_at', '<', $dateStr)
                    ->count();

                $unpaidQ = Invoice::query()
                    ->where('status', InvoiceStatus::Issued->value)
                    ->where('issued_at', '<=', $dayEnd)
                    ->where(function ($q) use ($dayEnd) {
                        $q->whereNull('paid_at')->orWhere('paid_at', '>', $dayEnd);
                    });

                $unpaidCount[] = (int) $unpaidQ->count();
                $unpaidCents[] = (int) $unpaidQ->sum('total_cents');
            }

            return [
                'bookings_today' => $bookings,
                'overdue_care' => $overdue,
                'unpaid_invoices_count' => $unpaidCount,
                'unpaid_invoices_total_cents' => $unpaidCents,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }
}
