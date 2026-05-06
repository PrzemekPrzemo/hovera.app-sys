<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Actions\Tenants\CreateTenant as CreateTenantAction;
use App\Filament\Admin\Resources\TenantResource;
use App\Models\Central\Plan;
use App\Services\MasterAuditLogger;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/**
 * Routes Filament's default save through our CreateTenant action so the
 * MySQL database + user are provisioned in the same flow as CLI.
 */
class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        /** @var CreateTenantAction $action */
        $action = app(CreateTenantAction::class);
        /** @var MasterAuditLogger $audit */
        $audit = app(MasterAuditLogger::class);

        $planCode = null;
        if (!empty($data['plan_id'])) {
            $planCode = Plan::find($data['plan_id'])?->code;
        }

        $tenant = $action->execute([
            'slug'      => $data['slug'],
            'name'      => $data['name'],
            'country'   => $data['country'] ?? 'PL',
            'locale'    => $data['locale'] ?? 'pl',
            'timezone'  => $data['timezone'] ?? 'Europe/Warsaw',
            'currency'  => $data['currency'] ?? 'PLN',
            'plan_code' => $planCode,
        ]);

        $audit->record('tenant.create', 'Tenant', $tenant->id, $tenant->id, [
            'slug' => $tenant->slug,
            'plan' => $planCode,
        ]);

        Notification::make()
            ->success()
            ->title('Stajnia utworzona')
            ->body("Baza {$tenant->db_name} została zainicjowana.")
            ->send();

        return $tenant;
    }
}
