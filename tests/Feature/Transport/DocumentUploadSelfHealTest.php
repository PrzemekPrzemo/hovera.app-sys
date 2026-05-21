<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Verification\DocumentUploadService;
use Illuminate\Database\QueryException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pokrywa self-heal w DocumentUploadService — gdy tenant DB nie ma
 * `transporter_documents` table (luka migracji), upload łapie QueryException,
 * próbuje raz odpalić tenant migrations, retry assertSchemaReady. Bez
 * tego owner widział "0 z 6 dokumentów zapisanych" bez naprawy.
 *
 * Test unit-level — sprawdzamy detektor missing table. Real backfill
 * (Artisan::call migrate) testowany manualnie post-deploy.
 */
class DocumentUploadSelfHealTest extends TestCase
{
    public function test_is_missing_table_detects_mysql_1146(): void
    {
        $service = $this->app->make(DocumentUploadService::class);
        $method = new ReflectionMethod($service, 'isMissingTableError');
        $method->setAccessible(true);

        $e = new QueryException(
            'tenant',
            'select * from `transporter_documents`',
            [],
            new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hovera_t_x.transporter_documents' doesn't exist"),
        );

        $this->assertTrue($method->invoke($service, $e));
    }

    public function test_is_missing_table_detects_sqlite(): void
    {
        $service = $this->app->make(DocumentUploadService::class);
        $method = new ReflectionMethod($service, 'isMissingTableError');
        $method->setAccessible(true);

        $e = new QueryException(
            'tenant',
            'select * from "transporter_documents"',
            [],
            new \PDOException('SQLSTATE[HY000]: General error: 1 no such table: transporter_documents'),
        );

        $this->assertTrue($method->invoke($service, $e));
    }

    public function test_is_missing_table_rejects_other_errors(): void
    {
        $service = $this->app->make(DocumentUploadService::class);
        $method = new ReflectionMethod($service, 'isMissingTableError');
        $method->setAccessible(true);

        $e = new QueryException(
            'tenant',
            'insert into `transporter_documents`',
            [],
            new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row'),
        );

        $this->assertFalse($method->invoke($service, $e));
    }
}
