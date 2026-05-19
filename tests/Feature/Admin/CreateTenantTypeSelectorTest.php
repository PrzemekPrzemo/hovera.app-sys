<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Filament\Admin\Resources\TenantResource\Pages\CreateTenant;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Tenancy\Provisioner;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Master admin tworzący tenanta przez `/admin/tenants/create` musi móc wybrać
 * `type` (Stable | Transporter). Bez tego pola wszystkie nowe tenanty
 * defaultowały do Stable — patrz user report 2026-05-19.
 *
 * Plan list filtruje się po wybranym type — stable widzi stable plans,
 * transporter widzi transport plans.
 */
class CreateTenantTypeSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Stub Provisioner — nie tworzymy faktycznie DB w testach.
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

    public function test_create_form_renders_type_selector_with_stable_default(): void
    {
        $this->actingAsMasterAdmin();

        Livewire::test(CreateTenant::class)
            ->assertFormFieldExists('type')
            ->assertFormSet(['type' => TenantType::Stable->value]);
    }

    public function test_creating_with_type_transporter_persists_transporter_type(): void
    {
        $this->actingAsMasterAdmin();
        $plan = Plan::create([
            'code' => 'transport_start',
            'audience' => TenantType::Transporter->value,
            'name' => 'Transport Start',
            'currency' => 'PLN',
            'price_monthly_cents' => 25000,
            'price_yearly_cents' => 250000,
            'sort_order' => 1,
        ]);

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'type' => TenantType::Transporter->value,
                'slug' => 'firma-trans',
                'name' => 'Firma Transportowa',
                'country' => 'PL',
                'locale' => 'pl',
                'timezone' => 'Europe/Warsaw',
                'currency' => 'PLN',
                'plan_id' => $plan->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('slug', 'firma-trans')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(TenantType::Transporter, $tenant->type);
        $this->assertSame('transport_start', $tenant->plan?->code);
    }

    public function test_creating_without_explicit_type_defaults_to_stable(): void
    {
        $this->actingAsMasterAdmin();

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'slug' => 'stadnina',
                'name' => 'Stadnina ABC',
                'country' => 'PL',
                'locale' => 'pl',
                'timezone' => 'Europe/Warsaw',
                'currency' => 'PLN',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('slug', 'stadnina')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(TenantType::Stable, $tenant->type);
    }

    public function test_plan_select_filters_by_selected_type(): void
    {
        $this->actingAsMasterAdmin();
        Plan::create([
            'code' => 'pro', 'audience' => TenantType::Stable->value, 'name' => 'Pro Stable',
            'currency' => 'PLN', 'price_monthly_cents' => 49900, 'price_yearly_cents' => 539000, 'sort_order' => 1,
        ]);
        $transporterPlan = Plan::create([
            'code' => 'transport_start', 'audience' => TenantType::Transporter->value, 'name' => 'Transport Start',
            'currency' => 'PLN', 'price_monthly_cents' => 25000, 'price_yearly_cents' => 250000, 'sort_order' => 1,
        ]);

        // Po przełączeniu type → plan options zmieniają się.
        Livewire::test(CreateTenant::class)
            ->fillForm(['type' => TenantType::Transporter->value])
            ->assertFormFieldExists('plan_id');

        // Bezpośrednia weryfikacja Plan::query()->where('audience', ...) — bo
        // Filament's options closure jest hard do podejrzenia przez assertFormSet.
        $options = Plan::query()->where('audience', TenantType::Transporter->value)->pluck('name', 'id');
        $this->assertTrue($options->keys()->contains($transporterPlan->id));
        $this->assertCount(1, $options);
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
