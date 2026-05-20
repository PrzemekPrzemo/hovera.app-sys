<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments;

use App\Enums\QuoteStatus;
use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Wspólny setup dla testów direct-charge payments MVP. Patrz docs/TRANSPORT.md §13.
 *
 * Tworzy:
 *   - sqlite tenant DB w temp dir
 *   - tabelę `quotes` z payments fields (payment_url etc.)
 *   - tabelę `transport_settings` z payments defaults
 *   - mock TenantManager żeby setCurrent() nie próbował przepiąć
 *     connection na MySQL (jak w produkcji), bo wysadziłoby sqlite test.
 */
trait PaymentTestTenantSetup
{
    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUpTenantWithPayments(): void
    {
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_pay_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpQuotesTable();
        $this->setUpTransportSettingsTable();
        $this->tenant = $this->makeTenant();

        $heldTenant = null;
        $this->mock(TenantManager::class, function ($m) use (&$heldTenant) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$heldTenant) {
                $heldTenant = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $heldTenant);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(function () use (&$heldTenant) {
                if ($heldTenant === null) {
                    throw new \RuntimeException('No tenant initialised.');
                }

                return $heldTenant;
            });
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $heldTenant !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$heldTenant) {
                $heldTenant = null;
            });
        });

        $this->mock(TenantAuditLogger::class, function ($m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    protected function tearDownTenantWithPayments(): void
    {
        @unlink($this->tenantDbPath);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeQuote(QuoteStatus $status, array $overrides = []): Quote
    {
        return Quote::create(array_merge([
            'id' => (string) Str::ulid(),
            'number' => 'OF/2026/05/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'pickup_address' => 'Warszawa, Marymoncka 1',
            'pickup_lat' => 52.2818, 'pickup_lng' => 20.9921,
            'dropoff_address' => 'Kraków, Krakusa 1',
            'dropoff_lat' => 50.0413, 'dropoff_lng' => 19.9362,
            'preferred_date' => '2026-06-15',
            'distance_km' => 295.50, 'duration_seconds' => 13_500,
            'routing_provider' => 'mapbox',
            'rate_per_km' => 4.50, 'base_cost' => 1329.75,
            'fuel_surcharge' => 48.02, 'minimum_adjustment' => 0,
            'net_total' => 1377.77, 'vat_rate' => 23.00,
            'vat_amount' => 316.89, 'gross_total' => 1694.66,
            'currency' => 'PLN',
            'sent_at' => now(),
        ], $overrides));
    }

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma Testowa Sp. z o.o.',
            'legal_name' => 'Firma Testowa Spółka z o.o.',
            'type' => TenantType::Transporter,
            'db_name' => 'firma_'.uniqid(),
            'db_username' => 'firma_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'branding' => ['contact_email' => 'biuro@firma.test', 'contact_phone' => '+48 600 000 000'],
        ]);
    }

    private function setUpQuotesTable(): void
    {
        Schema::connection('tenant')->create('quotes', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('number', 32)->unique();
            $t->string('status', 16)->default('draft');
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
            $t->boolean('round_trip')->default(false);
            $t->boolean('loaded')->default(true);
            $t->unsignedTinyInteger('horses_count')->default(1);
            $t->string('vehicle_id', 26)->nullable();
            $t->string('driver_id', 26)->nullable();
            $t->decimal('distance_km', 8, 2);
            $t->unsignedInteger('duration_seconds');
            $t->string('routing_provider', 16);
            $t->text('polyline')->nullable();
            $t->decimal('rate_per_km', 6, 2);
            $t->decimal('base_cost', 10, 2);
            $t->decimal('fuel_surcharge', 10, 2)->default(0);
            $t->decimal('extra_horse_fee_snapshot', 10, 2)->default(0);
            $t->json('fixed_fees_snapshot')->nullable();
            $t->decimal('surcharge_percent_snapshot', 5, 2)->nullable();
            $t->decimal('surcharge_amount_snapshot', 10, 2)->nullable();
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
            $t->string('accept_token', 64)->nullable()->unique();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->timestamp('expired_at')->nullable();
            $t->timestamp('withdrawn_at')->nullable();
            $t->string('lead_id', 26)->nullable();
            $t->string('response_id', 26)->nullable();
            $t->string('pdf_url')->nullable();
            // Payments MVP — patrz docs/TRANSPORT.md §13.
            $t->string('payment_url', 2048)->nullable();
            $t->string('payment_method_label', 80)->nullable();
            $t->timestamp('payment_completed_at')->nullable();
            $t->text('payment_notes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
            $t->timestamp('deleted_at')->nullable();
        });
    }

    private function setUpTransportSettingsTable(): void
    {
        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
            $t->decimal('extra_horse_fee_default', 8, 2)->default(0);
            $t->json('fixed_fees_default')->nullable();
            $t->decimal('surcharge_percent_default', 5, 2)->nullable();
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->string('fuel_calculation_mode', 16)->default('surcharge');
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('manual_fuel_price_pln', 5, 2)->nullable();
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->json('routing_provider')->nullable();
            // Payments defaults — patrz docs/TRANSPORT.md §13.
            $t->string('default_payment_url_template', 2048)->nullable();
            $t->string('default_payment_method_label', 80)->nullable();
            $t->text('payment_instructions')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });
    }
}
