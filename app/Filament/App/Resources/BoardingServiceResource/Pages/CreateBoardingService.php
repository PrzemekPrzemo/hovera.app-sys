<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\BoardingServiceResource\Pages;

use App\Filament\App\Resources\BoardingServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBoardingService extends CreateRecord
{
    protected static string $resource = BoardingServiceResource::class;
}
