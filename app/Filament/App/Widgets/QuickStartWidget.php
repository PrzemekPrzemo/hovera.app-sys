<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Services\Tenancy\TenantRoleGate;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;
use Throwable;

/**
 * Empty-state CTA card na dashboard'zie stable — pokazuje co user
 * powinien zrobić zanim panel ożyje. Każdy slot widoczny niezależnie
 * (klienci / konie / KSeF), znika gdy konkretny check przejdzie.
 *
 * Sort = -7 (zaraz pod OnboardingBannerWidget = -8, nad statystykami).
 * canView() → false gdy wszystkie 3 sloty puste (nie ma sensu pokazywać
 * pustej karty).
 *
 * Tenant DB queries owinięte w try/catch — nowy tenant przed migracją
 * (rzadkie) nie powinien wysadzać dashboard'u.
 */
class QuickStartWidget extends Widget
{
    protected static ?int $sort = -7;

    protected static string $view = 'filament.app.widgets.quick-start';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return false;
        }

        $slots = self::buildSlots($tenant);

        return $slots !== [];
    }

    /**
     * @return list<array{key:string, icon:string, label:string, body:string, cta:string, url:string}>
     */
    public function getSlots(): array
    {
        $tenant = app(TenantManager::class)->current();

        return $tenant === null ? [] : self::buildSlots($tenant);
    }

    /**
     * @return list<array{key:string, icon:string, label:string, body:string, cta:string, url:string}>
     */
    private static function buildSlots(Tenant $tenant): array
    {
        $slots = [];

        try {
            $clientsCount = Client::query()->count();
        } catch (Throwable) {
            $clientsCount = 1; // soft-skip dla nowych tenantów bez migracji
        }
        if ($clientsCount === 0) {
            $slots[] = [
                'key' => 'clients',
                'icon' => 'heroicon-o-user-group',
                'label' => (string) __('app/quick_start.clients.label'),
                'body' => (string) __('app/quick_start.clients.body'),
                'cta' => (string) __('app/quick_start.clients.cta'),
                'url' => '/app/clients/create',
            ];
        }

        try {
            $horsesCount = Horse::query()->count();
        } catch (Throwable) {
            $horsesCount = 1;
        }
        if ($horsesCount === 0) {
            $slots[] = [
                'key' => 'horses',
                'icon' => 'heroicon-o-bolt',
                'label' => (string) __('app/quick_start.horses.label'),
                'body' => (string) __('app/quick_start.horses.body'),
                'cta' => (string) __('app/quick_start.horses.cta'),
                'url' => '/app/horses/create',
            ];
        }

        // KSeF: brak certyfikatu w settings → karta. Tylko dla
        // canIssueInvoices() — horse_owner i tak nie ma tej strony.
        // PLUS gate na FINANCE_STAFF: vet/instructor/employee nie widzą
        // KSeF nawet jako suggestion na onboarding'u (canAccess
        // KsefSettings i tak ich zablokuje, ale link na dashboard'zie
        // wprowadzałby w błąd).
        if ($tenant->type?->canIssueInvoices() && app(TenantRoleGate::class)->allows(TenantRoleGate::FINANCE_STAFF)) {
            $ksefCert = data_get($tenant->settings, 'ksef.cert_metadata');
            if (empty($ksefCert)) {
                $slots[] = [
                    'key' => 'ksef',
                    'icon' => 'heroicon-o-shield-check',
                    'label' => (string) __('app/quick_start.ksef.label'),
                    'body' => (string) __('app/quick_start.ksef.body'),
                    'cta' => (string) __('app/quick_start.ksef.cta'),
                    'url' => '/app/ksef-settings',
                ];
            }
        }

        return $slots;
    }
}
