<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Geocoding\Data\GeocodedAddress;
use App\Domain\Transport\Geocoding\Exceptions\GeocodingException;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\TenantType;
use App\Filament\Transport\Resources\PoiResource;
use App\Filament\Transport\Resources\PoiResource\Pages\CreatePoi;
use App\Filament\Transport\Resources\PoiResource\Pages\EditPoi;
use App\Models\Central\Tenant;
use App\Models\Tenant\Poi;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use ReflectionClass;
use Tests\TestCase;

/**
 * POIResource — pokrywa lifecycle Create/Edit z geokodowaniem
 * adresu przez MapboxGeocoder oraz właściwe Resource routes.
 */
class PoiResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_poi_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
        $this->makeTenant();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_create_geocodes_address_into_lat_lng(): void
    {
        $this->mockGeocoder([
            'Baza Warszawa' => new GeocodedAddress(
                displayName: 'Baza, ul. Bazowa 1, Warszawa, Polska',
                coords: new Coords(52.2297, 21.0122),
                countryCode: 'PL',
                voivodeship: 'mazowieckie',
            ),
        ]);

        $page = new class extends CreatePoi
        {
            public function callMutate(array $data): array
            {
                return $this->mutateFormDataBeforeCreate($data);
            }
        };

        $data = $page->callMutate([
            'name' => 'Baza WAW',
            'kind' => Poi::KIND_BASE,
            'address' => 'Baza Warszawa',
            'lat' => 0,
            'lng' => 0,
        ]);

        $this->assertSame(52.2297, $data['lat']);
        $this->assertSame(21.0122, $data['lng']);
        // Display name z geocoder'a wygrywa nad user-typed.
        $this->assertSame('Baza, ul. Bazowa 1, Warszawa, Polska', $data['address']);
    }

    public function test_create_soft_fails_on_geocoder_error(): void
    {
        $this->mock(MapboxGeocoder::class, function (MockInterface $m) {
            $m->shouldReceive('geocode')->andThrow(new GeocodingException('no match'));
        });

        $page = new class extends CreatePoi
        {
            public function callMutate(array $data): array
            {
                return $this->mutateFormDataBeforeCreate($data);
            }
        };

        $data = $page->callMutate([
            'name' => 'Baza WAW',
            'kind' => Poi::KIND_BASE,
            'address' => 'Wymyślony adres bez geocode',
            'lat' => 0,
            'lng' => 0,
        ]);

        // Lat/lng pozostają 0 — user może retry'ować po naprawie adresu.
        $this->assertSame(0, $data['lat']);
        $this->assertSame(0, $data['lng']);
        // Address bez zmian.
        $this->assertSame('Wymyślony adres bez geocode', $data['address']);
    }

    public function test_edit_skips_geocoder_when_address_unchanged(): void
    {
        $poi = Poi::create([
            'id' => (string) Str::ulid(),
            'name' => 'Baza',
            'kind' => Poi::KIND_BASE,
            'address' => 'Same Address',
            'lat' => 52.0,
            'lng' => 21.0,
        ]);

        $geocoderCalled = false;
        $this->mock(MapboxGeocoder::class, function (MockInterface $m) use (&$geocoderCalled) {
            $m->shouldReceive('geocode')->andReturnUsing(function () use (&$geocoderCalled) {
                $geocoderCalled = true;
                throw new \RuntimeException('Geocoder should not be called when address unchanged');
            });
        });

        $page = new class($poi) extends EditPoi
        {
            public function __construct($record)
            {
                $this->record = $record;
            }

            public function callMutate(array $data): array
            {
                return $this->mutateFormDataBeforeSave($data);
            }
        };

        $data = $page->callMutate([
            'name' => 'Baza (renamed)',
            'address' => 'Same Address',
            'lat' => 52.0,
            'lng' => 21.0,
        ]);

        $this->assertFalse($geocoderCalled);
        $this->assertSame(52.0, $data['lat']);
    }

    public function test_edit_regeocodes_when_address_changes(): void
    {
        $poi = Poi::create([
            'id' => (string) Str::ulid(),
            'name' => 'Baza',
            'kind' => Poi::KIND_BASE,
            'address' => 'Old Address',
            'lat' => 52.0,
            'lng' => 21.0,
        ]);

        $this->mockGeocoder([
            'New Address' => new GeocodedAddress(
                displayName: 'New Address, Polska',
                coords: new Coords(50.0, 19.0),
                countryCode: 'PL',
                voivodeship: 'małopolskie',
            ),
        ]);

        $page = new class($poi) extends EditPoi
        {
            public function __construct($record)
            {
                $this->record = $record;
            }

            public function callMutate(array $data): array
            {
                return $this->mutateFormDataBeforeSave($data);
            }
        };

        $data = $page->callMutate([
            'name' => 'Baza',
            'address' => 'New Address',
            'lat' => 52.0,  // stare coords — geocoder przepisze
            'lng' => 21.0,
        ]);

        $this->assertSame(50.0, $data['lat']);
        $this->assertSame(19.0, $data['lng']);
    }

    public function test_resource_routes_are_registered(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter()->values();

        $this->assertTrue($names->contains('filament.transport.resources.pois.index'));
        $this->assertTrue($names->contains('filament.transport.resources.pois.create'));
        $this->assertTrue($names->contains('filament.transport.resources.pois.edit'));
    }

    public function test_kind_options_cover_all_constants(): void
    {
        $options = PoiResource::kindOptions();
        $this->assertArrayHasKey(Poi::KIND_BASE, $options);
        $this->assertArrayHasKey(Poi::KIND_STABLE, $options);
        $this->assertArrayHasKey(Poi::KIND_PARKING, $options);
        $this->assertArrayHasKey(Poi::KIND_FUEL, $options);
        $this->assertArrayHasKey(Poi::KIND_OTHER, $options);
    }

    /**
     * @param  array<string, GeocodedAddress>  $byQuery
     */
    private function mockGeocoder(array $byQuery): void
    {
        $this->mock(MapboxGeocoder::class, function (MockInterface $m) use ($byQuery) {
            $m->shouldReceive('geocode')->andReturnUsing(function (string $q) use ($byQuery) {
                if (! isset($byQuery[$q])) {
                    throw new GeocodingException('no fixture for query: '.$q);
                }

                return $byQuery[$q];
            });
        });
    }

    private function makeTenant(): Tenant
    {
        $u = uniqid();
        $t = Tenant::create([
            'slug' => 'poi-'.$u,
            'name' => 'POI Test',
            'type' => TenantType::Transporter,
            'db_name' => 'p_'.$u,
            'db_username' => 'p_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $tm = $this->app->make(TenantManager::class);
        $ref = new ReflectionClass($tm);
        $prop = $ref->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $t);

        return $t;
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('pois', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('name', 120);
            $t->string('kind', 16)->default('other');
            $t->string('address');
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }
}
