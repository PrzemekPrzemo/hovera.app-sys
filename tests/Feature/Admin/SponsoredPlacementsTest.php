<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Transport\Sponsored\SponsoredPlacementService;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\AddonPurchase;
use App\Models\Central\PlanAddon;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sponsored placements (wyróżnienie 30/60/90 dni) — apply side-effect po
 * paid AddonPurchase + Tenant::scopeFeatured() honoruje featured_until.
 * Patrz docs/TRANSPORT.md §16.
 */
class SponsoredPlacementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_from_purchase_sets_featured_until_30_days(): void
    {
        $tenant = $this->makeTransporter();
        $purchase = $this->makePaidSponsoredPurchase($tenant, 'sponsored_30d', 30);

        $applied = app(SponsoredPlacementService::class)->applyFromPurchase($purchase);

        $this->assertTrue($applied);
        $tenant->refresh();
        $this->assertTrue($tenant->is_featured);
        $this->assertNotNull($tenant->featured_until);
        $this->assertEqualsWithDelta(
            now()->addDays(30)->timestamp,
            $tenant->featured_until->timestamp,
            5, // 5s tolerance dla timing flakiness
        );

        // Audit + idempotency marker
        $purchase->refresh();
        $this->assertNotNull($purchase->side_effect_applied_at);
    }

    public function test_apply_60_days_package_sets_60_day_window(): void
    {
        $tenant = $this->makeTransporter();
        $purchase = $this->makePaidSponsoredPurchase($tenant, 'sponsored_60d', 60);

        app(SponsoredPlacementService::class)->applyFromPurchase($purchase);

        $tenant->refresh();
        $this->assertEqualsWithDelta(
            now()->addDays(60)->timestamp,
            $tenant->featured_until->timestamp,
            5,
        );
    }

    public function test_apply_is_idempotent_for_duplicate_webhook(): void
    {
        // Drugi webhook nie powinien podwajać okresu featured ani
        // ustawiać side_effect_applied_at jeszcze raz.
        $tenant = $this->makeTransporter();
        $purchase = $this->makePaidSponsoredPurchase($tenant, 'sponsored_30d', 30);

        $first = app(SponsoredPlacementService::class)->applyFromPurchase($purchase);
        $purchase->refresh();
        $appliedAt = $purchase->side_effect_applied_at;
        $tenant->refresh();
        $featuredUntil = $tenant->featured_until;

        // Drugie wywołanie → no-op
        $second = app(SponsoredPlacementService::class)->applyFromPurchase($purchase->fresh());

        $this->assertTrue($first);
        $this->assertFalse($second, 'Second apply should be no-op (idempotent)');
        $tenant->refresh();
        $this->assertTrue($featuredUntil->equalTo($tenant->featured_until));
        $purchase->refresh();
        $this->assertTrue($appliedAt->equalTo($purchase->side_effect_applied_at));
    }

    public function test_apply_for_non_sponsored_purchase_returns_false(): void
    {
        $tenant = $this->makeTransporter();
        // Inny addon (np. migrate_excel) — nie sponsored
        $purchase = AddonPurchase::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'plan_addon_id' => $this->planAddonForCode('migrate_excel')->id,
            'addon_code' => 'migrate_excel',
            'addon_name' => 'Migracja danych z Excela',
            'currency' => 'PLN',
            'amount_cents' => 49900,
            'status' => AddonPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $applied = app(SponsoredPlacementService::class)->applyFromPurchase($purchase);

        $this->assertFalse($applied);
        $tenant->refresh();
        $this->assertFalse($tenant->is_featured);
    }

    public function test_unpaid_purchase_does_not_apply(): void
    {
        $tenant = $this->makeTransporter();
        $purchase = AddonPurchase::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'plan_addon_id' => $this->planAddonForCode('sponsored_30d')->id,
            'addon_code' => 'sponsored_30d',
            'addon_name' => 'Wyróżnienie 30 dni',
            'currency' => 'PLN',
            'amount_cents' => 9900,
            'status' => AddonPurchase::STATUS_PENDING,  // jeszcze nie paid
        ]);

        $applied = app(SponsoredPlacementService::class)->applyFromPurchase($purchase);

        $this->assertFalse($applied);
        $tenant->refresh();
        $this->assertFalse($tenant->is_featured);
    }

    public function test_scope_featured_excludes_expired_until(): void
    {
        $active = $this->makeTransporter();
        $active->forceFill([
            'is_featured' => true,
            'featured_until' => now()->addDays(10),
        ])->save();

        $expired = $this->makeTransporter();
        $expired->forceFill([
            'is_featured' => true,
            'featured_until' => now()->subDay(),
        ])->save();

        $permanent = $this->makeTransporter();
        $permanent->forceFill([
            'is_featured' => true,
            'featured_until' => null, // legacy permanent featured
        ])->save();

        $featured = Tenant::query()->featured()->pluck('id')->all();

        $this->assertContains($active->id, $featured);
        $this->assertContains($permanent->id, $featured);
        $this->assertNotContains($expired->id, $featured);
    }

    public function test_mark_featured_until_rolling_extension(): void
    {
        // Kupno 30d nad już aktywnym 30d featured → łącznie ~60d.
        $tenant = $this->makeTransporter();
        $tenant->forceFill([
            'is_featured' => true,
            'featured_at' => now()->subDays(5),
            'featured_until' => now()->addDays(25), // 25 dni zostało
        ])->save();

        $tenant->markFeaturedUntil(now()->addDays(30));

        $tenant->refresh();
        // Powinno być ~ 25 + 30 = 55 dni od teraz (rolling extension)
        $this->assertGreaterThan(
            now()->addDays(50)->timestamp,
            $tenant->featured_until->timestamp,
        );
        $this->assertLessThan(
            now()->addDays(60)->timestamp,
            $tenant->featured_until->timestamp,
        );
    }

    public function test_addon_purchase_featured_days_parses_code_fallback(): void
    {
        // Bez side_effect_metadata, regex parse z addon_code.
        $purchase = new AddonPurchase([
            'addon_code' => 'sponsored_90d',
        ]);
        $this->assertSame(90, $purchase->featuredDays());

        $purchase2 = new AddonPurchase([
            'addon_code' => 'sponsored_30d',
            'side_effect_metadata' => ['featured_days' => 45], // explicit override
        ]);
        $this->assertSame(45, $purchase2->featuredDays());
    }

    private function makeTransporter(): Tenant
    {
        $u = uniqid();

        return Tenant::create([
            'slug' => 't-'.$u,
            'name' => 'Firma '.$u,
            'type' => TenantType::Transporter,
            'verification_status' => VerificationStatus::Verified,
            'db_name' => 't_'.$u,
            'db_username' => 't_'.$u,
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
    }

    private function makePaidSponsoredPurchase(Tenant $tenant, string $code, int $days): AddonPurchase
    {
        $amounts = ['sponsored_30d' => 9900, 'sponsored_60d' => 17900, 'sponsored_90d' => 24900];

        return AddonPurchase::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'plan_addon_id' => $this->planAddonForCode($code)->id,
            'addon_code' => $code,
            'addon_name' => 'Wyróżnienie '.$days.' dni',
            'currency' => 'PLN',
            'amount_cents' => $amounts[$code] ?? 9900,
            'status' => AddonPurchase::STATUS_PAID,
            'paid_at' => now(),
            'side_effect_metadata' => ['featured_days' => $days],
        ]);
    }

    private function planAddonForCode(string $code): PlanAddon
    {
        return PlanAddon::firstOrCreate(
            ['code' => $code],
            [
                'plan_id' => null,
                'name' => 'Test '.$code,
                'addon_type' => PlanAddon::TYPE_ONE_TIME,
                'is_global' => true,
                'currency' => 'PLN',
                'price_monthly_cents' => 9900,
                'price_yearly_cents' => 0,
                'is_active' => true,
            ],
        );
    }
}
