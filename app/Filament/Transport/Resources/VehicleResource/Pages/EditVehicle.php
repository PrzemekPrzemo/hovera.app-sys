<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\VehicleResource\Pages;

use App\Filament\Transport\Resources\VehicleResource;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;
}
