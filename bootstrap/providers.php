<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\Filament\OwnerPanelProvider;
use App\Providers\Filament\TransportPanelProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    AppPanelProvider::class,
    TransportPanelProvider::class,
    OwnerPanelProvider::class,
    TenancyServiceProvider::class,
];
