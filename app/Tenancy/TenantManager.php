<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Config;

/**
 * Holds the current tenant for the request and reconfigures the
 * `tenant` DB connection accordingly. Singleton.
 *
 * The application code is *never* allowed to talk to a tenant DB
 * by selecting `mysql` / `central` / etc. — it only ever talks to
 * the `tenant` connection, which this class wires up.
 */
class TenantManager
{
    private ?Tenant $current = null;

    public function __construct(private readonly DatabaseManager $db) {}

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function hasTenant(): bool
    {
        return $this->current !== null;
    }

    public function tenantOrFail(): Tenant
    {
        if ($this->current === null) {
            throw new \RuntimeException('No tenant initialised for this request.');
        }

        return $this->current;
    }

    /**
     * Activate a tenant for the rest of the request. Reconfigures the
     * `tenant` connection and purges any previous PDO from the pool.
     */
    public function setCurrent(Tenant $tenant): void
    {
        $this->current = $tenant;
        $this->configureTenantConnection($tenant);
    }

    /**
     * Forget the active tenant — drop the cached PDO so the next request
     * (or the next iteration of a queue worker) starts clean. We purge
     * the connection but leave the config intact: rebuilding it on the
     * next setCurrent() is the responsibility of the caller. Wiping the
     * config here would also break tests / artisan tasks that reuse the
     * connection name with a different driver.
     */
    public function forget(): void
    {
        $this->db->purge('tenant');
        $this->current = null;
    }

    /**
     * Run a callback in the context of the given tenant, then restore
     * the previous tenant (or unset). Useful for jobs / artisan commands
     * that iterate across tenants.
     */
    public function execute(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->current;
        try {
            $this->setCurrent($tenant);

            return $callback($tenant);
        } finally {
            if ($previous !== null) {
                $this->setCurrent($previous);
            } else {
                $this->forget();
            }
        }
    }

    private function configureTenantConnection(Tenant $tenant): void
    {
        $config = $tenant->databaseConnectionConfig();

        Config::set('database.connections.tenant', $config);

        // Drop any cached PDO so the next query rebuilds the connection
        // with the new credentials. Critical when switching tenants
        // mid-request (impersonation, jobs).
        $this->db->purge('tenant');
    }
}
