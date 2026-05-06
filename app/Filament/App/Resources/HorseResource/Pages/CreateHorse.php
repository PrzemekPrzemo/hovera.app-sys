<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\Pages;

use App\Filament\App\Resources\HorseResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateHorse extends CreateRecord
{
    protected static string $resource = HorseResource::class;

    protected function afterCreate(): void
    {
        app(TenantAuditLogger::class)->record(
            'horse.create',
            'Horse',
            (string) $this->record->getKey(),
            ['name' => $this->record->name],
        );
    }
}
