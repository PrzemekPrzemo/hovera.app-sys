<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\TransportReviewResource\Widgets;

use App\Models\Central\TransportReview;
use App\Tenancy\TenantManager;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Stats nad listą recenzji w panelu /transport: średnia / count / dist.
 * Korzysta z TransportReview::aggregateFor (cache 10 min) — bez extra
 * query per request.
 */
class ReviewsStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return [];
        }

        $agg = TransportReview::aggregateFor($tenant);
        $count = (int) ($agg['count'] ?? 0);
        $avg = (float) ($agg['average'] ?? 0);
        $distribution = (array) ($agg['distribution'] ?? []);
        $fives = (int) ($distribution[5] ?? 0);

        return [
            Stat::make(__('transport/reviews.stats.average'), $count > 0 ? number_format($avg, 1, ',', ' ').' / 5' : '—')
                ->description($count > 0 ? str_repeat('★', (int) round($avg)) : __('transport/reviews.stats.no_reviews_yet'))
                ->color($avg >= 4.5 ? 'success' : ($avg >= 3.5 ? 'warning' : 'gray')),
            Stat::make(__('transport/reviews.stats.count'), (string) $count)
                ->description(__('transport/reviews.stats.count_desc')),
            Stat::make(__('transport/reviews.stats.five_stars'), (string) $fives)
                ->description($count > 0 ? round(100 * $fives / max(1, $count)).'%' : '—')
                ->color('success'),
        ];
    }
}
