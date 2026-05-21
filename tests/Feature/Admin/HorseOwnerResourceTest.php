<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantType;
use App\Filament\Admin\Resources\HorseOwnerResource;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pokrywa dedykowany HorseOwnerResource — master admin musi widzieć
 * i edytować horse_owner tenants osobno od stajen. Wcześniej żyły
 * tylko w głównej `TenantResource` z formem stable-centric, edycja
 * crashowała bo `TenantType::HorseOwner` nie była w options select'a.
 */
class HorseOwnerResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_eloquent_query_scoped_to_horse_owner_type(): void
    {
        $this->makeTenant('stable-1', TenantType::Stable);
        $this->makeTenant('transport-1', TenantType::Transporter);
        $owner = $this->makeTenant('owner-1', TenantType::HorseOwner);

        $rows = HorseOwnerResource::getEloquentQuery()->get();

        $this->assertCount(1, $rows);
        $this->assertTrue($rows->contains('id', $owner->id));
    }

    public function test_owner_email_helper_reads_from_central_user_via_memberships(): void
    {
        $owner = $this->makeTenant('owner-mail', TenantType::HorseOwner);
        $user = User::create([
            'name' => 'Jan Owner',
            'email' => 'jan@example.com',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $owner->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $method = new ReflectionMethod(HorseOwnerResource::class, 'ownerEmail');
        $method->setAccessible(true);

        $this->assertSame('jan@example.com', $method->invoke(null, $owner->fresh()));
    }

    public function test_owner_email_returns_null_when_no_owner_membership(): void
    {
        $owner = $this->makeTenant('owner-no-user', TenantType::HorseOwner);

        $method = new ReflectionMethod(HorseOwnerResource::class, 'ownerEmail');
        $method->setAccessible(true);

        $this->assertNull($method->invoke(null, $owner->fresh()));
    }

    public function test_owner_email_skips_revoked_memberships(): void
    {
        $owner = $this->makeTenant('owner-revoked', TenantType::HorseOwner);
        $user = User::create([
            'name' => 'Revoked',
            'email' => 'revoked@example.com',
            'password' => bcrypt('secret'),
        ]);
        TenantMembership::create([
            'tenant_id' => $owner->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now()->subYear(),
            'revoked_at' => now()->subDay(),  // revoked yesterday
        ]);

        $method = new ReflectionMethod(HorseOwnerResource::class, 'ownerEmail');
        $method->setAccessible(true);

        $this->assertNull($method->invoke(null, $owner->fresh()));
    }

    public function test_navigation_label_localized(): void
    {
        // PL default → "Właściciele koni"
        $this->assertSame(__('admin/horse_owner.navigation'), HorseOwnerResource::getNavigationLabel());
    }

    private function makeTenant(string $slug, TenantType $type): Tenant
    {
        $tenant = new Tenant([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'type' => $type,
            'country' => 'PL',
            'locale' => 'pl',
            'timezone' => 'Europe/Warsaw',
            'currency' => 'PLN',
            'db_name' => 'hovera_t_'.Str::random(8),
            'db_username' => 'hovera_t_'.Str::random(8),
            'db_password_encrypted' => Crypt::encryptString('x'),
            'status' => 'active',
        ]);
        $tenant->save();

        return $tenant;
    }
}
