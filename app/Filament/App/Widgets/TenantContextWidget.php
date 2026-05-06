<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets;

use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Tenancy\TenantManager;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantContextWidget extends BaseWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $tenant = app(TenantManager::class)->current();

        if (! $tenant) {
            return [];
        }

        $horses = Horse::query()->count();
        $clients = Client::query()->count();
        $planName = $tenant->plan?->name ?? '—';

        return [
            Stat::make('Stajnia', $tenant->name)
                ->description($tenant->slug.' · plan: '.$planName)
                ->color('primary'),
            Stat::make('Konie', $horses),
            Stat::make('Klienci', $clients),
        ];
    }
}
