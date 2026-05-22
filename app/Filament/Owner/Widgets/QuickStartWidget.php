<?php

declare(strict_types=1);

namespace App\Filament\Owner\Widgets;

use App\Models\Central\Tenant;
use App\Models\Tenant\Horse;
use App\Tenancy\TenantManager;
use Filament\Widgets\Widget;
use Throwable;

/**
 * Empty-state CTA card na dashboard'zie horse owner — przede wszystkim
 * "Dodaj pierwszego konia". Patrz docstring App\Filament\App\Widgets\QuickStartWidget.
 */
class QuickStartWidget extends Widget
{
    protected static ?int $sort = -7;

    protected static string $view = 'filament.owner.widgets.quick-start';

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
            $horsesCount = Horse::query()->count();
        } catch (Throwable) {
            $horsesCount = 1;
        }
        if ($horsesCount === 0) {
            $slots[] = [
                'key' => 'horses',
                'icon' => 'heroicon-o-bolt',
                'label' => (string) __('owner/quick_start.horse.label'),
                'body' => (string) __('owner/quick_start.horse.body'),
                'cta' => (string) __('owner/quick_start.horse.cta'),
                'url' => '/owner/horses/create',
            ];
            // Po dodaniu pierwszego konia rozszerzymy o "zamow transport"
            // jako naturalny next step — na razie tylko gdy 0 koni.
            $slots[] = [
                'key' => 'transport',
                'icon' => 'heroicon-o-truck',
                'label' => (string) __('owner/quick_start.transport.label'),
                'body' => (string) __('owner/quick_start.transport.body'),
                'cta' => (string) __('owner/quick_start.transport.cta'),
                'url' => '/owner/order-transport',
            ];
        }

        return $slots;
    }
}
