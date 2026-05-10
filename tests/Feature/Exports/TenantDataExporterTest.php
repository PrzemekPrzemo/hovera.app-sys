<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use App\Models\Tenant\Horse;
use App\Services\Exports\TenantDataExporter;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * Smoke test eksportu — postawiona stajnia z 1 koniem + 1 klientem,
 * uruchamiamy exporter, sprawdzamy zawartość ZIP. Pełen integration
 * (calendar.ics z wieloma typami) jest poza scope tej PR-ki.
 */
class TenantDataExporterTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_exp_').'.sqlite';
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

    public function test_export_produces_zip_with_expected_entries(): void
    {
        Client::create([
            'id' => '01HCLI0000000000000000001',
            'name' => 'Anna Kowalska',
            'email' => 'anna@example.com',
            'phone' => '+48 600 100 200',
            'street' => 'ul. Kwiatowa 5',
            'city' => 'Warszawa',
            'postal_code' => '00-001',
            'country' => 'PL',
        ]);
        Horse::create([
            'id' => '01HHOR0000000000000000001',
            'name' => 'Bystry',
            'breed' => 'malopolski',
            'sex' => 'gelding',
            'color' => 'gniada',
            'birth_date' => '2018-04-10',
        ]);

        /** @var TenantDataExporter $exporter */
        $exporter = $this->app->make(TenantDataExporter::class);
        $path = $exporter->export($this->tenant);

        $this->assertFileExists($path);

        $zip = new ZipArchive;
        $this->assertSame(true, $zip->open($path) === true);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        sort($entries);

        $this->assertContains('clients.csv', $entries);
        $this->assertContains('horses.csv', $entries);
        $this->assertContains('calendar.ics', $entries);
        $this->assertContains('invoices.csv', $entries);
        $this->assertContains('meta.json', $entries);

        $meta = json_decode((string) $zip->getFromName('meta.json'), true);
        $this->assertSame($this->tenant->slug, $meta['tenant_slug']);
        $this->assertSame(1, $meta['export_format_version']);

        $clientsCsv = (string) $zip->getFromName('clients.csv');
        $this->assertStringContainsString('Anna Kowalska', $clientsCsv);

        $horsesCsv = (string) $zip->getFromName('horses.csv');
        $this->assertStringContainsString('Bystry', $horsesCsv);

        $ics = (string) $zip->getFromName('calendar.ics');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);

        $zip->close();
        @unlink($path);
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = new Tenant([
            'slug' => 'exp-'.$u,
            'name' => 'Export Stable',
            'db_name' => 'exp_'.$u,
            'db_username' => 'exp_'.substr($u, -8),
            'status' => 'trialing',
            'settings' => [],
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        // Ustawiamy current żeby execute() restore'ował na tę samą wartość
        // i nie tracił connection (sqlite in-memory).
        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('clients', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type')->default('individual');
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('tax_id', 32)->nullable();
            $t->string('armir_producer_id')->nullable();
            $t->string('pesel')->nullable();
            $t->string('street')->nullable();
            $t->string('postal_code', 20)->nullable();
            $t->string('city', 120)->nullable();
            $t->char('country', 2)->default('PL');
            $t->timestamp('rodo_consent_at')->nullable();
            $t->string('rodo_consent_source')->nullable();
            $t->string('central_user_id')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->string('magic_link_token_hash')->nullable();
            $t->timestamp('magic_link_expires_at')->nullable();
            $t->timestamp('last_logged_in_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('horses', function ($t) {
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

        Schema::connection('tenant')->create('calendar_entries', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('type', 32);
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('horse_id', 26)->nullable();
            $t->string('instructor_id', 26)->nullable();
            $t->string('arena_id', 26)->nullable();
            $t->string('client_id', 26)->nullable();
            $t->string('recurrence_id', 26)->nullable();
            $t->integer('recurrence_occurrence')->nullable();
            $t->string('status', 32)->default('confirmed');
            $t->string('title')->nullable();
            $t->text('notes')->nullable();
            $t->bigInteger('price_cents')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
            $t->string('created_by_central_user_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('invoices', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 64)->nullable();
            $t->string('kind', 32);
            $t->string('status', 32);
            $t->string('client_id', 26);
            $t->string('related_payment_id', 26)->nullable();
            $t->string('related_pass_id', 26)->nullable();
            $t->string('corrects_invoice_id', 26)->nullable();
            $t->string('seller_name')->nullable();
            $t->string('seller_nip', 16)->nullable();
            $t->string('seller_address')->nullable();
            $t->string('seller_postal_code', 16)->nullable();
            $t->string('seller_city', 120)->nullable();
            $t->char('seller_country', 2)->default('PL');
            $t->string('buyer_name')->nullable();
            $t->string('buyer_nip', 16)->nullable();
            $t->string('buyer_address')->nullable();
            $t->string('buyer_postal_code', 16)->nullable();
            $t->string('buyer_city', 120)->nullable();
            $t->char('buyer_country', 2)->default('PL');
            $t->date('issued_at')->nullable();
            $t->date('sale_date')->nullable();
            $t->date('due_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->char('currency', 3)->default('PLN');
            $t->bigInteger('subtotal_cents')->default(0);
            $t->bigInteger('vat_cents')->default(0);
            $t->bigInteger('total_cents')->default(0);
            $t->string('ksef_status', 32)->nullable();
            $t->string('ksef_reference', 191)->nullable();
            $t->timestamp('ksef_sent_at')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
