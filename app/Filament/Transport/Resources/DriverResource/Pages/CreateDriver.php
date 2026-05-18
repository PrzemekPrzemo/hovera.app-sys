<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\DriverResource\Pages;

use App\Filament\Transport\Resources\DriverResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateDriver extends CreateRecord
{
    protected static string $resource = DriverResource::class;

    protected function afterCreate(): void
    {
        app(TenantAuditLogger::class)->record(
            'driver.create',
            'Driver',
            (string) $this->record->getKey(),
            ['full_name' => $this->record->full_name],
        );
    }
}
