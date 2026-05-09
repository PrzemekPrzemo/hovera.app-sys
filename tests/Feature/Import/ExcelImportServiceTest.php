<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Services\Import\ExcelImportService;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExcelImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_imp_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->tenant = $this->makeTenant();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_parses_csv_with_polish_headers(): void
    {
        $file = $this->makeCsv("Imię,Nazwisko,Email,Telefon\nAnna,Kowalska,anna@example.com,+48600100200\nJan,Nowak,jan@example.com,+48600300400\n");
        $svc = new ExcelImportService;

        $parsed = $svc->parseFile($file);

        $this->assertSame(['Imię', 'Nazwisko', 'Email', 'Telefon'], $parsed['headers']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertSame('Anna', $parsed['rows'][0][0]);
    }

    public function test_suggests_mapping_from_aliases(): void
    {
        $svc = new ExcelImportService;
        $mapping = $svc->suggestMapping(ExcelImportService::ENTITY_CLIENTS, ['Imię', 'Nazwisko', 'E-mail', 'Tel']);

        $this->assertSame('Imię', $mapping['first_name']);
        $this->assertSame('Nazwisko', $mapping['last_name']);
        $this->assertSame('E-mail', $mapping['email']);
        $this->assertSame('Tel', $mapping['phone']);
        $this->assertNull($mapping['city']);
    }

    public function test_validate_row_concatenates_first_last_into_name(): void
    {
        $svc = new ExcelImportService;
        $headers = ['Imię', 'Nazwisko', 'E-mail'];
        $row = ['Anna', 'Kowalska', 'anna@example.com'];
        $mapping = ['first_name' => 'Imię', 'last_name' => 'Nazwisko', 'email' => 'E-mail'];

        $r = $svc->validateRow(ExcelImportService::ENTITY_CLIENTS, $mapping, $row, $headers);

        $this->assertTrue($r['ok']);
        $this->assertSame('Anna Kowalska', $r['data']['name']);
        $this->assertSame('anna@example.com', $r['data']['email']);
    }

    public function test_validate_row_rejects_invalid_email(): void
    {
        $svc = new ExcelImportService;
        $r = $svc->validateRow(
            ExcelImportService::ENTITY_CLIENTS,
            ['first_name' => 'A', 'last_name' => 'B', 'email' => 'C'],
            ['Anna', 'Kowalska', 'not-an-email'],
            ['A', 'B', 'C']
        );
        $this->assertFalse($r['ok']);
        $this->assertNotEmpty($r['errors']);
    }

    public function test_imports_clients_and_skips_duplicate_email(): void
    {
        $file = $this->makeCsv("Imię,Nazwisko,Email\nAnna,Kowalska,anna@example.com\nJan,Nowak,jan@example.com\nDuplicate,Anna,anna@example.com\n");
        $svc = new ExcelImportService;
        $parsed = $svc->parseFile($file);
        $mapping = $svc->suggestMapping(ExcelImportService::ENTITY_CLIENTS, $parsed['headers']);

        $result = $svc->import(ExcelImportService::ENTITY_CLIENTS, $mapping, $parsed['rows'], $parsed['headers']);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(2, Client::query()->count());
    }

    public function test_imports_horses_with_owner_lookup_by_email(): void
    {
        $owner = Client::create([
            'name' => 'Anna Kowalska',
            'email' => 'anna@example.com',
            'type' => 'individual',
        ]);

        $file = $this->makeCsv("Imię konia,Rasa,Płeć,Email właściciela\nBursztyn,Polski,wałach,anna@example.com\n");
        $svc = new ExcelImportService;
        $parsed = $svc->parseFile($file);
        $mapping = $svc->suggestMapping(ExcelImportService::ENTITY_HORSES, $parsed['headers']);

        $result = $svc->import(ExcelImportService::ENTITY_HORSES, $mapping, $parsed['rows'], $parsed['headers']);

        $this->assertSame(1, $result['imported']);
        $horse = Horse::query()->first();
        $this->assertSame('Bursztyn', $horse->name);
        $this->assertSame('gelding', $horse->sex);
        $this->assertSame($owner->id, $horse->owner_client_id);
    }

    public function test_imports_horses_with_missing_owner_logs_warning_but_proceeds(): void
    {
        $file = $this->makeCsv("Imię konia,Email właściciela\nKometa,unknown@example.com\n");
        $svc = new ExcelImportService;
        $parsed = $svc->parseFile($file);
        $mapping = $svc->suggestMapping(ExcelImportService::ENTITY_HORSES, $parsed['headers']);

        $result = $svc->import(ExcelImportService::ENTITY_HORSES, $mapping, $parsed['rows'], $parsed['headers']);

        $this->assertSame(1, $result['imported']);
        $this->assertNotEmpty($result['errors']); // warning logged
        $horse = Horse::query()->first();
        $this->assertNull($horse->owner_client_id);
    }

    private function makeCsv(string $contents): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv_').'.csv';
        file_put_contents($tmp, $contents);

        return new UploadedFile($tmp, 'data.csv', 'text/csv', null, true);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'imp-'.$u,
            'name' => 'Import Stable',
            'db_name' => 'imp_'.$u,
            'db_username' => 'imp_'.substr($u, -8),
            'status' => 'active',
            'settings' => [],
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('clients', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('street')->nullable();
            $t->string('postal_code', 20)->nullable();
            $t->string('city', 120)->nullable();
            $t->char('country', 2)->default('PL');
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horses', function ($t): void {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('ueln', 15)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 32)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('owner_client_id', 26)->nullable();
            $t->string('box_id', 26)->nullable();
            $t->string('cover_image_path')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
