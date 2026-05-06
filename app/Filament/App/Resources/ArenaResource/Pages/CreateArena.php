<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ArenaResource\Pages;

use App\Filament\App\Resources\ArenaResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateArena extends CreateRecord
{
    protected static string $resource = ArenaResource::class;

    protected function afterCreate(): void
    {
        app(TenantAuditLogger::class)->record(
            'arena.create',
            'Arena',
            (string) $this->record->getKey(),
            ['name' => $this->record->name],
        );
    }
}
