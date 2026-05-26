<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Central\Invoice;
use App\Models\Central\Plan;
use App\Models\Central\StripeWebhookEvent;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Services\Billing\PayUService;
use App\Services\Billing\StripeBillingService;
use App\Services\TenantAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // PayUService singleton w AppServiceProvider rzuca w konstruktorze
        // gdy creds puste. W tej suite mockujemy domyślnie żeby DI nie
        // explodował przy każdym `/app/billing` requesta. Test-konkretne
        // expectations dorzokamy override'em w teście.
        $this->app->instance(PayUService::class, Mockery::mock(PayUService::class));

        // TenantAuditLogger pisze do `audit_log` w tenant DB, której nie
        // mamy w tej suite. Mock no-op żeby kontroler nie crashował.
        $this->app->instance(
            TenantAuditLogger::class,
            Mockery::mock(TenantAuditLogger::class)->shouldIgnoreMissing(),
        );
    }

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

    public function test_payu_checkout_creates_subscription_and_invoice_with_onboarding_fee(): void
    {
        $this->mockBilling();
        $payu = Mockery::mock(PayUService::class);
        $payu->shouldReceive('createRecurringSetup')
            ->once()
            ->andReturn('https://secure.snd.payu.com/setup/1');
        $this->app->instance(PayUService::class, $payu);

        $plan = Plan::create([
            'code' => 'pro',
            'name' => 'Pro',
            'currency' => 'PLN',
            'price_monthly_cents' => 39800,
            'onboarding_fee_cents' => 10000,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 50,
        ]);

        [$user, $tenant] = $this->makeOwner();

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->post('/app/billing/payu/checkout', [
                'plan_code' => 'pro',
                'period' => 'monthly',
            ]);

        $response->assertRedirect('https://secure.snd.payu.com/setup/1');

        $sub = Subscription::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($sub);
        $this->assertSame('incomplete', $sub->status);
        $this->assertSame('monthly', $sub->billing_cycle);
        $this->assertSame($plan->id, $sub->plan_id);

        $invoice = Invoice::query()->where('subscription_id', $sub->id)->first();
        $this->assertNotNull($invoice);
        // Setup total = price_monthly (39800) + onboarding_fee (10000) = 49800.
        $this->assertSame(49800, $invoice->total_cents);
        $this->assertSame('open', $invoice->status);
    }

    public function test_payu_checkout_rejects_unknown_plan(): void
    {
        $this->mockBilling();
        $this->app->instance(PayUService::class, Mockery::mock(PayUService::class));

        [$user, $tenant] = $this->makeOwner();

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->from('/app/billing')
            ->post('/app/billing/payu/checkout', [
                'plan_code' => 'nonexistent',
                'period' => 'monthly',
            ]);

        $response->assertRedirect('/app/billing');
        $response->assertSessionHasErrors('plan');
        $this->assertSame(0, Subscription::query()->count());
    }

    public function test_payu_cancel_clears_token_and_keeps_period_active(): void
    {
        $this->mockBilling();
        $this->app->instance(PayUService::class, Mockery::mock(PayUService::class));

        [$user, $tenant] = $this->makeOwner();
        $plan = Plan::create([
            'code' => 'pro',
            'name' => 'Pro',
            'currency' => 'PLN',
            'price_monthly_cents' => 39800,
            'is_active' => true,
        ]);
        $sub = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subWeek(),
            'current_period_end' => now()->addWeeks(3),
            'payu_recurring_token' => 'TOK-12345',
            'payu_card_mask' => '**** 1234',
            'payu_card_brand' => 'VISA',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->post('/app/billing/payu/cancel');

        $response->assertRedirect('/app/billing');

        $sub->refresh();
        $this->assertNull($sub->payu_recurring_token);
        $this->assertNotNull($sub->cancelled_at);
        // current_period_end pozostaje — dostęp do końca okresu.
        $this->assertTrue($sub->current_period_end->isFuture());
    }

    public function test_show_renders_payu_card_section_for_active_subscription(): void
    {
        $this->mockBilling();

        [$user, $tenant] = $this->makeOwner();
        $plan = Plan::create([
            'code' => 'pro',
            'name' => 'Pro',
            'currency' => 'PLN',
            'price_monthly_cents' => 39800,
            'is_active' => true,
            'is_public' => true,
        ]);
        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'current_period_start' => now()->subWeek(),
            'current_period_end' => now()->addWeeks(3),
            'payu_recurring_token' => 'TOK-12345',
            'payu_card_mask' => '**** **** **** 1234',
            'payu_card_brand' => 'VISA',
            'payu_card_expires_at' => now()->addYears(3),
        ]);

        $this->actingAs($user)
            ->withSession(['current_tenant_id' => $tenant->id])
            ->get('/app/billing')
            ->assertOk()
            ->assertSee('VISA')
            ->assertSee('**** **** **** 1234');
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
