<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Support\Carbon;

/**
 * Tiny value object used by every monthly report to coerce a "YYYY-MM"
 * input into Carbon start/end pair, with safe fallback to current month.
 */
final class MonthRange
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly string $key,
    ) {}

    public static function from(?string $monthKey): self
    {
        if ($monthKey && preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $monthKey.'-01')->startOfDay();
            } catch (\Throwable) {
                $start = now()->startOfMonth();
                $monthKey = $start->format('Y-m');
            }
        } else {
            $start = now()->startOfMonth();
            $monthKey = $start->format('Y-m');
        }

        return new self(
            start: $start,
            end: $start->copy()->endOfMonth(),
            key: $monthKey,
        );
    }

    public function label(): string
    {
        return $this->start->translatedFormat('LLLL Y');
    }
}
