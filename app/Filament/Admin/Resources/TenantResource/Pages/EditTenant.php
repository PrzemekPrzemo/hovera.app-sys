<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Filament\Admin\Resources\TenantResource;
use App\Services\MasterAuditLogger;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function afterSave(): void
    {
        /** @var MasterAuditLogger $audit */
        $audit = app(MasterAuditLogger::class);

        $audit->record(
            'tenant.update',
            'Tenant',
            $this->record->id,
            $this->record->id,
            $this->record->getChanges(),
        );
    }
}
