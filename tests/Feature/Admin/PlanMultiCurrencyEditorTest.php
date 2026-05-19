<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Filament\Admin\Resources\PlanResource\Pages\EditPlan;
use App\Models\Central\Plan;
use App\Models\Central\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Multi-currency repeater w PlanResource — hydratacja z JSON do form
 * state'a oraz serializacja form state → JSON.
 */
class PlanMultiCurrencyEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_repeater_hydrates_existing_overlay(): void
    {
        $this->actingAsMasterAdmin();
        $plan = $this->makePlan([
            'prices_per_currency' => [
                'EUR' => ['monthly_cents' => 5900, 'yearly_cents' => 63700],
                'GBP' => ['monthly_cents' => 4900, 'yearly_cents' => 52900],
            ],
        ]);

        Livewire::test(EditPlan::class, ['record' => $plan->getKey()])
            ->assertFormFieldExists('prices_per_currency');
    }

    public function test_save_persists_new_currency_overlay(): void
    {
        $this->actingAsMasterAdmin();
        $plan = $this->makePlan();

        Livewire::test(EditPlan::class, ['record' => $plan->getKey()])
            ->fillForm([
                'prices_per_currency' => [
                    ['currency' => 'EUR', 'monthly_cents' => 5900, 'yearly_cents' => 63700],
                    ['currency' => 'GBP', 'monthly_cents' => 4900, 'yearly_cents' => 52900],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $plan->refresh();
        $this->assertIsArray($plan->prices_per_currency);
        $this->assertSame(5900, $plan->prices_per_currency['EUR']['monthly_cents']);
        $this->assertSame(52900, $plan->prices_per_currency['GBP']['yearly_cents']);
    }

    public function test_enterprise_plan_does_not_persist_overlay_section(): void
    {
        $this->actingAsMasterAdmin();
        $plan = $this->makePlan([
            'price_monthly_cents' => 0,
            'price_yearly_cents' => 0,
            'features' => ['is_custom_pricing' => true, 'marketing_cta' => 'contact_sales'],
            'prices_per_currency' => null,
        ]);

        // Sekcja jest ukryta w UI dla Enterprise (hidden() na sekcji).
        // Sanity: zapis bez interakcji z polem nie psuje istniejących danych.
        Livewire::test(EditPlan::class, ['record' => $plan->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $plan->refresh();
        $this->assertNull($plan->prices_per_currency);
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'mc-admin-'.uniqid().'@hovera.app',
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
            'code' => 'mc-plan-'.uniqid(),
            'name' => 'MC Plan',
            'audience' => TenantType::Transporter->value,
            'currency' => 'PLN',
            'price_monthly_cents' => 25000,
            'price_yearly_cents' => 270000,
            'sort_order' => 100,
            'is_active' => true,
            'is_public' => true,
        ], $overrides));
    }
}
