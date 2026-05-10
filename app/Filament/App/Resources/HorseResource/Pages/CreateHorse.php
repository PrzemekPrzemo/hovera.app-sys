<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\HorseResource\Pages;

use App\Exceptions\PlanLimitExceeded;
use App\Filament\App\Resources\HorseResource;
use App\Services\Billing\PlanLimitChecker;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Filament\Resources\Pages\CreateRecord;

class CreateHorse extends CreateRecord
{
    protected static string $resource = HorseResource::class;

    /**
     * Limity planu sprawdzamy zanim Filament w ogóle uruchomi
     * `handleRecordCreation` — żeby form data nawet nie poszedł
     * do bazy gdy stajnia jest na cap'ie. Blok = friendly notification +
     * `halt()` z CreateRecord.
     */
    protected function beforeCreate(): void
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return;
        }

        try {
            app(PlanLimitChecker::class)->assertCanAddHorse($tenant);
        } catch (PlanLimitExceeded $e) {
            $e->notify();
            $this->halt();
        }
    }

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
