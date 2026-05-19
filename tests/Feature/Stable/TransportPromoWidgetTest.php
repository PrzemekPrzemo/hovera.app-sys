<?php

declare(strict_types=1);

namespace Tests\Feature\Stable;

use App\Enums\TenantType;
use App\Filament\App\Widgets\TransportPromoWidget;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Widget na dashboardzie /app — promo modułu transportu. Visible tylko
 * gdy stable na płatnym planie + brak leadów w ostatnich 30 dniach.
 */
class TransportPromoWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_when_paid_plan_and_no_recent_leads(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        $this->assertTrue(TransportPromoWidget::canView());
    }

    public function test_hidden_for_free_plan(): void
    {
        $stable = $this->makeStable('free');
        app(TenantManager::class)->setCurrent($stable);

        $this->assertFalse(TransportPromoWidget::canView());
    }

    public function test_hidden_after_recent_lead(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'originator_tenant_id' => $stable->id,
            'originator_name' => 'X', 'originator_email' => 'x@x.pl',
            'pickup_address' => 'a', 'pickup_lat' => 1, 'pickup_lng' => 1, 'pickup_voivodeship' => 'mz',
            'dropoff_address' => 'b', 'dropoff_lat' => 2, 'dropoff_lng' => 2, 'dropoff_voivodeship' => 'mp',
            'preferred_date' => now()->addDay()->toDateString(),
            'horse_count' => 1, 'status' => 'open',
            'expires_at' => now()->addDays(14),
            'created_at' => now()->subDays(5),
        ]);

        $this->assertFalse(TransportPromoWidget::canView());
    }

    public function test_visible_when_lead_older_than_30_days(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        $lead = TransportLead::create([
            'id' => (string) Str::ulid(),
            'mode' => 'broadcast',
            'originator_tenant_id' => $stable->id,
            'originator_name' => 'X', 'originator_email' => 'x@x.pl',
            'pickup_address' => 'a', 'pickup_lat' => 1, 'pickup_lng' => 1, 'pickup_voivodeship' => 'mz',
            'dropoff_address' => 'b', 'dropoff_lat' => 2, 'dropoff_lng' => 2, 'dropoff_voivodeship' => 'mp',
            'preferred_date' => now()->subDays(30)->toDateString(),
            'horse_count' => 1, 'status' => 'open',
            'expires_at' => now()->subDays(20),
        ]);
        // created_at jest guarded → ustawiamy explicit raw query żeby
        // przesunąć w przeszłość.
        $lead->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();

        $this->assertTrue(TransportPromoWidget::canView());
    }

    public function test_hidden_after_session_dismiss(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        session()->put('transport_promo_dismissed', true);

        $this->assertFalse(TransportPromoWidget::canView());
    }

    public function test_dismiss_persists_in_session(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        $widget = new TransportPromoWidget;
        $widget->dismiss();

        $this->assertTrue(session('transport_promo_dismissed'));
        $this->assertTrue($widget->dismissed);
    }

    private function makeStable(string $planCode): Tenant
    {
        $plan = Plan::firstOrCreate(['code' => $planCode], [
            'audience' => 'stable',
            'name' => ucfirst($planCode),
            'currency' => 'PLN',
        ]);

        return Tenant::create([
            'slug' => 'stajnia-'.uniqid(),
            'name' => 'Stajnia',
            'type' => TenantType::Stable,
            'plan_id' => $plan->id,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
