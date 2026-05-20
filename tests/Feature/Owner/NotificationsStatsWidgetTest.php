<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Enums\TenantType;
use App\Filament\Owner\Widgets\NotificationsStatsWidget;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadResponse;
use App\Models\Tenant\TransportOrder;
use App\Tenancy\TenantManager;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * PR 11 — notifications hub w owner panelu. Widget pokazuje 3 statystyki
 * z drill-down do TransportOrderResource.
 *
 * Pokrywa:
 *  - count "Nowe oferty" agregowany cross-DB (tenant orders →
 *    central responses)
 *  - count "Zaakceptowane" tylko z ostatnich 14 dni
 *  - count "Nadchodzące" — preferred_date w oknie [today, +3 dni]
 *  - URL drill-down ustawiony tylko gdy count > 0
 */
class NotificationsStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_ownotif_').'.sqlite';
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

        // TransportOrderResource::getUrl() resolve'uje przez current panel —
        // bez setCurrentPanel default'uje na pierwszy panel (admin/app).
        Filament::setCurrentPanel(Filament::getPanel('owner'));
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_widget_counts_new_offers_from_central_responses(): void
    {
        $lead = $this->makeLead();
        $this->makeOrder($lead->id);

        TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $lead->id,
            'transporter_tenant_id' => (string) Str::ulid(),
            'price_net' => 1000,
            'price_gross' => 1230,
            'currency' => 'PLN',
            'proposed_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
            'responded_at' => now(),
        ]);
        TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $lead->id,
            'transporter_tenant_id' => (string) Str::ulid(),
            'price_net' => 1100,
            'price_gross' => 1353,
            'currency' => 'PLN',
            'proposed_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
            'responded_at' => now(),
        ]);
        // Draft response (responded_at NULL) — pomijamy.
        TransportLeadResponse::create([
            'id' => (string) Str::ulid(),
            'lead_id' => $lead->id,
            'transporter_tenant_id' => (string) Str::ulid(),
            'price_net' => 900,
            'price_gross' => 1107,
            'currency' => 'PLN',
            'proposed_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
            'responded_at' => null,
        ]);

        $stats = $this->callGetStats();

        $this->assertSame('2', $this->valueOf($stats[0]),
            'Powinno zliczyć tylko responded responses (nie draft)');
    }

    public function test_new_offers_count_is_zero_when_no_orders(): void
    {
        $stats = $this->callGetStats();
        $this->assertSame('0', $this->valueOf($stats[0]));
    }

    public function test_widget_counts_accepted_in_last_14_days_only(): void
    {
        $oldLead = $this->makeLead();
        $recentLead = $this->makeLead();

        // Stary accepted (>14 dni) — pomijamy.
        $oldOrder = $this->makeOrder($oldLead->id, ['status' => 'accepted']);
        $oldOrder->updated_at = now()->subDays(20);
        $oldOrder->saveQuietly();

        // Świeży accepted — liczymy.
        $this->makeOrder($recentLead->id, ['status' => 'accepted']);

        // Open — pomijamy.
        $openLead = $this->makeLead();
        $this->makeOrder($openLead->id, ['status' => 'open']);

        $stats = $this->callGetStats();

        $this->assertSame('1', $this->valueOf($stats[1]));
    }

    public function test_widget_counts_upcoming_within_3_days(): void
    {
        // W oknie:
        $this->makeOrder($this->makeLead()->id, [
            'preferred_date' => now()->addDay()->toDateString(),
            'status' => 'open',
        ]);
        $this->makeOrder($this->makeLead()->id, [
            'preferred_date' => now()->addDays(3)->toDateString(),
            'status' => 'quoted',
        ]);
        // Poza oknem (>3 dni):
        $this->makeOrder($this->makeLead()->id, [
            'preferred_date' => now()->addDays(5)->toDateString(),
            'status' => 'open',
        ]);
        // Wczoraj — past, pomijamy.
        $this->makeOrder($this->makeLead()->id, [
            'preferred_date' => now()->subDay()->toDateString(),
            'status' => 'open',
        ]);
        // Niewłaściwy status (cancelled) — pomijamy.
        $this->makeOrder($this->makeLead()->id, [
            'preferred_date' => now()->addDay()->toDateString(),
            'status' => 'cancelled',
        ]);

        $stats = $this->callGetStats();

        $this->assertSame('2', $this->valueOf($stats[2]));
    }

    public function test_zero_counts_have_no_drilldown_url(): void
    {
        // Pusty stan — wszystkie 3 statystyki = 0, URL=null.
        $stats = $this->callGetStats();

        foreach ($stats as $stat) {
            $this->assertSame('0', $this->valueOf($stat));
            $this->assertNull($this->urlOf($stat),
                'Nie chcemy stat z count=0 linkowac nigdzie — confusing UX');
        }
    }

    /**
     * @return array<int, Stat>
     */
    private function callGetStats(): array
    {
        $widget = new NotificationsStatsWidget;
        $method = new ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    private function valueOf(Stat $stat): string
    {
        $ref = new \ReflectionClass($stat);
        while ($ref !== false && ! $ref->hasProperty('value')) {
            $ref = $ref->getParentClass();
        }
        if ($ref === false) {
            return '';
        }
        $prop = $ref->getProperty('value');
        $prop->setAccessible(true);

        return (string) $prop->getValue($stat);
    }

    private function urlOf(Stat $stat): ?string
    {
        $ref = new \ReflectionClass($stat);
        while ($ref !== false && ! $ref->hasProperty('url')) {
            $ref = $ref->getParentClass();
        }
        if ($ref === false) {
            return null;
        }
        $prop = $ref->getProperty('url');
        $prop->setAccessible(true);
        $value = $prop->getValue($stat);

        return is_string($value) ? $value : null;
    }

    private function makeLead(): TransportLead
    {
        return TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'originator_name' => 'Owner',
            'originator_email' => 'owner-'.uniqid().'@example.com',
            'pickup_address' => 'PickAddr',
            'pickup_lat' => 52.0,
            'pickup_lng' => 21.0,
            'pickup_voivodeship' => 'mazowieckie',
            'dropoff_address' => 'DropAddr',
            'dropoff_lat' => 50.0,
            'dropoff_lng' => 19.0,
            'dropoff_voivodeship' => 'małopolskie',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'horse_count' => 1,
            'status' => 'open',
            'expires_at' => now()->addDays(14),
        ]);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeOrder(string $centralLeadId, array $overrides = []): TransportOrder
    {
        return TransportOrder::create(array_merge([
            'id' => (string) Str::ulid(),
            'central_lead_id' => $centralLeadId,
            'pickup_address' => 'pickup',
            'dropoff_address' => 'dropoff',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'calculation_mode' => 'one_way',
            'status' => 'open',
        ], $overrides));
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = Tenant::create([
            'slug' => 'own-'.$u,
            'name' => 'Owner',
            'type' => TenantType::HorseOwner,
            'db_name' => 'own_'.$u,
            'db_username' => 'own_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $tm = $this->app->make(TenantManager::class);
        $ref = new \ReflectionClass($tm);
        $prop = $ref->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('transport_orders', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_lead_id', 26)->index();
            $t->string('horse_id', 26)->nullable();
            $t->string('pickup_address');
            $t->string('dropoff_address');
            $t->date('preferred_date');
            $t->time('preferred_time')->nullable();
            $t->string('calculation_mode', 24)->default('one_way');
            $t->string('status', 24)->default('open');
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
