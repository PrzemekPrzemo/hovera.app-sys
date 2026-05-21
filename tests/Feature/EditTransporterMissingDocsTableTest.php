<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Admin\Resources\TransporterResource\Pages\EditTransporter;
use Illuminate\Database\QueryException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pokrywa defensive handling of missing `transporter_documents` table w
 * tenant DB (regression: 500 crash gdy tenant sprovisionowany przed
 * wprowadzeniem dokumentów weryfikacyjnych).
 *
 * Testujemy detector — czy `isMissingTableError()` rozpoznaje typowe
 * komunikaty drivera DB. Live integration (TenantManager switch + Eloquent
 * query) jest pokrywany ręcznie po deployu — tu unit-level scope.
 */
class EditTransporterMissingDocsTableTest extends TestCase
{
    public function test_detects_mysql_missing_table_error(): void
    {
        $exception = new QueryException(
            'tenant',
            'select * from `transporter_documents`',
            [],
            new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'hovera_t_x.transporter_documents' doesn't exist"),
        );

        $this->assertTrue($this->invokeIsMissingTableError($exception));
    }

    public function test_detects_sqlite_missing_table_error(): void
    {
        $exception = new QueryException(
            'tenant',
            'select * from "transporter_documents"',
            [],
            new \PDOException('SQLSTATE[HY000]: General error: 1 no such table: transporter_documents'),
        );

        $this->assertTrue($this->invokeIsMissingTableError($exception));
    }

    public function test_detects_postgres_missing_table_error(): void
    {
        $exception = new QueryException(
            'tenant',
            'select * from "transporter_documents"',
            [],
            new \PDOException('SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "transporter_documents" does not exist'),
        );

        $this->assertTrue($this->invokeIsMissingTableError($exception));
    }

    public function test_does_not_match_other_query_errors(): void
    {
        $exception = new QueryException(
            'tenant',
            'select * from `transporter_documents`',
            [],
            new \PDOException('SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row'),
        );

        $this->assertFalse($this->invokeIsMissingTableError($exception));
    }

    private function invokeIsMissingTableError(QueryException $e): bool
    {
        $method = new ReflectionMethod(EditTransporter::class, 'isMissingTableError');
        $method->setAccessible(true);

        // Method jest instance, ale nie używa $this state — możemy go wywołać
        // na newInstanceWithoutConstructor (EditRecord ma side-effect constructor).
        $page = (new \ReflectionClass(EditTransporter::class))->newInstanceWithoutConstructor();

        return (bool) $method->invoke($page, $e);
    }
}
