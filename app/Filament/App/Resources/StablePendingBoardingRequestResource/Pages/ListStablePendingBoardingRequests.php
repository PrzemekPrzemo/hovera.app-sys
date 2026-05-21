<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\StablePendingBoardingRequestResource\Pages;

use App\Filament\App\Resources\StablePendingBoardingRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListStablePendingBoardingRequests extends ListRecords
{
    protected static string $resource = StablePendingBoardingRequestResource::class;
}
