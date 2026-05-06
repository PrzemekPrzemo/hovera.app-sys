<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ClientResource\Pages;

use App\Filament\App\Resources\ClientResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function afterCreate(): void
    {
        app(TenantAuditLogger::class)->record(
            'client.create',
            'Client',
            (string) $this->record->getKey(),
            ['name' => $this->record->name],
        );
    }
}
