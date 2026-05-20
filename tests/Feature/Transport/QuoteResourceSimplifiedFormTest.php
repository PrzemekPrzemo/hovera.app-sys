<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Domain\Transport\Calculator\CalculatorService;
use App\Domain\Transport\Calculator\Data\Quotation;
use App\Domain\Transport\Geocoding\Data\GeocodedAddress;
use App\Domain\Transport\Geocoding\MapboxGeocoder;
use App\Domain\Transport\Quotes\QuoteNumberGenerator;
use App\Domain\Transport\Routing\Data\Coords;
use App\Enums\CalculationMode;
use App\Enums\TenantType;
use App\Filament\Transport\Resources\QuoteResource;
use App\Filament\Transport\Resources\QuoteResource\Pages\CreateQuote;
use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery\MockInterface;
use ReflectionClass;
use Tests\TestCase;

/**
 * PR "uprość /transport/quotes/create" — sprawdzamy że:
 *   1. lat/lng są Hidden (nie wymagają user input)
 *   2. customer_* pola są ukrywane gdy customer_id wybrany
 *   3. auto-routing flow: mutateFormDataBeforeCreate geocoduje adresy
 *      i wypełnia financial fields z CalculatorService
 *   4. legacy round_trip toggle nie pojawia się w form schema
 *
 * Form inspection robi się przez schemat — bez mount'owania Livewire
 * (Filament internals są dość stabilne na poziomie field name/type).
 */
class QuoteResourceSimplifiedFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_lat_lng_fields_are_hidden_not_text_inputs(): void
    {
        $fields = $this->collectFormFields();

        foreach (['pickup_lat', 'pickup_lng', 'dropoff_lat', 'dropoff_lng'] as $name) {
            $this->assertArrayHasKey($name, $fields, "Pole {$name} musi istnieć w form schema");
            $this->assertInstanceOf(
                Hidden::class,
                $fields[$name],
                "Pole {$name} musi być Hidden (auto-geokodowane), nie TextInput",
            );
        }
    }

    public function test_pricing_internals_are_hidden(): void
    {
        $fields = $this->collectFormFields();

        // Te pola wyliczamy automatycznie — user ich nie powinien widzieć.
        foreach ([
            'base_cost',
            'fuel_surcharge',
            'extra_horse_fee_snapshot',
            'minimum_adjustment',
            'vat_amount',
            'duration_seconds',
            'currency',
            'routing_provider',
        ] as $name) {
            $this->assertArrayHasKey($name, $fields, "Pole {$name} musi być w schema");
            $this->assertInstanceOf(
                Hidden::class,
                $fields[$name],
                "Pole {$name} powinno być Hidden po uproszczeniu — wyliczane automatycznie",
            );
        }
    }

    public function test_calculation_mode_replaces_round_trip_toggle(): void
    {
        $fields = $this->collectFormFields();

        $this->assertArrayHasKey('calculation_mode', $fields, 'calculation_mode Select musi być w schema');
        $this->assertArrayNotHasKey('round_trip', $fields, 'Legacy round_trip toggle powinien być usunięty z UI');
    }

    public function test_customer_id_hides_redundant_customer_fields(): void
    {
        $fields = $this->collectFormFields();

        $this->assertArrayHasKey('customer_name', $fields, 'customer_name musi istnieć w schema');

        // Predicat `visible(fn (Get $get) => ! $get('customer_id'))` — ciężko
        // odpalić bez form mount'a; zamiast tego sprawdzamy że pole MA visible
        // closure. Filament Field expose'uje to przez `isVisible()` ale wymaga
        // container — zamiast tego patrzymy reflection'em na `visible` property.
        $customerName = $fields['customer_name'];

        $this->assertNotNull(
            $this->extractVisibleClosure($customerName),
            'customer_name musi mieć visible() predicate (hide gdy customer_id picked)',
        );
    }

    public function test_mutate_data_geocodes_and_populates_pricing_when_auto_routing_true(): void
    {
        // Mocki: geocoder zwraca dwa adresy z lat/lng, CalculatorService
        // zwraca pełną wycenę. CreateQuote::autoCalculatePricing składa
        // wszystkie pola.
        $this->mockGeocoder();
        $this->mockCalculator();
        $this->setUpTenantContext();

        $page = new class extends CreateQuote
        {
            public function callMutate(array $data): array
            {
                return $this->mutateFormDataBeforeCreate($data);
            }
        };

        $data = $page->callMutate([
            'auto_routing' => true,
            'pickup_address' => 'Warszawa',
            'dropoff_address' => 'Kraków',
            'calculation_mode' => CalculationMode::OneWay->value,
            'loaded' => true,
            'horses_count' => 2,
            'rate_per_km' => null,
            'vat_rate' => null,
            'status' => 'draft',
            'customer_name' => 'Jan Test',
        ]);

        // auto_routing nie powinien trafić do $data — to formularzowy toggle,
        // nie kolumna w quotes.
        $this->assertArrayNotHasKey('auto_routing', $data);

        // Lat/lng wypełnione z geokodera.
        $this->assertSame(52.2297, $data['pickup_lat']);
        $this->assertSame(21.0122, $data['pickup_lng']);
        $this->assertSame(50.0647, $data['dropoff_lat']);
        $this->assertSame(19.9450, $data['dropoff_lng']);

        // Financials z CalculatorService'u.
        $this->assertSame(300.0, $data['distance_km']);
        $this->assertSame(7200, $data['duration_seconds']);
        $this->assertSame(4.50, $data['rate_per_km']);
        $this->assertSame(1350.0, $data['base_cost']);
        $this->assertSame(2000.00, $data['net_total']);
        $this->assertSame(2460.00, $data['gross_total']);
        $this->assertSame('PLN', $data['currency']);
        $this->assertSame('ors', $data['routing_provider']);

        // Number snapshot (QuoteNumberGenerator robi przyrostowy counter; w
        // testach SQLite generator nie ma jeszcze counter row'a, więc number
        // może być null — sprawdzamy tylko klucz, nie konkretną wartość).
        $this->assertArrayHasKey('number', $data);
    }

    public function test_mutate_data_skips_geocoding_when_auto_routing_false(): void
    {
        // Gdy user wyłączył auto-routing (lub Calculator pre-fill ustawił
        // auto_routing=false), mutate hook NIE woła geokodera ani
        // CalculatorService'u — wartości z form state idą do DB as-is.
        $geocoderCalled = false;
        $this->mock(MapboxGeocoder::class, function (MockInterface $m) use (&$geocoderCalled) {
            $m->shouldReceive('geocode')->andReturnUsing(function () use (&$geocoderCalled) {
                $geocoderCalled = true;
                throw new \RuntimeException('Geocoder should not be called when auto_routing=false');
            });
        });
        $this->setUpTenantContext();

        $page = new class extends CreateQuote
        {
            public function callMutate(array $data): array
            {
                return $this->mutateFormDataBeforeCreate($data);
            }
        };

        $data = $page->callMutate([
            'auto_routing' => false,
            'pickup_address' => 'Manual address',
            'dropoff_address' => 'Manual dest',
            'pickup_lat' => 52.0,
            'pickup_lng' => 21.0,
            'dropoff_lat' => 50.0,
            'dropoff_lng' => 19.0,
            'distance_km' => 999.0,
            'net_total' => 1500.0,
            'gross_total' => 1845.0,
            'currency' => 'PLN',
            'horses_count' => 1,
        ]);

        $this->assertFalse($geocoderCalled);
        $this->assertSame(999.0, $data['distance_km'], 'Manual values muszą zostać nietknięte');
        $this->assertSame(1500.0, $data['net_total']);
        $this->assertSame(52.0, $data['pickup_lat']);
    }

    /**
     * Zbiera wszystkie pola z form schematu (recursive — sections
     * zagnieżdżają fields). Klucz = field name, wartość = field instance.
     *
     * @return array<string, Field>
     */
    private function collectFormFields(): array
    {
        $page = new class extends CreateRecord
        {
            protected static string $resource = QuoteResource::class;
        };

        // Tworzymy Form ręcznie zamiast mount'ować Livewire — interesuje
        // nas tylko schema, nie state.
        $form = Form::make($page);
        $schema = QuoteResource::form($form)->getComponents();

        $result = [];
        $this->walkSchema($schema, $result);

        return $result;
    }

    /**
     * @param  array<int, mixed>  $schema
     * @param  array<string, Field>  $out
     */
    private function walkSchema(array $schema, array &$out): void
    {
        foreach ($schema as $component) {
            if ($component instanceof Section) {
                $this->walkSchema($component->getChildComponents(), $out);

                continue;
            }
            if ($component instanceof Field) {
                $out[$component->getName()] = $component;
            }
        }
    }

    private function extractVisibleClosure(Field $field): ?\Closure
    {
        // Filament Field trzyma visibility predicate w property `isVisible`
        // (closure | bool). Wyciągamy przez reflection — testowe API
        // Filamentu wymagałoby form mount'a.
        $ref = new ReflectionClass($field);
        while ($ref !== false && ! $ref->hasProperty('isVisible')) {
            $ref = $ref->getParentClass();
        }
        if ($ref === false) {
            return null;
        }
        $prop = $ref->getProperty('isVisible');
        $prop->setAccessible(true);
        $value = $prop->getValue($field);

        return $value instanceof \Closure ? $value : null;
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

    private function mockCalculator(): void
    {
        $this->mock(CalculatorService::class, function (MockInterface $m) {
            $m->shouldReceive('calculate')->andReturn(new Quotation(
                distanceKm: 300.0,
                durationSeconds: 7200,
                rateUsed: 4.50,
                baseCost: 1350.0,
                fuelSurcharge: 100.0,
                minimumAdjustment: 0.0,
                netTotal: 2000.00,
                vatRate: 23.0,
                vatAmount: 460.00,
                grossTotal: 2460.00,
                currency: 'PLN',
                routingProvider: 'ors',
                polyline: 'POLY',
                extraHorseFeeTotal: 150.0,
                extraHorseFeePerHead: 150.0,
                horsesCount: 2,
            ));
        });
    }

    private function setUpTenantContext(): void
    {
        $u = uniqid();
        $tenant = Tenant::create([
            'slug' => 'qrs-'.$u,
            'name' => 'Quote test',
            'type' => TenantType::Transporter,
            'db_name' => 'qrs_'.$u,
            'db_username' => 'qrs_'.substr($u, -8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);

        $tm = $this->app->make(TenantManager::class);
        $ref = new ReflectionClass($tm);
        $prop = $ref->getProperty('current');
        $prop->setAccessible(true);
        $prop->setValue($tm, $tenant);

        // QuoteNumberGenerator + TransportSettings żyją w tenant DB —
        // w tym teście nie potrzebujemy ich realnego stanu, bo mocky
        // przejmują przed nimi.
        $this->app->bind(QuoteNumberGenerator::class,
            fn () => new class
            {
                public function next(): string
                {
                    return 'TEST-001';
                }

                public function preview(): string
                {
                    return 'TEST-001';
                }
            }
        );
    }
}
