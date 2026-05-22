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
 * Gating panelu /app dla horse_owner tenant'a.
 *
 * Wymóg: właściciel konia (HorseOwner) NIE wystawia faktur i NIE przyjmuje
 * płatności. Strony KsefSettings / InvoicingSettings / PaymentSettings
 * w panelu /app muszą zwracać canAccess()=false dla tego typu tenanta —
 * nawet jeśli user ma rolę owner w tenancie.
 *
 * Stable / Transporter (paid tier) działają jak dotychczas — owner ma dostęp.
 */
class FinanceSettingsHorseOwnerGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_horse_owner_cannot_access_ksef_settings(): void
    {
        $this->bootTenant(TenantType::HorseOwner);
        $this->assertFalse(KsefSettings::canAccess());
        $this->assertFalse(KsefSettings::shouldRegisterNavigation());
    }

    public function test_horse_owner_cannot_access_invoicing_settings(): void
    {
        $this->bootTenant(TenantType::HorseOwner);
        $this->assertFalse(InvoicingSettings::canAccess());
        $this->assertFalse(InvoicingSettings::shouldRegisterNavigation());
    }

    public function test_horse_owner_cannot_access_payment_settings(): void
    {
        $this->bootTenant(TenantType::HorseOwner);
        $this->assertFalse(PaymentSettings::canAccess());
        $this->assertFalse(PaymentSettings::shouldRegisterNavigation());
    }

    public function test_stable_owner_can_access_ksef_settings(): void
    {
        $this->bootTenant(TenantType::Stable);
        $this->assertTrue(KsefSettings::canAccess());
    }

    public function test_stable_owner_can_access_invoicing_settings(): void
    {
        $this->bootTenant(TenantType::Stable);
        $this->assertTrue(InvoicingSettings::canAccess());
    }

    public function test_stable_owner_can_access_payment_settings(): void
    {
        $this->bootTenant(TenantType::Stable);
        $this->assertTrue(PaymentSettings::canAccess());
    }

    private function bootTenant(TenantType $type): void
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
            'email' => 'owner-'.uniqid().'@example.com',
            'name' => 'Owner',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);
        // TenantManager::setCurrent w teście tylko trzyma referencję
        // (nie przepina connection do MySQL — to wysadziłoby sqlite test DB).
        $this->mock(TenantManager::class, function ($m) use ($tenant) {
            $m->shouldReceive('current')->andReturn($tenant);
            $m->shouldReceive('tenantOrFail')->andReturn($tenant);
            $m->shouldReceive('hasTenant')->andReturn(true);
        });
        Auth::login($user);
    }
}
