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
            // z nazwą pola (`slug: The slug has already been taken.`) żeby admin
            // jednym spojrzeniem wiedział co naprawić.
            $body = $this->formatValidationErrors($e->errors());
            Notification::make()
                ->danger()
                ->title(__('admin/tenant.notify.create_failed'))
                ->body($body)
                ->persistent()
                ->send();

            $this->safelyLog('warning', 'Tenant create validation failed', [
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

            $this->safelyLog('error', 'Tenant create failed', [
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

    /**
     * Składa multi-field validation errors w czytelny string dla Filament
     * Notification: `slug: The slug has already been taken. | name: required`.
     * Jeden field per linia żeby admin widział wszystkie naraz.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    private function formatValidationErrors(array $errors): string
    {
        $lines = [];
        foreach ($errors as $field => $messages) {
            if (! is_array($messages) || $messages === []) {
                continue;
            }
            $lines[] = $field.': '.(string) reset($messages);
        }

        return implode(' | ', $lines) ?: __('admin/tenant.notify.create_failed');
    }

    /**
     * Logowanie z fallbackiem na error_log (PHP-FPM stderr) gdy
     * `storage/logs/*.log` nie ma write permission. Bez tego pierwotny
     * exception z provisioningu jest cichy bo Log driver rzuca własny
     * exception zanim Notification dotrze do UI.
     *
     * Ops-side fix to chmod na storage; ten fallback gwarantuje że admin
     * przynajmniej dostanie czytelną Notification nawet przy zepsutych
     * permach.
     *
     * @param  array<string, mixed>  $context
     */
    private function safelyLog(string $level, string $message, array $context): void
    {
        try {
            Log::$level($message, $context);
        } catch (Throwable $e) {
            // Ostatnia szansa — error_log idzie do PHP-FPM stderr (Plesk
            // pokazuje w panelu Logi). Nie używamy ->error() bo to ten
            // sam Log fasada który właśnie padł.
            error_log(sprintf(
                '[hovera] %s: %s (context: %s) (log write failed: %s)',
                strtoupper($level),
                $message,
                json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                $e->getMessage(),
            ));
        }
    }
}
