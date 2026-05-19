<?php

declare(strict_types=1);

namespace Tests\Feature\Stable;

use App\Enums\TenantType;
use App\Filament\App\Pages\TransportEntry;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\TransportLead;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Strona /app/transport — entry-point dla stable na płatnym planie do
 * modułu transportu. Wizja: 3 ścieżki discovery (broadcast / katalog /
 * favorites) + free badge + disclaimer.
 */
class TransportEntryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_when_stable_on_paid_plan(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        $this->assertTrue(TransportEntry::canAccess());
        $this->assertTrue(TransportEntry::shouldRegisterNavigation());
    }

    public function test_cannot_access_when_stable_on_free_plan(): void
    {
        $stable = $this->makeStable('free');
        app(TenantManager::class)->setCurrent($stable);

        $this->assertFalse(TransportEntry::canAccess());
        $this->assertFalse(TransportEntry::shouldRegisterNavigation());
    }

    public function test_cannot_access_when_no_tenant_active(): void
    {
        // forget any prior tenant from setUp pipeline
        app(TenantManager::class)->forget();

        $this->assertFalse(TransportEntry::canAccess());
    }

    public function test_navigation_label_localized(): void
    {
        app()->setLocale('pl');
        $this->assertSame('Transport koni', TransportEntry::getNavigationLabel());
    }

    public function test_filament_page_route_registered(): void
    {
        $names = collect(app('router')->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->values();

        $this->assertTrue($names->contains('filament.app.pages.transport'));
    }

    public function test_broadcast_url_includes_stable_id_and_from_marker(): void
    {
        $stable = $this->makeStable('pro');
        app(TenantManager::class)->setCurrent($stable);

        $page = new TransportEntry;
        $url = $page->getBroadcastUrl();

        $this->assertStringContainsString('/transport/zapytanie', $url);
        $this->assertStringContainsString('from=app', $url);
        $this->assertStringContainsString('stable='.$stable->id, $url);
    }

    public function test_stable_leads_count_returns_originator_filtered(): void
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
        ]);

        $page = new TransportEntry;
        $this->assertSame(1, $page->getStableLeadsCount());
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
            'name' => 'Stajnia testowa',
            'type' => TenantType::Stable,
            'plan_id' => $plan->id,
            'db_name' => 's_'.uniqid(),
            'db_username' => 's_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }
}
