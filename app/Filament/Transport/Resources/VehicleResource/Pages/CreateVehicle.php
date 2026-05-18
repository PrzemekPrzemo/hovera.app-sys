<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\VehicleResource\Pages;

use App\Filament\Transport\Resources\VehicleResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected function afterCreate(): void
    {
        app(TenantAuditLogger::class)->record(
            'vehicle.create',
            'Vehicle',
            (string) $this->record->getKey(),
            [
                'name' => $this->record->name,
                'registration_plate' => $this->record->registration_plate,
            ],
        );
    }
}
