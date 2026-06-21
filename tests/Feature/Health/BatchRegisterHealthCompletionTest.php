<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Enums\HealthRecordType;
use App\Models\Central\Tenant;
use App\Models\Tenant\HealthRecord;
use App\Services\Health\BatchRegisterHealthCompletion;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * PR D — Batch-register-completion. Pokrywa service'em który tworzy
 * follow-up HealthRecord wpisy z wspólnego formularza (vet szczepi 8 koni).
 */
class BatchRegisterHealthCompletionTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hov_bulk_health_').'.sqlite';
        touch($this->tenantDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->bootTenantContext();

        $this->mock(TenantAuditLogger::class, function (MockInterface $m): void {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_creates_one_followup_per_original(): void
    {
        $originals = collect([
            $this->seedHealthRecord(type: HealthRecordType::Vaccination, performedAt: now()->subYear()),
            $this->seedHealthRecord(type: HealthRecordType::Vaccination, performedAt: now()->subYear()),
            $this->seedHealthRecord(type: HealthRecordType::Vaccination, performedAt: now()->subYear()),
        ]);

        $service = app(BatchRegisterHealthCompletion::class);
        $result = $service->execute($originals, [
            'performed_at' => now(),
            'summary' => 'Szczepienie tężec — sezon 2026',
            'specialist_id' => null,
            'performed_by' => 'Dr. Kowalski',
            'next_due_at' => now()->addYear()->toDateString(),
            'cost_cents' => 8000,
        ]);

        $this->assertSame(3, $result['created_count']);
        $this->assertCount(3, $result['created_ids']);
        $this->assertSame(6, HealthRecord::query()->count()); // 3 oryginalne + 3 follow-up
    }

    public function test_followup_copies_type_and_horse_from_original(): void
    {
        $original = $this->seedHealthRecord(
            type: HealthRecordType::Farrier,
            horseId: '01HHORSE0000000000000ABC1',
        );

        $service = app(BatchRegisterHealthCompletion::class);
        $service->execute(collect([$original]), [
            'performed_at' => now(),
            'summary' => 'Podkucie',
        ]);

        $newRecord = HealthRecord::query()->latest('id')->first();
        $this->assertSame('01HHORSE0000000000000ABC1', $newRecord->horse_id);
        $this->assertSame(HealthRecordType::Farrier, $newRecord->type);
        $this->assertSame('Podkucie', $newRecord->summary);
    }

    public function test_audit_logger_called_once_per_created_record(): void
    {
        $this->mock(TenantAuditLogger::class, function (MockInterface $m): void {
            $m->shouldReceive('record')
                ->twice()
                ->with('health.batch_completed', 'HealthRecord', \Mockery::any(), \Mockery::any());
        });

        $originals = collect([
            $this->seedHealthRecord(),
            $this->seedHealthRecord(),
        ]);

        app(BatchRegisterHealthCompletion::class)->execute($originals, [
            'performed_at' => now(),
            'summary' => 'Test',
        ]);

        // Mockery expectations sprawdzą automatycznie przy tearDown — brak
        // jawnego assert tu. Dodajemy expects=true żeby PHPUnit nie marudził.
        $this->assertTrue(true);
    }

    public function test_transaction_rollback_on_invalid_data(): void
    {
        $original = $this->seedHealthRecord();
        $beforeCount = HealthRecord::query()->count();

        $service = app(BatchRegisterHealthCompletion::class);

        try {
            // Pusty summary spowoduje invalid data — DB constraint na NOT NULL
            // może nie złapać w SQLite, ale Mock validation by tested by service.
            // Tu testujemy assumption transakcji — jeśli throw'niemy w środku
            // pętli, nic nie zostaje. Symulujemy throw'em.
            $service->execute($original ? collect([$original, null]) : collect(), [
                'performed_at' => now(),
                'summary' => 'X',
            ]);
        } catch (\Throwable) {
            // expected — null jako original spowoduje błąd
        }

        // Original zostaje, ale żadnego follow-up'a NIE ma (rollback).
        $this->assertSame($beforeCount, HealthRecord::query()->count());
    }

    public function test_next_due_at_optional(): void
    {
        $original = $this->seedHealthRecord();

        app(BatchRegisterHealthCompletion::class)->execute(collect([$original]), [
            'performed_at' => now(),
            'summary' => 'Bez next due',
            // next_due_at intentionally omitted
        ]);

        $newRecord = HealthRecord::query()->latest('id')->first();
        $this->assertNull($newRecord->next_due_at);
    }

    public function test_cost_optional(): void
    {
        $original = $this->seedHealthRecord();

        app(BatchRegisterHealthCompletion::class)->execute(collect([$original]), [
            'performed_at' => now(),
            'summary' => 'Bez kosztu',
        ]);

        $newRecord = HealthRecord::query()->latest('id')->first();
        $this->assertNull($newRecord->cost_cents);
    }

    private function seedHealthRecord(
        HealthRecordType $type = HealthRecordType::Vaccination,
        ?string $horseId = null,
        ?\DateTimeInterface $performedAt = null,
    ): HealthRecord {
        return HealthRecord::create([
            'horse_id' => $horseId ?? '01HHORSE000000000000DEF1',
            'type' => $type->value,
            'performed_at' => $performedAt ?? now()->subMonths(11),
            'summary' => 'Original entry',
            'next_due_at' => now()->addDays(7)->toDateString(),
        ]);
    }

    private function bootTenantContext(): void
    {
        $tenant = new Tenant([
            'slug' => 'bulk-health-test',
            'name' => 'Bulk Health Test',
            'db_name' => 'irrelevant',
            'db_username' => 'irrelevant',
            'status' => 'active',
        ]);
        $tenant->db_password = 'x';
        $tenant->save();

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);
    }

    private function setUpTenantTables(): void
    {
        // Minimalne horses — HealthRecordObserver loaduje relation,
        // bez central_horse_id silent skip'uje notify dispatcher.
        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('central_horse_id', 26)->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::connection('tenant')->create('health_records', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->dateTime('performed_at');
            $t->string('performed_by', 255)->nullable();
            $t->string('specialist_id', 26)->nullable();
            $t->string('summary', 255);
            $t->text('details')->nullable();
            $t->date('next_due_at')->nullable();
            $t->unsignedInteger('cost_cents')->nullable();
            $t->json('attachments')->nullable();
            $t->json('metadata')->nullable();
            $t->string('created_by_central_user_id', 26)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        // Seed jednego horse'a (central_horse_id NULL → observer skip)
        DB::connection('tenant')->table('horses')->insert([
            'id' => '01HHORSE000000000000DEF1',
            'name' => 'Trojka',
            'central_horse_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('tenant')->table('horses')->insert([
            'id' => '01HHORSE0000000000000ABC1',
            'name' => 'Bury',
            'central_horse_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
