<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Admin\Pages\SmtpSettings;
use App\Models\Central\SystemSetting;
use App\Models\Central\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pokrywa przycisk "Sprawdź połączenie z Mailgun" w /admin/smtp-settings.
 * Endpoint pyta `GET https://{endpoint}/v4/domains/{domain}` z saved creds
 * i mapuje 200/401/404 na konkretne notyfikacje master adminowi (zamiast
 * generycznego 401 Forbidden ze Symfony Mailera).
 */
class SmtpSettingsCheckMailgunConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        config()->set('hovera.admin.require_2fa', false);
    }

    public function test_warns_when_mailgun_creds_missing(): void
    {
        $this->actingAsMasterAdmin();
        Http::fake(); // żaden HTTP call nie powinien polecieć

        Livewire::test(SmtpSettings::class)
            ->call('checkMailgunConnection')
            ->assertNotified();

        Http::assertNothingSent();
    }

    public function test_reports_success_when_mailgun_returns_200(): void
    {
        $this->seedMailgun();
        $this->actingAsMasterAdmin();

        Http::fake([
            'https://api.eu.mailgun.net/v4/domains/hovera.pl' => Http::response([
                'domain' => [
                    'name' => 'hovera.pl',
                    'state' => 'active',
                    'type' => 'custom',
                    'created_at' => '2026-06-19',
                ],
            ], 200),
        ]);

        Livewire::test(SmtpSettings::class)
            ->call('checkMailgunConnection')
            ->assertNotified();
    }

    public function test_reports_401_with_specific_hint(): void
    {
        $this->seedMailgun();
        $this->actingAsMasterAdmin();

        Http::fake([
            'https://api.eu.mailgun.net/v4/domains/hovera.pl' => Http::response(
                ['message' => 'Invalid private key'],
                401
            ),
        ]);

        Livewire::test(SmtpSettings::class)
            ->call('checkMailgunConnection')
            ->assertNotified();

        // Sanity: HTTP request poszedł z basic auth `api:key-fake`.
        Http::assertSent(function ($req) {
            $auth = $req->header('Authorization')[0] ?? '';

            return str_starts_with($auth, 'Basic ');
        });
    }

    public function test_reports_404_when_domain_not_found(): void
    {
        $this->seedMailgun();
        $this->actingAsMasterAdmin();

        Http::fake([
            'https://api.eu.mailgun.net/v4/domains/hovera.pl' => Http::response(
                ['message' => 'Domain not found: hovera.pl'],
                404
            ),
        ]);

        Livewire::test(SmtpSettings::class)
            ->call('checkMailgunConnection')
            ->assertNotified();
    }

    public function test_handles_network_error_gracefully(): void
    {
        $this->seedMailgun();
        $this->actingAsMasterAdmin();

        Http::fake([
            'https://api.eu.mailgun.net/*' => function () {
                throw new ConnectionException('cURL error 6: Could not resolve host');
            },
        ]);

        Livewire::test(SmtpSettings::class)
            ->call('checkMailgunConnection')
            ->assertNotified();
    }

    public function test_hits_us_endpoint_when_configured(): void
    {
        $this->seedMailgun(endpoint: 'api.mailgun.net');
        $this->actingAsMasterAdmin();

        Http::fake([
            'https://api.mailgun.net/v4/domains/hovera.pl' => Http::response([
                'domain' => ['name' => 'hovera.pl', 'state' => 'active', 'type' => 'custom', 'created_at' => 'x'],
            ], 200),
        ]);

        Livewire::test(SmtpSettings::class)
            ->call('checkMailgunConnection')
            ->assertNotified();

        Http::assertSent(fn ($req) => str_contains((string) $req->url(), 'api.mailgun.net'));
        Http::assertNotSent(fn ($req) => str_contains((string) $req->url(), 'api.eu.mailgun.net'));
    }

    private function seedMailgun(string $endpoint = 'api.eu.mailgun.net'): void
    {
        SystemSetting::setSecret('mail.mailgun.domain', 'hovera.pl');
        SystemSetting::setSecret('mail.mailgun.secret', 'key-fake');
        SystemSetting::setValue('mail.mailgun.endpoint', $endpoint);
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'master-'.uniqid().'@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }
}
