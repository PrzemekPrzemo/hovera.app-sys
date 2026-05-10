<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\ClientResource\Pages;

use App\Exceptions\PlanLimitExceeded;
use App\Filament\App\Resources\ClientResource;
use App\Services\Billing\PlanLimitChecker;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected function beforeCreate(): void
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return;
        }

        try {
            app(PlanLimitChecker::class)->assertCanAddClient($tenant);
        } catch (PlanLimitExceeded $e) {
            $e->notify();
            $this->halt();
        }
    }

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
