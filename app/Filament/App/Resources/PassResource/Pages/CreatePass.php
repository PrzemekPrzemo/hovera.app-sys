<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\PassResource\Pages;

use App\Filament\App\Resources\PassResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreatePass extends CreateRecord
{
    protected static string $resource = PassResource::class;

    protected function afterCreate(): void
    {
        app(TenantAuditLogger::class)->record(
            'pass.create',
            'Pass',
            (string) $this->record->getKey(),
            ['name' => $this->record->name, 'total_uses' => $this->record->total_uses],
        );
    }
}
