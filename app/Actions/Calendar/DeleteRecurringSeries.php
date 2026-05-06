<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Enums\CalendarEntryStatus;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\RecurringCalendarEntry;
use App\Services\Calendar\PassUseManager;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Cancel a recurring series.
 *
 * Default behaviour:
 *   - All FUTURE occurrences (starts_at >= now) are cancelled
 *     (status='cancelled', soft-deleted) so they vanish from the day
 *     plan and any active pass uses get restored where in policy.
 *   - PAST occurrences are kept untouched — they're a record of what
 *     actually happened.
 *   - The master template is soft-deleted so it stops appearing in
 *     the recurring-events list and won't be re-expanded.
 */
class DeleteRecurringSeries
{
    public function __construct(
        private readonly PassUseManager $passes,
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * @return array{cancelled: int}
     */
    public function execute(RecurringCalendarEntry $series): array
    {
        $cancelled = DB::connection('tenant')->transaction(function () use ($series) {
            $futureEntries = CalendarEntry::query()
                ->where('recurrence_id', $series->id)
                ->where('starts_at', '>=', now())
                ->whereIn('status', [
                    CalendarEntryStatus::Confirmed->value,
                    CalendarEntryStatus::Requested->value,
                ])
                ->get();

            foreach ($futureEntries as $entry) {
                $entry->forceFill(['status' => CalendarEntryStatus::Cancelled->value])->save();
                $this->passes->restoreFor($entry, 'recurring-series-cancelled');
                $entry->delete();   // soft delete
            }

            $series->forceFill(['is_active' => false])->save();
            $series->delete();   // soft delete the master too

            return $futureEntries->count();
        });

        $this->audit->record(
            'recurrence.cancelled',
            'RecurringCalendarEntry',
            (string) $series->getKey(),
            ['future_occurrences_cancelled' => $cancelled],
        );

        return ['cancelled' => $cancelled];
    }
}
