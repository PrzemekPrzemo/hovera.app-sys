<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Models\Central\AuditLogMaster;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Tenancy\Provisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Akceptacja regulaminu na signupie jest prawnym wymaganiem RODO art. 7
 * (możliwość udowodnienia zgody). Test asercje:
 *   1. brak terms checkbox blokuje submit (validation error)
 *   2. terms checked: tenant.terms_accepted_at + terms_version są zapisane
 *   3. audit_log_master ma wpis 'tenant.terms_accepted' z payloadem
 *   4. dla type=transporter w label widoczny jest link do marketplace regulaminu
 */
class SignupTermsAcceptanceTest extends TestCase
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

        // Plan 'pro' jest defaultem dla type=stable w CreateTenant.
        Plan::create([
            'code' => 'pro',
            'audience' => 'stable',
            'name' => 'Pro',
            'currency' => 'PLN',
        ]);
        Plan::create([
            'code' => 'transport_pro',
            'audience' => 'transporter',
            'name' => 'Transport Pro',
            'currency' => 'PLN',
        ]);
    }

    public function test_signup_rejects_submission_without_terms_checkbox(): void
    {
        $response = $this->post('/signup', [
            'name' => 'Stajnia Test',
            'slug' => 'stajnia-test-'.uniqid(),
            'type' => 'stable',
            'owner_name' => 'Jan Kowalski',
            'owner_email' => 'jan@example.com',
            // brak: 'terms' => '1'
        ]);

        $response->assertSessionHasErrors(['terms']);
        $this->assertSame(0, Tenant::count());
    }

    public function test_signup_with_terms_persists_acceptance_metadata(): void
    {
        $response = $this->post('/signup', [
            'name' => 'Stajnia Test',
            'slug' => $slug = 'stajnia-test-'.uniqid(),
            'type' => 'stable',
            'owner_name' => 'Jan Kowalski',
            'owner_email' => 'jan@example.com',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('signup.thanks', ['slug' => $slug]));

        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->assertNotNull($tenant->terms_accepted_at);
        $this->assertSame(
            (string) config('hovera.legal.terms_version'),
            $tenant->terms_version,
        );
    }

    public function test_signup_writes_audit_log_for_terms_acceptance(): void
    {
        $this->post('/signup', [
            'name' => 'Stajnia Audit',
            'slug' => $slug = 'audit-'.uniqid(),
            'type' => 'stable',
            'owner_name' => 'Audit Anna',
            'owner_email' => 'audit@example.com',
            'terms' => '1',
        ])->assertRedirect();

        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $logs = AuditLogMaster::where('action', 'tenant.terms_accepted')
            ->where('tenant_id', $tenant->id)
            ->get();

        $this->assertCount(1, $logs);
        $payload = $logs->first()->payload;
        $this->assertSame($tenant->terms_version, $payload['version']);
        $this->assertSame('audit@example.com', $payload['owner_email']);
        $this->assertFalse($payload['accepted_marketplace']);
    }

    public function test_transporter_signup_audit_marks_marketplace_accepted(): void
    {
        $this->post('/signup', [
            'name' => 'Transport Co',
            'slug' => $slug = 'transp-'.uniqid(),
            'type' => 'transporter',
            'owner_name' => 'Driver Dan',
            'owner_email' => 'driver@example.com',
            'terms' => '1',
        ])->assertRedirect();

        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $log = AuditLogMaster::where('action', 'tenant.terms_accepted')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        // Transporter signup → marketplace regulamin też zaakceptowany (label
        // w form.blade.php pokazuje extra link dla type=transporter).
        $this->assertTrue($log->payload['accepted_marketplace']);
        $this->assertSame('transporter', $log->payload['tenant_type']);
    }

    public function test_transporter_signup_form_shows_marketplace_terms_link(): void
    {
        // Form blade na podstawie type=transporter renderuje rozszerzony
        // terms label z linkiem do /regulamin-marketplace.
        $response = $this->get('/signup?type=transporter');

        $response->assertOk();
        $response->assertSee('/regulamin-marketplace', false);
        $response->assertSee('regulamin marketplace', false);
    }

    public function test_stable_signup_form_does_not_show_marketplace_link(): void
    {
        // Stable signup → tylko podstawowy regulamin + privacy. Bez
        // marketplace suffix — stajnie używają tylko core SaaS, nie marketplace
        // pośrednictwa transportu (od strony "transporter side").
        $response = $this->get('/signup?type=stable');

        $response->assertOk();
        $response->assertDontSee('/regulamin-marketplace', false);
    }
}
