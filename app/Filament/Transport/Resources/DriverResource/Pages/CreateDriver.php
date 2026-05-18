<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\DriverResource\Pages;

use App\Exceptions\PlanLimitExceeded;
use App\Filament\Transport\Resources\DriverResource;
use App\Services\Billing\PlanLimitChecker;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Resources\Pages\CreateRecord;

class CreateDriver extends CreateRecord
{
    protected static string $resource = DriverResource::class;

    /**
     * Gating na liczbę kierowców — Solo 2, Pro 10, Fleet unlimited.
     */
    protected function beforeCreate(): void
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return;
        }

        try {
            app(PlanLimitChecker::class)->assertCanAddDriver($tenant);
        } catch (PlanLimitExceeded $e) {
            $e->notify();
            $this->halt();
        }
    }

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
