<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Filament\Admin\Resources\PlanResource\Pages\EditPlan;
use App\Models\Central\AuditLogMaster;
use App\Models\Central\Plan;
use App\Models\Central\User;
use App\Services\Billing\StripeProductCreator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery;
use Stripe\Price;
use Stripe\Product;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * Header action "Utwórz Product + Cenę w Stripe" — sanity check że
 * service jest poprawnie wołany, Stripe IDs trafiają na Plan, audit log
 * zapisuje wpis, Enterprise jest pomijany, a idempotencja działa.
 */
class PlanStripeWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_stripe_wizard_creates_product_and_prices_and_persists_ids(): void
    {
        $this->actingAsMasterAdmin();

        $plan = $this->makePlan([
            'code' => 'transport-start-test-'.uniqid(),
            'currency' => 'PLN',
            'price_monthly_cents' => 25000,
            'price_yearly_cents' => 270000,
            'prices_per_currency' => [
                'EUR' => ['monthly_cents' => 5900, 'yearly_cents' => 63700],
            ],
        ]);

        $stripe = $this->makeStripeMock(productId: 'prod_TEST123', priceIds: [
            'pln_month' => 'price_pln_m',
            'pln_year' => 'price_pln_y',
            'eur_month' => 'price_eur_m',
            'eur_year' => 'price_eur_y',
        ]);

        $creator = app(StripeProductCreator::class);
        $creator->setClient($stripe);
        $this->app->instance(StripeProductCreator::class, $creator);

        Livewire::test(EditPlan::class, ['record' => $plan->getKey()])
            ->callAction('stripe_wizard');

        $plan->refresh();
        $this->assertSame('price_pln_m', $plan->stripe_price_monthly_id);
        $this->assertSame('price_pln_y', $plan->stripe_price_yearly_id);
        $this->assertSame('price_eur_m', $plan->prices_per_currency['EUR']['stripe_price_monthly_id']);
        $this->assertSame('price_eur_y', $plan->prices_per_currency['EUR']['stripe_price_yearly_id']);

        $audit = AuditLogMaster::query()
            ->where('action', 'plan.stripe_created')
            ->where('target_id', $plan->id)
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('prod_TEST123', $audit->payload['product_id']);
    }

    public function test_stripe_wizard_is_idempotent_and_hidden_when_already_created(): void
    {
        $this->actingAsMasterAdmin();

        $plan = $this->makePlan([
            'code' => 'already-stripe-'.uniqid(),
            'stripe_price_monthly_id' => 'price_existing',
            'stripe_price_yearly_id' => 'price_existing_y',
        ]);

        Livewire::test(EditPlan::class, ['record' => $plan->getKey()])
            ->assertActionHidden('stripe_wizard');
    }

    public function test_stripe_wizard_skips_enterprise_plan(): void
    {
        $this->actingAsMasterAdmin();

        $plan = $this->makePlan([
            'code' => 'transport-enterprise-test-'.uniqid(),
            'price_monthly_cents' => 0,
            'price_yearly_cents' => 0,
            'features' => ['is_custom_pricing' => true, 'marketing_cta' => 'contact_sales'],
        ]);

        $stripe = Mockery::mock(StripeClient::class);
        // Nie powinno być żadnego calla do Stripe — Mockery rzuci jeśli będzie.
        $creator = app(StripeProductCreator::class);
        $creator->setClient($stripe);
        $this->app->instance(StripeProductCreator::class, $creator);

        Livewire::test(EditPlan::class, ['record' => $plan->getKey()])
            ->callAction('stripe_wizard');

        $plan->refresh();
        $this->assertNull($plan->stripe_price_monthly_id);
        // Brak wpisu w audit log dla enterprise skip.
        $this->assertSame(
            0,
            AuditLogMaster::query()
                ->where('action', 'plan.stripe_created')
                ->where('target_id', $plan->id)
                ->count(),
        );
    }

    public function test_service_directly_creates_correct_payload_for_base_currency_only(): void
    {
        $plan = $this->makePlan([
            'code' => 'srv-direct-'.uniqid(),
            'currency' => 'PLN',
            'price_monthly_cents' => 99900,
            'price_yearly_cents' => 1079000,
            'prices_per_currency' => null,
        ]);

        $stripe = $this->makeStripeMock(productId: 'prod_SRV1', priceIds: [
            'pln_month' => 'price_srv_m',
            'pln_year' => 'price_srv_y',
        ]);

        $creator = new StripeProductCreator;
        $creator->setClient($stripe);

        $result = $creator->createForPlan($plan);

        $this->assertSame('prod_SRV1', $result['product_id']);
        $this->assertSame('PLN', $result['base_currency']);
        $this->assertArrayHasKey('PLN', $result['prices']);
        $this->assertSame('price_srv_m', $result['prices']['PLN']['monthly']);
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'stripe-wizard-'.uniqid().'@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'code' => 'wizard-plan-'.uniqid(),
            'name' => 'Wizard Plan',
            'audience' => TenantType::Transporter->value,
            'currency' => 'PLN',
            'price_monthly_cents' => 25000,
            'price_yearly_cents' => 270000,
            'sort_order' => 100,
            'is_active' => true,
            'is_public' => true,
        ], $overrides));
    }

    /**
     * Bardzo lekki mock — Stripe SDK ma typowe usługi `products` i `prices`
     * jako publiczne properties; podmienimy je przez Mockery'ego allowing get.
     *
     * @param  array<string,string>  $priceIds
     */
    private function makeStripeMock(string $productId, array $priceIds): StripeClient
    {
        $product = Product::constructFrom(['id' => $productId]);

        $productSvc = Mockery::mock(ProductService::class);
        $productSvc->shouldReceive('create')->andReturn($product);

        $priceSvc = Mockery::mock(PriceService::class);
        $priceSvc->shouldReceive('create')->andReturnUsing(function (array $args) use (&$priceIds): Price {
            // Wybierz next price ID — kolejność: PLN m, PLN y, EUR m, EUR y, ...
            $key = array_key_first($priceIds);
            $id = $priceIds[$key];
            unset($priceIds[$key]);

            return Price::constructFrom(['id' => $id]);
        });

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->products = $productSvc;
        $stripe->prices = $priceSvc;

        return $stripe;
    }
}
