<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TransportReviewResource\Pages;

use App\Filament\Admin\Resources\TransportReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminTransportReviews extends ListRecords
{
    protected static string $resource = TransportReviewResource::class;
}
