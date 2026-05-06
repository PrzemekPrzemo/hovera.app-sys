<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HealthRecordResource\Pages;

use App\Enums\HealthRecordType;
use App\Filament\App\Resources\HealthRecordResource;
use App\Services\TenantAuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateHealthRecord extends CreateRecord
{
    protected static string $resource = HealthRecordResource::class;

    protected function afterCreate(): void
    {
        $type = $this->record->type instanceof HealthRecordType
            ? $this->record->type->value
            : (string) $this->record->type;

        app(TenantAuditLogger::class)->record(
            'health.create',
            'HealthRecord',
            (string) $this->record->getKey(),
            ['horse_id' => $this->record->horse_id, 'type' => $type],
        );
    }
}
