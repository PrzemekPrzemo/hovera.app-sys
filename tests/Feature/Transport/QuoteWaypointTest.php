<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Models\Tenant\Poi;
use App\Models\Tenant\Quote;
use App\Models\Tenant\QuoteWaypoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Foundation testy dla waypoints + POI library — patrz
 * docs/MARKETPLACE-ROADMAP.md "Waypoints + reorder + POI library".
 *
 * Pokrywa:
 *  - Quote.waypoints HasMany w kolejności sort_order
 *  - QuoteWaypoint kasy/scopes
 *  - Poi soft-deletes + active scope
 *  - cascadeOnDelete: usunięcie quote'a kasuje waypointy
 */
class QuoteWaypointTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_wp_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,  // żeby cascadeOnDelete działał
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpTenantTables();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_quote_waypoints_returned_ordered_by_sort_order(): void
    {
        $quote = $this->makeQuote();

        QuoteWaypoint::create([
            'id' => (string) Str::ulid(),
            'quote_id' => $quote->id,
            'sort_order' => 2,
            'address' => 'B',
            'lat' => 51.5, 'lng' => 20.0,
        ]);
        QuoteWaypoint::create([
            'id' => (string) Str::ulid(),
            'quote_id' => $quote->id,
            'sort_order' => 0,
            'address' => 'A',
            'lat' => 51.0, 'lng' => 20.5,
        ]);
        QuoteWaypoint::create([
            'id' => (string) Str::ulid(),
            'quote_id' => $quote->id,
            'sort_order' => 1,
            'address' => 'C',
            'lat' => 50.5, 'lng' => 19.5,
        ]);

        $waypoints = $quote->waypoints()->get();

        $this->assertSame(['A', 'C', 'B'], $waypoints->pluck('address')->all());
    }

    public function test_waypoint_kind_default_is_stop(): void
    {
        $quote = $this->makeQuote();
        $wp = QuoteWaypoint::create([
            'id' => (string) Str::ulid(),
            'quote_id' => $quote->id,
            'sort_order' => 0,
            'address' => 'X',
            'lat' => 51.0, 'lng' => 20.0,
        ]);

        $this->assertSame(QuoteWaypoint::KIND_STOP, $wp->refresh()->kind);
    }

    public function test_waypoint_supports_all_kinds(): void
    {
        $quote = $this->makeQuote();

        foreach ([
            QuoteWaypoint::KIND_STOP,
            QuoteWaypoint::KIND_PICKUP,
            QuoteWaypoint::KIND_DROPOFF,
            QuoteWaypoint::KIND_REST,
            QuoteWaypoint::KIND_POI,
        ] as $kind) {
            $wp = QuoteWaypoint::create([
                'id' => (string) Str::ulid(),
                'quote_id' => $quote->id,
                'sort_order' => 0,
                'kind' => $kind,
                'address' => 'X',
                'lat' => 51.0, 'lng' => 20.0,
            ]);
            $this->assertSame($kind, $wp->refresh()->kind);
        }
    }

    public function test_deleting_quote_cascades_to_waypoints(): void
    {
        $quote = $this->makeQuote();
        QuoteWaypoint::create([
            'id' => (string) Str::ulid(),
            'quote_id' => $quote->id,
            'sort_order' => 0,
            'address' => 'X',
            'lat' => 51.0, 'lng' => 20.0,
        ]);

        $this->assertSame(1, QuoteWaypoint::query()->count());

        // forceDelete bo Quote ma SoftDeletes — wycenowy soft delete
        // NIE powinien kaskadować na waypointy (zachowujemy historię),
        // ale hard delete tak.
        $quote->forceDelete();

        $this->assertSame(0, QuoteWaypoint::query()->count());
    }

    public function test_poi_soft_deletes(): void
    {
        $poi = Poi::create([
            'id' => (string) Str::ulid(),
            'name' => 'Baza Warszawa',
            'kind' => Poi::KIND_BASE,
            'address' => 'ul. Bazowa 1',
            'lat' => 52.2, 'lng' => 21.0,
        ]);

        $poi->delete();

        $this->assertNotNull($poi->refresh()->deleted_at);
        $this->assertSame(0, Poi::query()->count());
        $this->assertSame(1, Poi::query()->withTrashed()->count());
    }

    public function test_poi_active_scope_filters_inactive(): void
    {
        Poi::create([
            'id' => (string) Str::ulid(),
            'name' => 'Active',
            'kind' => Poi::KIND_BASE,
            'address' => 'a',
            'lat' => 52.0, 'lng' => 21.0,
            'is_active' => true,
        ]);
        Poi::create([
            'id' => (string) Str::ulid(),
            'name' => 'Inactive',
            'kind' => Poi::KIND_BASE,
            'address' => 'b',
            'lat' => 52.0, 'lng' => 21.0,
            'is_active' => false,
        ]);

        $active = Poi::query()->active()->get();
        $this->assertCount(1, $active);
        $this->assertSame('Active', $active->first()->name);
    }

    public function test_poi_of_kind_scope(): void
    {
        Poi::create([
            'id' => (string) Str::ulid(), 'name' => 'B1',
            'kind' => Poi::KIND_BASE, 'address' => 'a',
            'lat' => 52.0, 'lng' => 21.0,
        ]);
        Poi::create([
            'id' => (string) Str::ulid(), 'name' => 'P1',
            'kind' => Poi::KIND_PARKING, 'address' => 'b',
            'lat' => 52.0, 'lng' => 21.0,
        ]);

        $bases = Poi::query()->ofKind(Poi::KIND_BASE)->get();
        $this->assertCount(1, $bases);
        $this->assertSame('B1', $bases->first()->name);
    }

    private function makeQuote(): Quote
    {
        return Quote::create([
            'id' => (string) Str::ulid(),
            'number' => 'TEST-'.uniqid(),
            'status' => 'draft',
            'customer_name' => 'Anna',
            'pickup_address' => 'A', 'pickup_lat' => 52.0, 'pickup_lng' => 21.0,
            'dropoff_address' => 'B', 'dropoff_lat' => 50.0, 'dropoff_lng' => 19.0,
            'preferred_date' => now()->addDays(7)->toDateString(),
            'rate_per_km' => 4.50,
            'base_cost' => 0, 'fuel_surcharge' => 0,
            'minimum_adjustment' => 0,
            'net_total' => 0, 'vat_rate' => 23.0, 'vat_amount' => 0,
            'gross_total' => 0, 'currency' => 'PLN',
            'distance_km' => 100, 'duration_seconds' => 3600,
            'routing_provider' => 'ors',
        ]);
    }

    private function setUpTenantTables(): void
    {
        Schema::connection('tenant')->create('quotes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 32)->unique();
            $t->string('status', 16)->default('draft');
            $t->string('customer_id', 26)->nullable();
            $t->string('customer_name');
            $t->string('customer_email')->nullable();
            $t->string('customer_phone', 40)->nullable();
            $t->string('customer_company')->nullable();
            $t->string('customer_tax_id', 32)->nullable();
            $t->text('customer_address')->nullable();
            $t->string('pickup_address');
            $t->decimal('pickup_lat', 10, 7);
            $t->decimal('pickup_lng', 10, 7);
            $t->string('dropoff_address');
            $t->decimal('dropoff_lat', 10, 7);
            $t->decimal('dropoff_lng', 10, 7);
            $t->date('preferred_date');
            $t->time('preferred_time')->nullable();
            $t->string('calculation_mode', 16)->default('one_way');
            $t->boolean('round_trip')->default(false);
            $t->boolean('loaded')->default(true);
            $t->unsignedTinyInteger('horses_count')->default(1);
            $t->string('vehicle_id', 26)->nullable();
            $t->string('trailer_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->decimal('distance_km', 10, 2);
            $t->integer('duration_seconds');
            $t->string('routing_provider', 16)->default('manual');
            $t->text('polyline')->nullable();
            $t->decimal('rate_per_km', 6, 2);
            $t->decimal('base_cost', 10, 2);
            $t->decimal('fuel_surcharge', 10, 2)->default(0);
            $t->decimal('extra_horse_fee_snapshot', 10, 2)->default(0);
            $t->json('fixed_fees_snapshot')->nullable();
            $t->decimal('surcharge_percent_snapshot', 5, 2)->nullable();
            $t->decimal('surcharge_amount_snapshot', 10, 2)->nullable();
            $t->json('line_items')->nullable();
            $t->decimal('minimum_adjustment', 10, 2)->default(0);
            $t->decimal('net_total', 10, 2);
            $t->decimal('vat_rate', 4, 2);
            $t->decimal('vat_amount', 10, 2);
            $t->decimal('gross_total', 10, 2);
            $t->string('currency', 3)->default('PLN');
            $t->decimal('exchange_rate_to_pln', 10, 4)->nullable();
            $t->date('exchange_rate_date')->nullable();
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->date('valid_until')->nullable();
            $t->string('accept_token', 64)->nullable();
            $t->string('lead_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->string('pdf_url', 500)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('expired_at')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });

        Schema::connection('tenant')->create('quote_waypoints', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('quote_id', 26);
            $t->unsignedTinyInteger('sort_order')->default(0);
            $t->string('kind', 16)->default('stop');
            $t->string('address');
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->string('poi_id', 26)->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->foreign('quote_id')->references('id')->on('quotes')->cascadeOnDelete();
        });

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
