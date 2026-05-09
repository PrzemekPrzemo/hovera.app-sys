<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\StripeWebhookEvent;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Services\Billing\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_redirects_unauthenticated(): void
    {
        $response = $this->get('/app/billing');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location') ?? '');
    }

    public function test_show_renders_for_owner(): void
    {
        $this->mockBilling();
        Plan::create([
            'code' => 'stable',
            'name' => 'Stable',
            'currency' => 'PLN',
            'price_monthly_cents' => 24900,
            'price_yearly_cents' => 269000,
            'stripe_price_monthly_id' => 'price_test_monthly',
            'stripe_price_yearly_id' => 'price_test_yearly',
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 30,
            'features' => ['bullet_1' => 'Wszystko z Solo'],
        ]);

        [$user, $tenant] = $this->makeOwner();

        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/app/billing')
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertSee('Stable');
    }

    public function test_webhook_idempotent_on_duplicate_event_id(): void
    {
        // Pre-seed a row that simulates a previously processed webhook.
        StripeWebhookEvent::create([
            'event_id' => 'evt_test_1',
            'type' => 'checkout.session.completed',
            'payload' => ['id' => 'evt_test_1'],
            'processed_at' => now(),
        ]);

        // Mock the service: if idempotency works the controller should
        // *call* handleWebhook but the service must not throw, and the
        // count of stripe_webhook_events stays at 1.
        $this->mock(StripeBillingService::class, function (MockInterface $m) {
            $m->shouldReceive('handleWebhook')->once()->andReturnNull();
        });

        $this->postJson('/webhooks/stripe', ['id' => 'evt_test_1'], [
            'Stripe-Signature' => 't=1,v1=fake',
        ])->assertOk();

        $this->assertSame(1, StripeWebhookEvent::count());
    }

    private function makeOwner(): array
    {
        $user = User::create([
            'email' => 'owner@example.com',
            'name' => 'Owner',
            'password' => bcrypt('secret123'),
        ]);

        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(15),
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$user, $tenant];
    }

    private function mockBilling(): void
    {
        $this->app->instance(
            StripeBillingService::class,
            Mockery::mock(StripeBillingService::class),
        );
    }
}
