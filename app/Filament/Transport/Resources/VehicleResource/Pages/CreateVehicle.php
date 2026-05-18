<?php

declare(strict_types=1);

namespace App\Filament\Transport\Resources\VehicleResource\Pages;

use App\Exceptions\PlanLimitExceeded;
use App\Filament\Transport\Resources\VehicleResource;
use App\Services\Billing\PlanLimitChecker;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    /**
     * Gating na liczbę pojazdów — Solo 1, Pro 5, Fleet unlimited.
     * Sprawdzamy przed Filament `handleRecordCreation`, żeby insert
     * nawet nie poszedł do bazy gdy transporter jest na cap'ie.
     */
    protected function beforeCreate(): void
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return;
        }

        try {
            app(PlanLimitChecker::class)->assertCanAddVehicle($tenant);
        } catch (PlanLimitExceeded $e) {
            $e->notify();
            $this->halt();
        }
    }

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
