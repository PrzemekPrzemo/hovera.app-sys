<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\TransportReviewResource\Pages;

use App\Filament\Transport\Resources\TransportReviewResource;
use App\Filament\Transport\Resources\TransportReviewResource\Widgets\ReviewsStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListTransportReviews extends ListRecords
{
    protected static string $resource = TransportReviewResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ReviewsStatsWidget::class,
        ];
    }
}
