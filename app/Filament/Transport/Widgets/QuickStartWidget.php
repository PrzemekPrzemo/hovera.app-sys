<?php

declare(strict_types=1);

namespace App\Filament\Transport\Widgets;

use App\Models\Central\Tenant;
use App\Models\Tenant\Driver;
use App\Models\Tenant\Vehicle;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;
use Throwable;

/**
 * Empty-state CTA card na dashboard'zie transporter — pojazdy / kierowcy /
 * KSeF. Patrz docstring App\Filament\App\Widgets\QuickStartWidget.
 */
class QuickStartWidget extends Widget
{
    protected static ?int $sort = -7;

    protected static string $view = 'filament.transport.widgets.quick-start';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $tenant = app(TenantManager::class)->current();
        if ($tenant === null) {
            return false;
        }

        return self::buildSlots($tenant) !== [];
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
            $vehiclesCount = Vehicle::query()->count();
        } catch (Throwable) {
            $vehiclesCount = 1;
        }
        if ($vehiclesCount === 0) {
            $slots[] = [
                'key' => 'vehicles',
                'icon' => 'heroicon-o-truck',
                'label' => (string) __('transport/quick_start.vehicles.label'),
                'body' => (string) __('transport/quick_start.vehicles.body'),
                'cta' => (string) __('transport/quick_start.vehicles.cta'),
                'url' => '/transport/vehicles/create',
            ];
        }

        try {
            $driversCount = Driver::query()->count();
        } catch (Throwable) {
            $driversCount = 1;
        }
        if ($driversCount === 0) {
            $slots[] = [
                'key' => 'drivers',
                'icon' => 'heroicon-o-identification',
                'label' => (string) __('transport/quick_start.drivers.label'),
                'body' => (string) __('transport/quick_start.drivers.body'),
                'cta' => (string) __('transport/quick_start.drivers.cta'),
                'url' => '/transport/drivers/create',
            ];
        }

        // KSeF: brak certyfikatu w settings → karta.
        $ksefCert = data_get($tenant->settings, 'ksef.cert_metadata');
        if (empty($ksefCert)) {
            $slots[] = [
                'key' => 'ksef',
                'icon' => 'heroicon-o-shield-check',
                'label' => (string) __('transport/quick_start.ksef.label'),
                'body' => (string) __('transport/quick_start.ksef.body'),
                'cta' => (string) __('transport/quick_start.ksef.cta'),
                'url' => '/transport/transport-settings',
            ];
        }

        return $slots;
    }
}
