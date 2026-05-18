<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Filament\Admin\Resources\PlanResource\Pages\CreatePlan;
use App\Filament\Admin\Resources\PlanResource\Pages\ListPlans;
use App\Models\Central\Plan;
use App\Models\Central\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * PlanResource musi wyświetlać kolumnę + filtr `audience` i wymagać go w formularzu,
 * aby master admin mógł rozdzielać plany dla Stajni i Transporterów.
 */
class PlanResourceAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_table_renders_audience_column(): void
    {
        $this->actingAsMasterAdmin();
        $stablePlan = $this->makePlan('stb-'.uniqid(), TenantType::Stable->value);
        $transporterPlan = $this->makePlan('trp-'.uniqid(), TenantType::Transporter->value);

        Livewire::test(ListPlans::class)
            ->assertCanSeeTableRecords([$stablePlan, $transporterPlan])
            ->assertTableColumnExists('audience');
    }

    public function test_audience_filter_returns_only_stable_plans(): void
    {
        $this->actingAsMasterAdmin();
        $stable = $this->makePlan('stb-'.uniqid(), TenantType::Stable->value);
        $tr = $this->makePlan('trp-'.uniqid(), TenantType::Transporter->value);

        Livewire::test(ListPlans::class)
            ->filterTable('audience', TenantType::Stable->value)
            ->assertCanSeeTableRecords([$stable])
            ->assertCanNotSeeTableRecords([$tr]);
    }

    public function test_audience_filter_returns_only_transporter_plans(): void
    {
        $this->actingAsMasterAdmin();
        $stable = $this->makePlan('stb-'.uniqid(), TenantType::Stable->value);
        $tr = $this->makePlan('trp-'.uniqid(), TenantType::Transporter->value);

        Livewire::test(ListPlans::class)
            ->filterTable('audience', TenantType::Transporter->value)
            ->assertCanSeeTableRecords([$tr])
            ->assertCanNotSeeTableRecords([$stable]);
    }

    public function test_create_form_requires_audience(): void
    {
        $this->actingAsMasterAdmin();

        // Pole `audience` jest required w formularzu — pusty submit powinien dać błąd walidacji.
        Livewire::test(CreatePlan::class)
            ->fillForm([
                'code' => 'no-audience-'.uniqid(),
                'name' => 'No audience plan',
                'currency' => 'PLN',
                'audience' => null,
                'price_monthly_cents' => 1000,
                'price_yearly_cents' => 10000,
            ])
            ->call('create')
            ->assertHasFormErrors(['audience']);
    }

    public function test_create_form_persists_audience(): void
    {
        $this->actingAsMasterAdmin();
        $code = 'tr-plan-'.uniqid();

        Livewire::test(CreatePlan::class)
            ->fillForm([
                'code' => $code,
                'name' => 'Transporter Plan',
                'currency' => 'PLN',
                'audience' => TenantType::Transporter->value,
                'sort_order' => 5,
                'price_monthly_cents' => 9900,
                'price_yearly_cents' => 99000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = Plan::query()->where('code', $code)->first();
        $this->assertNotNull($plan);
        $this->assertSame(TenantType::Transporter->value, $plan->audience);
    }

    private function actingAsMasterAdmin(): User
    {
        $admin = User::create([
            'email' => 'plan-admin-'.uniqid().'@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $this->actingAs($admin);

        return $admin;
    }

    private function makePlan(string $code, string $audience): Plan
    {
        return Plan::create([
            'code' => $code,
            'name' => 'Plan '.$code,
            'audience' => $audience,
            'currency' => 'PLN',
            'price_monthly_cents' => 1000,
            'price_yearly_cents' => 10000,
            'sort_order' => 1,
        ]);
    }
}
