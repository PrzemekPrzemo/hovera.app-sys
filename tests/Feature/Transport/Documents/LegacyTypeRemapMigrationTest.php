<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Documents;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Migration `2026_05_18_140000_remap_legacy_transporter_document_types` mapuje
 * rekordy z document_type='insurance_ocp' na 'carrier_liability_insurance'.
 *
 * Test odpalany ręcznie (uruchamia migrację na sqlite testowej DB).
 */
class LegacyTypeRemapMigrationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_remap_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpDocsTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_legacy_insurance_ocp_mapped_to_carrier_liability_insurance(): void
    {
        DB::connection('tenant')->table('transporter_documents')->insert([
            'id' => (string) Str::ulid(),
            'document_type' => 'insurance_ocp',
            'status' => 'verified',
            'file_path' => 'foo.pdf',
        ]);

        $this->runMigration();

        $rows = DB::connection('tenant')->table('transporter_documents')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('carrier_liability_insurance', $rows[0]->document_type);
    }

    public function test_duplicate_removed_when_new_value_already_exists(): void
    {
        // Stary insurance_ocp + nowy carrier_liability_insurance jednocześnie.
        // Reguła: usuwamy stary (zapisany prawdopodobnie wcześniej).
        DB::connection('tenant')->table('transporter_documents')->insert([
            [
                'id' => (string) Str::ulid(),
                'document_type' => 'insurance_ocp',
                'status' => 'rejected',
                'file_path' => 'old.pdf',
            ],
            [
                'id' => (string) Str::ulid(),
                'document_type' => 'carrier_liability_insurance',
                'status' => 'verified',
                'file_path' => 'new.pdf',
            ],
        ]);

        $this->runMigration();

        $rows = DB::connection('tenant')->table('transporter_documents')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('carrier_liability_insurance', $rows[0]->document_type);
        $this->assertSame('new.pdf', $rows[0]->file_path);
    }

    public function test_other_legacy_types_unchanged(): void
    {
        // animal_transport_cert / vehicle_registration zostają nietknięte (zachowane jako deprecated case).
        DB::connection('tenant')->table('transporter_documents')->insert([
            [
                'id' => (string) Str::ulid(),
                'document_type' => 'animal_transport_cert',
                'status' => 'verified',
                'file_path' => 'cert.pdf',
            ],
            [
                'id' => (string) Str::ulid(),
                'document_type' => 'vehicle_registration',
                'status' => 'verified',
                'file_path' => 'dr.pdf',
            ],
        ]);

        $this->runMigration();

        $values = DB::connection('tenant')->table('transporter_documents')
            ->pluck('document_type')->all();
        $this->assertContains('animal_transport_cert', $values);
        $this->assertContains('vehicle_registration', $values);
    }

    private function runMigration(): void
    {
        $migration = require database_path('migrations/tenant/2026_05_18_140000_remap_legacy_transporter_document_types.php');

        // Migration uses DB::table() (default connection). W teście włączamy
        // tenant jako default na czas.
        $original = config('database.default');
        config()->set('database.default', 'tenant');
        try {
            $migration->up();
        } finally {
            config()->set('database.default', $original);
        }
    }

    private function setUpDocsTable(): void
    {
        Schema::connection('tenant')->create('transporter_documents', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('document_type', 32);
            $t->string('status', 16)->default('pending');
            $t->string('file_path');
        });
    }
}
