<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates and tears down the physical isolation per tenant:
 *   - dedicated MySQL database
 *   - dedicated MySQL user with grants only to that database
 *   - tenant-specific migrations executed
 *
 * Uses the `provisioner` DB connection, which is the only connection
 * authorised to run CREATE/DROP DATABASE and CREATE USER.
 */
class Provisioner
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly DatabaseManager $db,
    ) {}

    public function provision(Tenant $tenant): void
    {
        $this->createDatabase($tenant);
        $this->createDatabaseUser($tenant);
        $this->grantPrivileges($tenant);
        $this->runTenantMigrations($tenant);

        Log::info('Tenant provisioned', [
            'tenant_id' => $tenant->id,
            'db_name' => $tenant->db_name,
        ]);
    }

    /**
     * Hard-delete a tenant's database + MySQL user. Irreversible.
     * Should only be called after the soft-delete grace period.
     */
    public function destroy(Tenant $tenant): void
    {
        $conn = $this->provisionerConnection();
        $dbName = $this->quoteIdentifier($tenant->db_name);
        $username = $this->quoteString($tenant->db_username);
        $host = $this->quoteString('%');

        $this->tenants->forget();

        $conn->statement("DROP DATABASE IF EXISTS {$dbName}");
        $conn->statement("DROP USER IF EXISTS {$username}@{$host}");
        $conn->statement('FLUSH PRIVILEGES');

        Log::warning('Tenant destroyed', [
            'tenant_id' => $tenant->id,
            'db_name' => $tenant->db_name,
        ]);
    }

    /**
     * Generate a tenant DB name + username for a slug, applying the
     * configured prefix and a uniqueness check against the central DB.
     */
    public function makeIdentifiers(string $slug): array
    {
        $sanitised = Str::of($slug)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]/', '_')
            ->replace('-', '_')
            ->limit(40, '');

        $dbPrefix = config('hovera.tenant.db_prefix', 'hovera_t_');
        $userPrefix = config('hovera.tenant.user_prefix', 'hovera_t_');

        return [
            'db_name' => Str::limit($dbPrefix.$sanitised, 63, ''),
            'db_user' => Str::limit($userPrefix.$sanitised, 32, ''),
        ];
    }

    public function generatePassword(int $length = 32): string
    {
        return Str::password($length, symbols: false);
    }

    private function createDatabase(Tenant $tenant): void
    {
        $conn = $this->provisionerConnection();
        $dbName = $this->quoteIdentifier($tenant->db_name);

        $conn->statement(
            "CREATE DATABASE IF NOT EXISTS {$dbName} "
            .'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }

    private function createDatabaseUser(Tenant $tenant): void
    {
        $conn = $this->provisionerConnection();
        $username = $this->quoteString($tenant->db_username);
        $host = $this->quoteString('%');
        $password = $this->quoteString($tenant->db_password);

        $conn->statement(
            "CREATE USER IF NOT EXISTS {$username}@{$host} IDENTIFIED BY {$password}"
        );

        // If the user already existed (re-provisioning), reset its password.
        $conn->statement(
            "ALTER USER {$username}@{$host} IDENTIFIED BY {$password}"
        );
    }

    private function grantPrivileges(Tenant $tenant): void
    {
        $conn = $this->provisionerConnection();
        $dbName = $this->quoteIdentifier($tenant->db_name);
        $username = $this->quoteString($tenant->db_username);
        $host = $this->quoteString('%');

        $conn->statement(
            "GRANT ALL PRIVILEGES ON {$dbName}.* TO {$username}@{$host}"
        );
        $conn->statement('FLUSH PRIVILEGES');
    }

    private function runTenantMigrations(Tenant $tenant): void
    {
        $this->tenants->execute($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);
        });
    }

    private function provisionerConnection(): Connection
    {
        return $this->db->connection('provisioner');
    }

    /**
     * Quote a MySQL identifier (database, table, column).
     * Whitelist alphanumeric + underscore only — anything else is rejected
     * to prevent injection through this code path.
     */
    private function quoteIdentifier(string $identifier): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException("Invalid SQL identifier: {$identifier}");
        }

        return '`'.$identifier.'`';
    }

    /**
     * Quote a MySQL string literal. Using PDO::quote via the
     * provisioner connection's PDO instance.
     */
    private function quoteString(string $value): string
    {
        return $this->provisionerConnection()->getPdo()->quote($value);
    }
}
