<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Smoke test dla `tenant:export`. Tworzy sqlite tenant DB z paroma
 * tabelami, seedu 2 klientów, uruchamia komendę. Weryfikuje:
 *  - dir wyjściowy powstał
 *  - _manifest.json zawiera tenant_id + total_rows
 *  - tenant.json zawiera central row
 *  - clients.json zawiera 2 seedy
 *
 * Modele bez tabel (większość — IndexDocument, Horse itp.) lecą do
 * soft-failed warning'ów. To intencjonalne — komenda nie crashuje
 * gdy tenant DB jest niekompletna (np. po częściowej migracji).
 */
class TenantExportCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private string $outDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_export_').'.sqlite';
        touch($this->tenantDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('clients', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        $this->outDir = sys_get_temp_dir().'/hovera_export_out_'.uniqid();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        if (is_dir($this->outDir)) {
            File::deleteDirectory($this->outDir);
        }
        parent::tearDown();
    }

    public function test_export_writes_manifest_and_per_table_json(): void
    {
        $tenant = $this->makeTenant();
        $this->seedTwoClients();

        $this->artisan('tenant:export', ['ulid' => $tenant->id, '--out' => $this->outDir])
            ->expectsOutputToContain('Exporting tenant')
            ->assertExitCode(0);

        $dirs = glob($this->outDir.'/tenant-'.$tenant->slug.'-*');
        $this->assertNotEmpty($dirs, 'Output directory was not created.');
        $dir = $dirs[0];

        $this->assertFileExists($dir.'/_manifest.json');
        $this->assertFileExists($dir.'/tenant.json');
        $this->assertFileExists($dir.'/clients.json');

        $manifest = json_decode((string) file_get_contents($dir.'/_manifest.json'), true);
        $this->assertSame($tenant->id, $manifest['tenant_id']);
        $this->assertSame($tenant->slug, $manifest['tenant_slug']);
        $this->assertArrayHasKey('total_rows', $manifest);
        $this->assertGreaterThanOrEqual(2, $manifest['total_rows']);

        $clients = json_decode((string) file_get_contents($dir.'/clients.json'), true);
        $this->assertCount(2, $clients);
        $this->assertSame('Anna Kowalska', $clients[0]['name']);

        $central = json_decode((string) file_get_contents($dir.'/tenant.json'), true);
        $this->assertSame($tenant->id, $central['id']);
        $this->assertSame($tenant->slug, $central['slug']);
    }

    public function test_export_fails_when_tenant_not_found(): void
    {
        $this->artisan('tenant:export', ['ulid' => '01HNONEXIST00000000000000A', '--out' => $this->outDir])
            ->expectsOutputToContain('not found in central DB')
            ->assertExitCode(1);
    }

    private function makeTenant(): Tenant
    {
        $tenant = new Tenant([
            'slug' => 'export-test',
            'name' => 'Export Test',
            'db_name' => 'irrelevant',
            'db_username' => 'irrelevant',
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);

        return $tenant;
    }

    private function seedTwoClients(): void
    {
        \DB::connection('tenant')->table('clients')->insert([
            ['id' => '01HEXPCLIENT0000000000001A', 'name' => 'Anna Kowalska', 'email' => 'anna@test.pl'],
            ['id' => '01HEXPCLIENT0000000000002B', 'name' => 'Bartek Nowak', 'email' => 'bartek@test.pl'],
        ]);
    }
}
