<?php

declare(strict_types=1);

namespace Tests\Feature\Stable;

use App\Filament\App\Resources\BoxInquiryResource;
use App\Tenancy\TenantManager;
use Tests\TestCase;

/**
 * Regression guard dla hotfix'a "SQLSTATE 1045 access denied for user
 * ''@'localhost'". `getNavigationBadge()` na BoxInquiryResource leciał
 * query'em na tenant connection ZANIM tenant manager miał creds —
 * Filament wywołuje badge'e podczas każdego render'a, także w panelach
 * pre-tenant-resolution.
 *
 * Fix: hasTenant() guard zwraca null bez query; try/catch chroni przed
 * pozostałymi błędami (brak migracji, padnięta DB).
 */
class BoxInquiryBadgeGuardTest extends TestCase
{
    public function test_badge_returns_null_when_no_tenant_bound(): void
    {
        // Brak `setCurrent` — fresh TenantManager bez tenanta.
        $this->assertFalse(app(TenantManager::class)->hasTenant());

        // Nie powinno rzucić — przed hotfixiem leciało SQLSTATE 1045.
        $badge = BoxInquiryResource::getNavigationBadge();

        $this->assertNull($badge);
    }
}
