<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Domain\Transport\Geocoding\Data\GeocodedAddress;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Leads\LeadDispatcher;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\CalculationMode;
use App\Enums\TenantType;
use App\Filament\Owner\Pages\OrderTransport;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Models\Central\User;
use App\Models\Tenant\OwnerHorse;
use App\Models\Tenant\TransportOrder;
use App\Tenancy\TenantManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * OrderTransport — mini-Calculator dla owner panel'u. Submit tworzy:
 *   - TransportLead w central DB (status=open, mode=broadcast)
 *   - TransportOrder w tenant DB (snapshot trasy + soft FK do leada)
 *   - dispatch przez LeadDispatcher (mockujemy żeby nie odpalać network'u)
 */
class OrderTransportTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_owner_').'.sqlite';
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
        $this->user = User::create([
            'name' => 'Jan Owner',
            'email' => 'jan-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->mockGeocoder();
        $this->mockDispatcher();

        // TransportOrderResource::getUrl() resolves przez current panel —
        // bez setCurrentPanel() Filament default'uje na pierwszy panel
        // (admin/app) i route URL nie istnieje.
        Filament::setCurrentPanel(Filament::getPanel('owner'));
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_submit_creates_lead_and_local_order(): void
    {
        $this->actingAs($this->user);

        $page = new OrderTransport;
        $page->data = [
            'horse_id' => null,
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'preferred_time' => null,
            'calculation_mode' => CalculationMode::OneWay->value,
            'notes' => 'Pilne, ostrożnie z transportem.',
        ];

        $response = $page->submit(app(MapboxGeocoder::class), app(LeadDispatcher::class));

        $this->assertNotNull($response, 'submit() powinien zwrócić redirect po sukcesie');

        // Lead w central
        $lead = TransportLead::query()->where('originator_user_id', $this->user->id)->first();
        $this->assertNotNull($lead);
        $this->assertSame('broadcast', $lead->mode);
        $this->assertSame('open', $lead->status);
        $this->assertSame($this->tenant->id, $lead->originator_tenant_id);
        $this->assertSame('mazowieckie', $lead->pickup_voivodeship);
        $this->assertSame('małopolskie', $lead->dropoff_voivodeship);

        // Lokalny TransportOrder
        $order = TransportOrder::query()->first();
        $this->assertNotNull($order);
        $this->assertSame($lead->id, $order->central_lead_id);
        $this->assertSame('open', $order->status);
        $this->assertSame(CalculationMode::OneWay->value, $order->calculation_mode);
        $this->assertSame('Pilne, ostrożnie z transportem.', $order->notes);
    }

    public function test_submit_links_horse_when_selected(): void
    {
        $this->actingAs($this->user);

        $horse = OwnerHorse::create([
            'id' => (string) Str::ulid(),
            'name' => 'Iskra',
        ]);

        $page = new OrderTransport;
        $page->data = [
            'horse_id' => $horse->id,
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'preferred_time' => null,
            'calculation_mode' => CalculationMode::RoundTrip->value,
            'notes' => '',
        ];

        $page->submit(app(MapboxGeocoder::class), app(LeadDispatcher::class));

        $order = TransportOrder::query()->first();
        $this->assertSame($horse->id, $order->horse_id);
        $this->assertSame(CalculationMode::RoundTrip->value, $order->calculation_mode);
    }

    public function test_submit_calls_dispatcher(): void
    {
        $this->actingAs($this->user);

        $this->mock(LeadDispatcher::class, function (MockInterface $m) {
            $m->shouldReceive('dispatch')->once()
                ->andReturn(['notified' => 2, 'transporter_ids' => ['a', 'b']]);
        });

        $page = new OrderTransport;
        $page->data = [
            'horse_id' => null,
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'preferred_time' => null,
            'calculation_mode' => CalculationMode::OneWay->value,
            'notes' => '',
        ];

        $page->submit(app(MapboxGeocoder::class), app(LeadDispatcher::class));
    }

    private function mockGeocoder(): void
    {
        $this->mock(MapboxGeocoder::class, function (MockInterface $m) {
            $m->shouldReceive('geocode')->andReturnUsing(function (string $query) {
                return match ($query) {
                    'Warszawa' => new GeocodedAddress(
                        displayName: 'Warszawa, Polska',
                        coords: new Coords(52.2297, 21.0122),
                        countryCode: 'PL',
                        voivodeship: 'mazowieckie',
                    ),
                    'Kraków' => new GeocodedAddress(
                        displayName: 'Kraków, Polska',
                        coords: new Coords(50.0647, 19.9450),
                        countryCode: 'PL',
                        voivodeship: 'małopolskie',
                    ),
                    default => throw new \RuntimeException('Unexpected geocode query: '.$query),
                };
            });
        });
    }

    private function mockDispatcher(): void
    {
        // Default mock; pojedyncze testy mogą nadpisać.
        $this->mock(LeadDispatcher::class, function (MockInterface $m) {
            $m->shouldReceive('dispatch')->andReturn(['notified' => 0, 'transporter_ids' => []]);
        });
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = Tenant::create([
            'slug' => 'owner-'.$u,
            'name' => 'Jan Owner',
            'type' => TenantType::HorseOwner,
            'db_name' => 'owner_'.$u,
            'db_username' => 'owner_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $tm = $this->app->make(TenantManager::class);
        $reflection = new \ReflectionClass($tm);
        $prop = $reflection->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('horses', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('breed', 120)->nullable();
            $t->string('passport_number', 64)->nullable();
            $t->string('microchip', 32)->nullable();
            $t->string('sex', 24)->nullable();
            $t->string('color', 60)->nullable();
            $t->date('birth_date')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

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
