<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\TenantType;
use App\Filament\App\Pages\InvoicingSettings;
use App\Filament\App\Pages\KsefSettings;
use App\Filament\App\Pages\PaymentSettings;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Macierz dostępu (tenant_type × user_role × page) — pojedyncze miejsce,
 * w którym dokumentujemy "kto może wejść gdzie" w sposób wykonywalny.
 *
 * Każdy `assert*` to wiersz tabeli z docs/ROLE-MATRIX.md. Gdy plan ról
 * się zmieni → tu wpis się sypie → fix ROLE-MATRIX + page canAccess()
 * razem.
 *
 * Test celuje w canAccess() pages (PageProvider tworzy też resource'y,
 * ale ich gating idzie przez RestrictedByTenantRole trait, którego
 * pokrycie mają osobne testy `*ResourceTest`).
 */
class PanelAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0:TenantType, 1:string, 2:string, 3:bool}> */
    public static function ksefSettingsMatrix(): array
    {
        return [
            'stable owner → access OK' => [TenantType::Stable, 'owner', KsefSettings::class, true],
            'stable admin → access OK' => [TenantType::Stable, 'admin', KsefSettings::class, true],
            'stable manager → BLOCKED (FINANCE_STAFF nie zawiera ksef settings)' => [TenantType::Stable, 'manager', KsefSettings::class, false],
            'stable employee → BLOCKED' => [TenantType::Stable, 'employee', KsefSettings::class, false],
            'horse_owner owner → BLOCKED (canIssueInvoices=false)' => [TenantType::HorseOwner, 'owner', KsefSettings::class, false],
        ];
    }

    /** @dataProvider ksefSettingsMatrix */
    public function test_ksef_settings_access_matrix(TenantType $type, string $role, string $pageClass, bool $expected): void
    {
        $this->bootTenantWithRole($type, $role);
        $this->assertSame($expected, $pageClass::canAccess());
    }

    /** @return array<string, array{0:TenantType, 1:string, 2:bool}> */
    public static function invoicingSettingsMatrix(): array
    {
        return [
            'stable owner → OK' => [TenantType::Stable, 'owner', true],
            'stable admin → OK' => [TenantType::Stable, 'admin', true],
            'stable manager → BLOCKED (numeracja FV tylko admin/owner)' => [TenantType::Stable, 'manager', false],
            'stable instructor → BLOCKED' => [TenantType::Stable, 'instructor', false],
            'horse_owner owner → BLOCKED (canIssueInvoices)' => [TenantType::HorseOwner, 'owner', false],
        ];
    }

    /** @dataProvider invoicingSettingsMatrix */
    public function test_invoicing_settings_access_matrix(TenantType $type, string $role, bool $expected): void
    {
        $this->bootTenantWithRole($type, $role);
        $this->assertSame($expected, InvoicingSettings::canAccess());
    }

    /** @return array<string, array{0:TenantType, 1:string, 2:bool}> */
    public static function paymentSettingsMatrix(): array
    {
        return [
            'stable owner → OK' => [TenantType::Stable, 'owner', true],
            'stable admin → OK' => [TenantType::Stable, 'admin', true],
            'stable manager → BLOCKED (FULL_ADMINS only)' => [TenantType::Stable, 'manager', false],
            'horse_owner owner → BLOCKED' => [TenantType::HorseOwner, 'owner', false],
        ];
    }

    /** @dataProvider paymentSettingsMatrix */
    public function test_payment_settings_access_matrix(TenantType $type, string $role, bool $expected): void
    {
        $this->bootTenantWithRole($type, $role);
        $this->assertSame($expected, PaymentSettings::canAccess());
    }

    public function test_can_issue_invoices_is_correct_per_type(): void
    {
        $this->assertTrue(TenantType::Stable->canIssueInvoices());
        $this->assertTrue(TenantType::Transporter->canIssueInvoices());
        $this->assertFalse(TenantType::HorseOwner->canIssueInvoices());
    }

    public function test_is_free_tier_is_correct_per_type(): void
    {
        $this->assertFalse(TenantType::Stable->isFreeTier());
        $this->assertFalse(TenantType::Transporter->isFreeTier());
        $this->assertTrue(TenantType::HorseOwner->isFreeTier());
    }

    private function bootTenantWithRole(TenantType $type, string $role): void
    {
        $tenant = Tenant::create([
            'slug' => 'firma-'.uniqid(),
            'name' => 'Firma',
            'type' => $type,
            'db_name' => 'firma_'.uniqid(),
            'db_username' => 'firma_'.uniqid(),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
        $user = User::create([
            'email' => $role.'-'.uniqid().'@example.com',
            'name' => ucfirst($role),
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'joined_at' => now(),
        ]);
        $this->mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('tenantOrFail')->andReturn($tenant);
            $m->shouldReceive('hasTenant')->andReturn(true);
        });
        Auth::login($user);
    }
}
