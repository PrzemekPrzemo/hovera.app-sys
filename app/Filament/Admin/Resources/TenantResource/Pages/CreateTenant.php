<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TenantResource\Pages;

use App\Actions\Tenants\CreateTenant as CreateTenantAction;
use App\Enums\TenantType;
use App\Filament\Admin\Resources\TenantResource;
use App\Models\Central\Plan;
use App\Services\MasterAuditLogger;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Routes Filament's default save through our CreateTenant action so the
 * MySQL database + user are provisioned in the same flow as CLI.
 */
class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var CreateTenantAction $action */
        $action = app(CreateTenantAction::class);
        /** @var MasterAuditLogger $audit */
        $audit = app(MasterAuditLogger::class);

        $type = TenantType::tryFrom($data['type'] ?? '') ?? TenantType::Stable;

        $planCode = null;
        if (! empty($data['plan_id'])) {
            $planCode = Plan::find($data['plan_id'])?->code;
        }

        $tenant = $action->execute([
            'type' => $type->value,
            'slug' => $data['slug'],
            'name' => $data['name'],
            'country' => $data['country'] ?? 'PL',
            'locale' => $data['locale'] ?? 'pl',
            'timezone' => $data['timezone'] ?? 'Europe/Warsaw',
            'currency' => $data['currency'] ?? 'PLN',
            'plan_code' => $planCode,
        ]);

        $audit->record('tenant.create', 'Tenant', $tenant->id, $tenant->id, [
            'slug' => $tenant->slug,
            'type' => $type->value,
            'plan' => $planCode,
        ]);

        $title = $type === TenantType::Transporter
            ? __('admin/tenant.notify.created_transporter')
            : __('admin/tenant.notify.created_stable');

        Notification::make()
            ->success()
            ->title($title)
            ->body(__('admin/tenant.notify.created_body', ['db' => $tenant->db_name]))
            ->send();

        return $tenant;
    }
}
