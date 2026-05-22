<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Transport;

use App\Models\Central\User;
use App\Models\Tenant\Driver;
use App\Services\Tenancy\CurrentDriverResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Smoke test dla driver resolver — sprawdza ze:
 *  - bez auth → null
 *  - auth bez Driver linked → null
 *  - auth + Driver z central_user_id → znajduje
 *  - cache resetuje sie po flush()
 */
class CurrentDriverResolverTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentDriverResolver::flush();

        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_drv_').'.sqlite';
        touch($this->tenantDbPath);
        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        $this->setUpDriversTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_returns_null_when_no_user_logged_in(): void
    {
        $this->assertNull(app(CurrentDriverResolver::class)->current());
    }

    public function test_returns_null_when_user_has_no_driver_record(): void
    {
        $user = User::create([
            'email' => 'driver-'.uniqid().'@example.com',
            'name' => 'Mateusz',
            'password' => bcrypt('secret'),
        ]);
        Auth::login($user);

        $this->assertNull(app(CurrentDriverResolver::class)->current());
    }

    public function test_returns_driver_when_central_user_id_matches(): void
    {
        $user = User::create([
            'email' => 'driver-'.uniqid().'@example.com',
            'name' => 'Mateusz',
            'password' => bcrypt('secret'),
        ]);
        Auth::login($user);

        $driver = Driver::create([
            'id' => (string) Str::ulid(),
            'central_user_id' => $user->id,
            'first_name' => 'Mateusz',
            'last_name' => 'Kowalski',
            'license_number' => 'ABC123',
            'is_active' => true,
        ]);

        $found = app(CurrentDriverResolver::class)->current();
        $this->assertNotNull($found);
        $this->assertSame($driver->id, $found->id);
    }

    private function setUpDriversTable(): void
    {
        Schema::connection('tenant')->create('drivers', function ($t) {
            $t->string('id', 26)->primary();
            $t->string('central_user_id', 26)->nullable();
            $t->string('first_name');
            $t->string('last_name');
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->string('license_number', 60)->nullable();
            $t->json('license_categories')->nullable();
            $t->date('license_expires_at')->nullable();
            $t->boolean('has_animal_transport_cert')->default(false);
            $t->date('animal_transport_cert_expires_at')->nullable();
            $t->boolean('has_adr')->default(false);
            $t->date('adr_expires_at')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->date('hire_date')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
    }
}
