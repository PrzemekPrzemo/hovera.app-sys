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
}
