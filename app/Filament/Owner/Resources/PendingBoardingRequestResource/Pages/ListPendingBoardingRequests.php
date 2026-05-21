<?php

declare(strict_types=1);

namespace App\Filament\Owner\Resources\PendingBoardingRequestResource\Pages;

use App\Filament\Owner\Resources\PendingBoardingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingBoardingRequests extends ListRecords
{
    protected static string $resource = PendingBoardingRequestResource::class;
}
