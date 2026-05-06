<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Enums\HealthRecordType;
use App\Models\Tenant\HealthRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates upcoming + overdue health follow-ups into bite-sized
 * payloads for dashboard widgets / mail digests.
 *
 * Scope: only records whose `next_due_at` is set. A record without a
 * next-due is treated as "fire and forget" (e.g. one-off check-up,
 * Botox-style medication noted but no schedule).
 */
class UpcomingHealthAlertsService
{
    /**
     * @return Collection<int, array{
     *     id:string, horse_id:string, horse_name:string,
     *     type:HealthRecordType, type_label:string,
     *     summary:string, due_at:Carbon,
     *     days_until:int, is_overdue:bool,
     * }>
     */
    public function upcomingAndOverdue(int $windowDays = 30): Collection
    {
        $records = HealthRecord::query()
            ->with('horse')
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', now()->addDays($windowDays)->toDateString())
            ->orderBy('next_due_at')
            ->get();

        return $records->map(fn (HealthRecord $r) => [
            'id' => (string) $r->id,
            'horse_id' => (string) $r->horse_id,
            'horse_name' => $r->horse?->name ?? '—',
            'type' => $r->type,
            'type_label' => $r->type->label(),
            'summary' => $r->summary,
            'due_at' => $r->next_due_at,
            'days_until' => $r->daysUntilDue() ?? 0,
            'is_overdue' => $r->isOverdue(),
        ])->values();
    }

    /**
     * @return array{overdue:int, due_within_7_days:int, due_within_30_days:int}
     */
    public function counts(): array
    {
        return [
            'overdue' => HealthRecord::query()->overdue()->count(),
            'due_within_7_days' => HealthRecord::query()->dueWithin(7)->count(),
            'due_within_30_days' => HealthRecord::query()->dueWithin(30)->count(),
        ];
    }
}
