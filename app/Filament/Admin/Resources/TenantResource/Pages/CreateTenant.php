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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        try {
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
        } catch (ValidationException $e) {
            // Walidacja po stronie action'a — pokaż błędy jako Filament Notification
            // żeby admin wiedział czemu submit nie poszedł (zamiast cichego rollbacku).
            $firstError = collect($e->errors())->flatten()->first() ?: $e->getMessage();
            Notification::make()
                ->danger()
                ->title(__('admin/tenant.notify.create_failed'))
                ->body((string) $firstError)
                ->persistent()
                ->send();

            Log::warning('Tenant create validation failed', [
                'slug' => $data['slug'] ?? '?',
                'type' => $type->value,
                'errors' => $e->errors(),
            ]);

            $this->halt();
        } catch (Throwable $e) {
            // Provisioning padł (MySQL CREATE DATABASE, GRANT, migrate, etc.).
            // Surface error w UI zamiast cichego ekranu "myśli ale nic się nie dzieje".
            Notification::make()
                ->danger()
                ->title(__('admin/tenant.notify.create_failed'))
                ->body($e->getMessage())
                ->persistent()
                ->send();

            Log::error('Tenant create failed', [
                'slug' => $data['slug'] ?? '?',
                'type' => $type->value,
                'plan_code' => $planCode,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->halt();
        }

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
