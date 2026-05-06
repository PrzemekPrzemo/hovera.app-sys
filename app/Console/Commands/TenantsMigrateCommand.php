<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantsMigrateCommand extends Command
{
    protected $signature = 'tenants:migrate
        {--tenant= : Slug of a single tenant to migrate (default: all active)}
        {--fresh : Drop all tables first (DANGER — never use in production)}';

    protected $description = 'Run tenant database migrations against one or all active tenants.';

    public function handle(TenantManager $tenants): int
    {
        $query = Tenant::query();

        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        } else {
            $query->whereIn('status', ['trialing', 'active', 'past_due', 'provisioning']);
        }

        $list = $query->get();

        if ($list->isEmpty()) {
            $this->warn('No matching tenants.');
            return self::SUCCESS;
        }

        if ($this->option('fresh') && $this->getLaravel()->isProduction()) {
            $this->error('--fresh is forbidden in production.');
            return self::FAILURE;
        }

        foreach ($list as $tenant) {
            $this->info("→ {$tenant->slug} ({$tenant->db_name})");
            $tenants->execute($tenant, function () {
                Artisan::call($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
                    '--database' => 'tenant',
                    '--path'     => 'database/migrations/tenant',
                    '--realpath' => false,
                    '--force'    => true,
                ], $this->getOutput());
            });
        }

        return self::SUCCESS;
    }
}
