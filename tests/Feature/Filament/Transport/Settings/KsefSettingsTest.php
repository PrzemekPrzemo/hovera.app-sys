<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Transport\Settings;

use App\Domain\Transport\Ksef\TransporterKsefService;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransportSettings;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Testy „end-to-end" warstwy konfiguracji KSeF — bez Livewire harnessu
 * Filamenta, bo to nadmiar dla MVP. Sprawdzamy bezpośrednio kontrakt
 * modelu TransportSettings (set/get tokenu szyfrowany) i serwisu
 * testConnection.
 */
class KsefSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantDbPath;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantDbPath = tempnam(sys_get_temp_dir(), 'hovera_ksef_set_').'.sqlite';
        touch($this->tenantDbPath);

        config()->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => $this->tenantDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->app->make('db')->purge('tenant');

        Schema::connection('tenant')->create('transport_settings', function ($t) {
            $t->id();
            $t->decimal('rate_per_km', 6, 2)->default(4.50);
            $t->decimal('rate_per_km_loaded', 6, 2)->nullable();
            $t->decimal('minimum_charge', 8, 2)->default(800.00);
            $t->decimal('fuel_consumption_l_per_100km', 5, 2)->default(32.5);
            $t->boolean('fuel_surcharge_enabled')->default(true);
            $t->decimal('fuel_base_price_pln', 5, 2)->default(7.00);
            $t->decimal('manual_fuel_price_pln', 5, 2)->nullable();
            $t->decimal('vat_rate', 4, 2)->default(23.00);
            $t->string('currency', 3)->default('PLN');
            $t->json('routing_provider')->nullable();
            $t->text('ksef_token_encrypted')->nullable();
            $t->string('ksef_environment', 16)->default('test');
            $t->string('ksef_nip', 16)->nullable();
            $t->boolean('ksef_enabled')->default(false);
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('updated_at')->useCurrent();
        });

        $this->tenant = Tenant::create([
            'slug' => 't-'.uniqid(),
            'name' => 'Firma',
            'legal_name' => 'Firma Sp. z o.o.',
            'tax_id' => '1234567890',
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.uniqid(),
            'db_username' => 't_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
            'country' => 'PL',
        ]);

        $held = $this->tenant;
        $this->mock(TenantManager::class, function ($m) use (&$held) {
            $m->shouldReceive('setCurrent')->andReturnUsing(function ($t) use (&$held) {
                $held = $t;
            });
            $m->shouldReceive('current')->andReturnUsing(fn () => $held);
            $m->shouldReceive('tenantOrFail')->andReturnUsing(function () use (&$held) {
                if ($held === null) {
                    throw new \RuntimeException('No tenant');
                }

                return $held;
            });
            $m->shouldReceive('hasTenant')->andReturnUsing(fn () => $held !== null);
            $m->shouldReceive('forget')->andReturnUsing(function () use (&$held) {
                $held = null;
            });
        });

        // Fake klucz publiczny MF — testConnection robi pełen handshake.
        Storage::fake('local');
        $this->publishFakeKsefPublicKey('test');
    }

    private function publishFakeKsefPublicKey(string $env): void
    {
        $keyConfig = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $res = openssl_pkey_new($keyConfig);
        $details = openssl_pkey_get_details($res);
        Storage::disk('local')->put('ksef/public-key-'.$env.'.pem', $details['key']);
    }

    protected function tearDown(): void
    {
        @unlink($this->tenantDbPath);
        parent::tearDown();
    }

    public function test_admin_can_enter_ksef_token(): void
    {
        $settings = TransportSettings::current();
        $settings->setKsefToken('plaintext-token');
        $settings->ksef_enabled = true;
        $settings->ksef_nip = '1234567890';
        $settings->save();

        $reloaded = TransportSettings::current();
        $this->assertSame('plaintext-token', $reloaded->getKsefToken());
        $this->assertTrue((bool) $reloaded->ksef_enabled);
    }

    public function test_token_is_encrypted_at_rest(): void
    {
        $settings = TransportSettings::current();
        $settings->setKsefToken('plaintext-token-XYZ');
        $settings->save();

        // Czytamy raw (bez accessora) prosto z bazy.
        $raw = \DB::connection('tenant')->table('transport_settings')->value('ksef_token_encrypted');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('plaintext-token-XYZ', (string) $raw);

        // Ale Crypt::decryptString musi odzyskać.
        $this->assertSame('plaintext-token-XYZ', Crypt::decryptString((string) $raw));
    }

    public function test_setting_empty_token_disables_integration(): void
    {
        $settings = TransportSettings::current();
        $settings->setKsefToken('was-something');
        $settings->ksef_enabled = true;
        $settings->save();

        $settings->refresh();
        $settings->setKsefToken('');
        $settings->save();

        $this->assertNull($settings->getKsefToken());
        $this->assertFalse((bool) $settings->ksef_enabled);
    }

    public function test_test_connection_action_runs_full_handshake(): void
    {
        $settings = TransportSettings::current();
        $settings->setKsefToken('working-token');
        $settings->ksef_enabled = true;
        $settings->ksef_nip = '1234567890';
        $settings->ksef_environment = 'test';
        $settings->save();

        // testConnection() force-refresh handshake — challenge + InitToken.
        Http::fake([
            '*/Session/AuthorisationChallenge' => Http::response([
                'challenge' => 'CHL-1',
                'timestamp' => now()->toIso8601String(),
            ], 200),
            '*/Session/InitToken' => Http::response([
                'sessionToken' => [
                    'token' => 'sess-from-mf',
                    'expirationDate' => now()->addHours(2)->toIso8601String(),
                ],
            ], 200),
        ]);

        $result = app(TransporterKsefService::class)->testConnection();

        $this->assertTrue($result['success'], 'testConnection should succeed; got: '.($result['message'] ?? ''));
        Http::assertSent(fn ($req) => str_contains($req->url(), '/Session/AuthorisationChallenge'));
        Http::assertSent(fn ($req) => str_contains($req->url(), '/Session/InitToken'));
    }

    public function test_test_connection_returns_friendly_message_when_token_rejected_by_mf(): void
    {
        $settings = TransportSettings::current();
        $settings->setKsefToken('bad-token');
        $settings->ksef_enabled = true;
        $settings->ksef_nip = '1234567890';
        $settings->ksef_environment = 'test';
        $settings->save();

        Http::fake([
            '*/Session/AuthorisationChallenge' => Http::response(['error' => 'Invalid NIP'], 400),
        ]);

        $result = app(TransporterKsefService::class)->testConnection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('KSeF', $result['message']);
        $this->assertStringNotContainsString('bad-token', $result['message']);
    }

    public function test_test_connection_fails_with_friendly_message_when_not_configured(): void
    {
        $result = app(TransporterKsefService::class)->testConnection();

        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
        // Nie wycieka żadnego tokenu (bo go nie ma) ani ścieżki / stack trace.
        $this->assertStringNotContainsString('Exception', (string) $result['message']);
    }
}
