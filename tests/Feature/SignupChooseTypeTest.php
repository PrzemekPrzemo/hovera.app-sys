<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantType;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Tenancy\Provisioner;
use Database\Seeders\TransportPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SignupChooseTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(Provisioner::class, function (MockInterface $m) {
            $m->shouldReceive('makeIdentifiers')->andReturn([
                'db_name' => 'hovera_t_test',
                'db_user' => 'hovera_t_test',
            ]);
            $m->shouldReceive('generatePassword')->andReturn('PASSWORD123456789');
            $m->shouldReceive('provision')->andReturnNull();
            $m->shouldReceive('destroy')->andReturnNull();
        });
    }

    public function test_signup_without_type_shows_chooser(): void
    {
        $this->get('/signup')
            ->assertOk()
            ->assertSee('hovera')
            ->assertSee('type=stable', false)
            ->assertSee('type=transporter', false);
    }

    public function test_signup_with_type_stable_shows_stable_form(): void
    {
        $response = $this->get('/signup?type=stable');

        $response->assertOk();
        $response->assertSee('value="stable"', false);
    }

    public function test_signup_with_type_transporter_shows_transporter_form(): void
    {
        $response = $this->get('/signup?type=transporter');

        $response->assertOk();
        $response->assertSee('value="transporter"', false);
    }

    public function test_signup_with_invalid_type_falls_back_to_chooser(): void
    {
        $this->get('/signup?type=garbage')
            ->assertOk()
            ->assertSee('type=stable', false);
    }

    public function test_create_tenant_action_defaults_to_stable_when_type_missing(): void
    {
        // Default plan for stable is 'pro' — seed minimal Plan rows.
        Plan::create([
            'code' => 'pro',
            'audience' => 'stable',
            'name' => 'Pro',
            'currency' => 'PLN',
        ]);

        $action = $this->app->make(CreateTenant::class);
        $tenant = $action->execute([
            'slug' => 'no-type-'.uniqid(),
            'name' => 'No Type',
        ]);

        $this->assertSame(TenantType::Stable, $tenant->type);
    }

    public function test_create_tenant_action_creates_transporter_with_transport_start_plan(): void
    {
        TransportPlansSeeder::seed();
        Plan::create([
            'code' => 'pro',
            'audience' => 'stable',
            'name' => 'Pro',
            'currency' => 'PLN',
        ]);

        $action = $this->app->make(CreateTenant::class);
        $tenant = $action->execute([
            'slug' => 'company-'.uniqid(),
            'name' => 'Transport Co',
            'type' => 'transporter',
        ]);

        $this->assertSame(TenantType::Transporter, $tenant->type);
        $this->assertNotNull($tenant->plan);
        // Marketing spec (2026-05-18): default plan for transporters
        // bumped from `transport_pro` (old 349 PLN) to `transport_start`
        // (new 250 PLN). Patrz docs/TRANSPORT.md §15.5.
        $this->assertSame('transport_start', $tenant->plan->code);

        // Trial caps for stable should NOT be set for transporter.
        $this->assertNull($tenant->trial_max_horses);
        $this->assertNull($tenant->trial_max_clients);

        // Marketing spec: trial NIE startuje od signupu — startuje od
        // pozytywnej weryfikacji dokumentów. Patrz Tenant::startTrialOnVerification().
        $this->assertNull($tenant->trial_ends_at,
            'Transporter trial must NOT start at signup — only after verification');
        $this->assertSame('provisioning', $tenant->status,
            'Transporter sits in provisioning until verified');
    }
}
