<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\HealthRecordType;
use App\Filament\App\Resources\HealthRecordResource;
use App\Models\Central\Tenant;
use App\Models\Tenant\HealthRecord;
use App\Models\Tenant\Horse;
use App\Services\Health\UpcomingHealthAlertsService;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HealthRecordsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Horse $horse;

    private Horse $otherHorse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_health_').'.sqlite';
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

        $this->horse = Horse::create([
            'id' => '01HHORSE000000000000000001',
            'name' => 'Bucefał',
        ]);
        $this->otherHorse = Horse::create([
            'id' => '01HHORSE000000000000000002',
            'name' => 'Pegaz',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_horse_identification_renderer_emits_microchip_passport_ueln(): void
    {
        // PR A — wet musi widzieć chip/paszport/UELN inline w form'ie HealthRecord,
        // żeby zweryfikować tożsamość konia przed zabiegiem.
        $this->horse->forceFill([
            'microchip' => '985121000123456',
            'passport_number' => 'PL00012345',
            'ueln' => '616-1234-5678901',
        ])->save();

        $html = (string) HealthRecordResource::renderHorseIdentificationFor($this->horse->id);

        $this->assertStringContainsString('985121000123456', $html);
        $this->assertStringContainsString('PL00012345', $html);
        $this->assertStringContainsString('616-1234-5678901', $html);
        $this->assertStringNotContainsString(__('app/health.form.horse_identification.empty_warning'), $html);
    }

    public function test_horse_identification_renderer_warns_when_all_fields_empty(): void
    {
        // Bucefał z setUp() nie ma chip/paszport/UELN → warning powinien się pojawić.
        $html = (string) HealthRecordResource::renderHorseIdentificationFor($this->horse->id);

        $this->assertStringContainsString(__('app/health.form.horse_identification.empty_warning'), $html);
    }

    public function test_horse_identification_renderer_empty_when_no_horse_selected(): void
    {
        $this->assertSame(
            '',
            (string) HealthRecordResource::renderHorseIdentificationFor(null)
        );
    }

    public function test_horse_identification_renderer_handles_missing_horse(): void
    {
        // Stale form state — koń usunięty po wyborze. Pokazujemy informację
        // zamiast renderować pustki / crashować.
        $html = (string) HealthRecordResource::renderHorseIdentificationFor('01HXXX000000000000000000NX');

        $this->assertStringContainsString(__('app/health.form.horse_identification.missing'), $html);
    }

    public function test_default_follow_up_months_for_each_type(): void
    {
        $this->assertSame(12, HealthRecordType::Vaccination->defaultFollowUpMonths());
        $this->assertSame(3, HealthRecordType::Deworming->defaultFollowUpMonths());
        $this->assertSame(2, HealthRecordType::Farrier->defaultFollowUpMonths());
        $this->assertSame(12, HealthRecordType::Dentist->defaultFollowUpMonths());
        $this->assertSame(6, HealthRecordType::CheckUp->defaultFollowUpMonths());

        // Vet visit and medication intentionally have no default — too varied.
        $this->assertNull(HealthRecordType::VetVisit->defaultFollowUpMonths());
        $this->assertNull(HealthRecordType::Medication->defaultFollowUpMonths());
        $this->assertNull(HealthRecordType::Other->defaultFollowUpMonths());
    }

    public function test_create_record_with_all_fields(): void
    {
        $record = HealthRecord::create([
            'horse_id' => $this->horse->id,
            'type' => HealthRecordType::Vaccination->value,
            'performed_at' => now(),
            'performed_by' => 'Dr Anna Kowalska',
            'summary' => 'Szczepienie tężec + grypa',
            'details' => 'Standardowe szczepienie roczne',
            'next_due_at' => now()->addYear()->toDateString(),
            'cost_cents' => 25000,
        ]);

        $this->assertSame(HealthRecordType::Vaccination, $record->type);
        $this->assertSame('Szczepienie tężec + grypa', $record->summary);
        $this->assertSame(25000, $record->cost_cents);
        $this->assertNotNull($record->next_due_at);
    }

    public function test_horse_relation_exposes_records(): void
    {
        HealthRecord::create($this->payload());
        HealthRecord::create($this->payload());

        $this->assertCount(2, $this->horse->healthRecords);
    }

    public function test_due_within_scope_filters_records(): void
    {
        // 5 days from now → in scope
        HealthRecord::create($this->payload(nextDueAt: now()->addDays(5)));
        // 60 days from now → out of scope
        HealthRecord::create($this->payload(nextDueAt: now()->addDays(60)));
        // No next_due → out of scope
        HealthRecord::create($this->payload(nextDueAt: null));

        $this->assertSame(1, HealthRecord::query()->dueWithin(30)->count());
    }

    public function test_overdue_scope_picks_past_dates(): void
    {
        HealthRecord::create($this->payload(nextDueAt: now()->subDays(2)));
        HealthRecord::create($this->payload(nextDueAt: now()->addDays(2)));

        $this->assertSame(1, HealthRecord::query()->overdue()->count());
    }

    public function test_alerts_service_lists_overdue_and_upcoming(): void
    {
        $overdue = HealthRecord::create($this->payload(nextDueAt: now()->subDay(), summary: 'Stara szczepionka'));
        $soon = HealthRecord::create($this->payload(nextDueAt: now()->addDays(5), summary: 'Wkrótce'));
        $far = HealthRecord::create($this->payload(nextDueAt: now()->addDays(60), summary: 'Daleko'));

        $alerts = app(UpcomingHealthAlertsService::class)->upcomingAndOverdue(30);

        $ids = $alerts->pluck('id')->all();
        $this->assertContains($overdue->id, $ids);
        $this->assertContains($soon->id, $ids);
        $this->assertNotContains($far->id, $ids);

        // Sorted by due_at ascending — overdue first
        $this->assertSame($overdue->id, $alerts[0]['id']);
        $this->assertTrue($alerts[0]['is_overdue']);
        $this->assertFalse($alerts[1]['is_overdue']);
    }

    public function test_alerts_service_counts(): void
    {
        HealthRecord::create($this->payload(nextDueAt: now()->subDay()));         // overdue
        HealthRecord::create($this->payload(nextDueAt: now()->addDays(3)));       // 7d
        HealthRecord::create($this->payload(nextDueAt: now()->addDays(20)));      // 30d, not 7d
        HealthRecord::create($this->payload(nextDueAt: now()->addDays(100)));     // out

        $counts = app(UpcomingHealthAlertsService::class)->counts();

        $this->assertSame(1, $counts['overdue']);
        $this->assertSame(1, $counts['due_within_7_days']);
        $this->assertSame(2, $counts['due_within_30_days']);
    }

    public function test_days_until_due_helper(): void
    {
        $record = HealthRecord::create($this->payload(nextDueAt: now()->addDays(10)));
        $this->assertSame(10, $record->daysUntilDue());

        $record2 = HealthRecord::create($this->payload(nextDueAt: now()->subDays(3)));
        $this->assertSame(-3, $record2->daysUntilDue());

        $record3 = HealthRecord::create($this->payload(nextDueAt: null));
        $this->assertNull($record3->daysUntilDue());
    }

    public function test_is_overdue_helper(): void
    {
        $past = HealthRecord::create($this->payload(nextDueAt: now()->subDay()));
        $future = HealthRecord::create($this->payload(nextDueAt: now()->addDay()));
        $none = HealthRecord::create($this->payload(nextDueAt: null));

        $this->assertTrue($past->isOverdue());
        $this->assertFalse($future->isOverdue());
        $this->assertFalse($none->isOverdue());
    }

    public function test_soft_delete_record(): void
    {
        $record = HealthRecord::create($this->payload());
        $record->delete();

        $this->assertSame(0, HealthRecord::query()->count());
        $this->assertSame(1, HealthRecord::withTrashed()->count());
    }

    private function payload(?Carbon $nextDueAt = null, string $summary = 'Test'): array
    {
        return [
            'horse_id' => $this->horse->id,
            'type' => HealthRecordType::Vaccination->value,
            'performed_at' => now()->subDay(),
            'summary' => $summary,
            'next_due_at' => $nextDueAt?->toDateString(),
        ];
    }

    private function bootTenantContext(): void
    {
        $tenant = new Tenant([
            'slug' => 'health-test',
            'name' => 'Health Test',
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
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('owner_client_id', 26)->nullable();
            $t->string('microchip', 32)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('ueln', 15)->nullable();
            $t->string('breed', 120)->nullable();
            $t->string('sex', 32)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->string('cover_image_path')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('health_records', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('horse_id', 26);
            $t->string('type', 32);
            $t->dateTime('performed_at');
            $t->string('performed_by', 255)->nullable();
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
    }
}
