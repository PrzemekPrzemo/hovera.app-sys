<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Tenant;
use App\Models\Tenant\AuditLog;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises TenantAuditLogger using SQLite as the tenant DB. We point
 * the `tenant` connection at a temp-file SQLite (not `:memory:` because
 * that's per-PDO and would not survive a purge between operations).
 */
class TenantAuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_tenant_').'.sqlite';
        touch($this->tenantDbPath);
        $this->configureTenantSqlite();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_no_op_when_no_tenant_is_active(): void
    {
        // No tenant booted on the manager.
        app(TenantAuditLogger::class)->record('test.action');

        $this->assertSame(0, AuditLog::on('tenant')->count());
    }

    public function test_writes_audit_log_when_tenant_active(): void
    {
        $this->bootTenantContext();
        $this->bindRequestWithSession();

        app(TenantAuditLogger::class)->record(
            'horse.create',
            'Horse',
            '01H123',
            ['name' => 'Bucefał'],
        );

        $row = AuditLog::on('tenant')->firstOrFail();
        $this->assertSame('horse.create', $row->action);
        $this->assertSame('Horse', $row->target_type);
        $this->assertSame('01H123', $row->target_id);
        $this->assertSame(['name' => 'Bucefał'], $row->payload);
        $this->assertFalse($row->via_impersonation);
    }

    public function test_marks_via_impersonation_when_session_flag_set(): void
    {
        $this->bootTenantContext();
        $this->bindRequestWithSession([
            'impersonation_session_id' => '01HIMP00000000000000000000',
        ]);

        app(TenantAuditLogger::class)->record('horse.update', 'Horse', '01H1');

        $row = AuditLog::on('tenant')->firstOrFail();
        $this->assertTrue($row->via_impersonation);
        $this->assertSame('01HIMP00000000000000000000', $row->impersonation_session_id);
    }

    /**
     * Bind a real Request with a session into the container so that
     * services calling `app('request')->session()` see our test data.
     */
    private function bindRequestWithSession(array $data = []): void
    {
        $session = $this->app['session']->driver();
        foreach ($data as $k => $v) {
            $session->put($k, $v);
        }
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
    }

    private function configureTenantSqlite(): void
    {
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->dropIfExists('audit_log');
        Schema::connection('tenant')->create('audit_log', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('actor_central_user_id', 26)->nullable();
            $t->string('action', 128);
            $t->string('target_type', 64)->nullable();
            $t->string('target_id', 64)->nullable();
            $t->json('payload')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->boolean('via_impersonation')->default(false);
            $t->string('impersonation_session_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    private function bootTenantContext(): Tenant
    {
        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        // Mark a tenant as active without reconfiguring the connection
        // (we already pointed it at our SQLite file in setUp).
        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);

        return $tenant;
    }
}
