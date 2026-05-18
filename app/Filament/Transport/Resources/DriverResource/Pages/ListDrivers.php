<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\DriverResource\Pages;

use App\Filament\Transport\Resources\DriverResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDrivers extends ListRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
