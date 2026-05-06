<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenancy\Provisioner;
use App\Tenancy\TenantManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager($app->make(DatabaseManager::class));
        });

        $this->app->singleton(Provisioner::class, function ($app) {
            return new Provisioner(
                $app->make(TenantManager::class),
                $app->make(DatabaseManager::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
