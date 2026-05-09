<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Reports;

use App\Enums\CalendarEntryStatus;
use App\Filament\Concerns\RestrictedByTenantRole;
use App\Models\Tenant\CalendarEntry;
use App\Models\Tenant\Instructor;
use App\Services\Reports\MonthRange;
use App\Services\Tenancy\TenantRoleGate;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Instructor utilization — hours taught per instructor per month +
 * attendance rate (completed vs no-show vs cancelled).
 */
class InstructorUtilizationReport extends Page
{
    use RestrictedByTenantRole;

    /** @return list<string> */
    protected static function allowedRoles(): array
    {
        return TenantRoleGate::FINANCE_STAFF;
    }

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 94;

    protected static string $view = 'filament.app.pages.reports.instructor-utilization';

    #[Url]
    public ?string $month = null;

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.reports');
    }

    public static function getNavigationLabel(): string
    {
        return __('pages.reports.instructor_utilization.navigation');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pages.reports.instructor_utilization.title');
    }

    public function range(): MonthRange
    {
        return MonthRange::from($this->month);
    }

    /**
     * @return array{
     *     range:MonthRange,
     *     rows: Collection,
     * }
     */
    public function snapshot(): array
    {
        $range = $this->range();

        $entries = CalendarEntry::query()
            ->whereNotNull('instructor_id')
            ->whereBetween('starts_at', [$range->start, $range->end])
            ->get(['id', 'instructor_id', 'starts_at', 'ends_at', 'status']);

        $byInstructor = $entries->groupBy('instructor_id');

        $instructorIds = $byInstructor->keys()->all();
        $instructors = Instructor::query()
            ->whereIn('id', $instructorIds)
            ->pluck('name', 'id');

        $rows = $byInstructor->map(function ($group, $instructorId) use ($instructors) {
            $confirmed = $group->whereIn('status', [
                CalendarEntryStatus::Confirmed,
                CalendarEntryStatus::Completed,
            ]);
            $cancelled = $group->where('status', CalendarEntryStatus::Cancelled)->count();
            $noShow = $group->where('status', CalendarEntryStatus::NoShow)->count();

            $seconds = $confirmed->sum(fn ($e) => $e->starts_at->diffInSeconds($e->ends_at));

            $totalSlots = $group->count();
            $attendance = $totalSlots > 0
                ? round(($confirmed->count() / $totalSlots) * 100)
                : 0;

            return [
                'instructor_id' => (string) $instructorId,
                'instructor_name' => $instructors->get($instructorId, '—'),
                'lesson_count' => $confirmed->count(),
                'hours' => round($seconds / 3600, 1),
                'cancelled' => $cancelled,
                'no_show' => $noShow,
                'attendance_pct' => (int) $attendance,
            ];
        })->sortByDesc('hours')->values();

        return [
            'range' => $range,
            'rows' => $rows,
        ];
    }

    public function colorForAttendance(int $pct): string
    {
        return match (true) {
            $pct >= 90 => 'success',
            $pct >= 70 => 'warning',
            default => 'danger',
        };
    }
}
