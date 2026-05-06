<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\CreateTenant;
use Illuminate\Console\Command;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
        {slug : URL slug — used in routes and DB name}
        {name : Display name of the stable}
        {--country=PL}
        {--locale=pl}
        {--timezone=Europe/Warsaw}
        {--currency=PLN}
        {--plan=free}
        {--owner-email= : If set, creates / attaches a user as owner}
        {--owner-name=}';

    protected $description = 'Provision a new tenant (CLI helper, mirrors what /admin Filament does).';

    public function handle(CreateTenant $action): int
    {
        $tenant = $action->execute([
            'slug' => (string) $this->argument('slug'),
            'name' => (string) $this->argument('name'),
            'country' => $this->option('country'),
            'locale' => $this->option('locale'),
            'timezone' => $this->option('timezone'),
            'currency' => $this->option('currency'),
            'plan_code' => $this->option('plan'),
            'owner_email' => $this->option('owner-email'),
            'owner_name' => $this->option('owner-name'),
        ]);

        $this->info("Tenant '{$tenant->slug}' created.");
        $this->line("  DB:   {$tenant->db_name}");
        $this->line("  user: {$tenant->db_username}");

        return self::SUCCESS;
    }
}
