<?php

declare(strict_types=1);

namespace Tests\Feature\Transport\Payments\Stripe;

use App\Domain\Transport\Payments\Stripe\TransporterStripeConnectService;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Feature\Transport\Payments\PaymentTestTenantSetup;
use Tests\TestCase;

/**
 * Regression: trasy /transport/stripe/connect/* NIE mogą 500-ować, gdy
 * STRIPE_SECRET jest pusty. TransporterStripeConnectService rzuca przy
 * resolve (pusty secret) — wcześniej był wstrzykiwany w konstruktorze
 * kontrolera, więc każda trasa padała 500 zanim zadziałał try/catch.
 * Teraz serwis jest rozwiązywany leniwie w akcjach → przyjazny redirect.
 */
class StripeConnectControllerResilienceTest extends TestCase
{
    use PaymentTestTenantSetup;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantWithPayments();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantWithPayments();
        parent::tearDown();
    }

    public function test_onboard_redirects_gracefully_when_stripe_unconfigured(): void
    {
        // Serwis rzuca przy resolve (symulacja pustego STRIPE_SECRET).
        $this->app->bind(TransporterStripeConnectService::class, function () {
            throw new \RuntimeException('Stripe not configured');
        });

        $owner = User::create(['name' => 'Tran Owner', 'email' => 'owner@trans.test', 'password' => bcrypt('x')]);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // Stały kontekst tenanta dla guard() — current() zwraca naszą stajnię.
        $tenant = $this->tenant;
        $this->instance(TenantManager::class, Mockery::mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('setCurrent')->andReturnNull();
            $m->shouldReceive('forget')->andReturnNull();
        }));

        $this->actingAs($owner)
            ->get('/transport/stripe/connect/onboard')
            ->assertRedirect('/transport/settings');
    }
}
