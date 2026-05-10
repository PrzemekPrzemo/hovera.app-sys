<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\BackOffice;

use App\Filament\Admin\Pages\TenantAsAdmin\TenantSettingsAsAdmin;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TenantSettingsAsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_edit_branding_and_profile(): void
    {
        $admin = User::create([
            'email' => 'master@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $tenant = $this->makeTenant();

        $this->actingAs($admin);

        Livewire::test(TenantSettingsAsAdmin::class, ['tenantId' => $tenant->id])
            ->set('data.name', 'Stajnia Edytowana')
            ->set('data.primary_color', '#ff6600')
            ->set('data.pp_tagline', 'Nowe hasło')
            ->set('data.pp_description', 'Świetna stajnia w okolicy.')
            ->set('data.pp_email', 'kontakt@stajnia.pl')
            ->set('data.pp_opening_hours', 'Pn-Pt 8-20')
            ->set('data.pb_enabled', true)
            ->set('data.pb_lesson_duration_minutes', 90)
            ->call('save')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertSame('Stajnia Edytowana', $tenant->name);
        $this->assertSame('#ff6600', $tenant->branding['primary_color']);
        $this->assertSame('Nowe hasło', $tenant->settings['public_profile']['tagline']);
        $this->assertSame('Pn-Pt 8-20', $tenant->settings['public_profile']['opening_hours']);
        $this->assertTrue($tenant->settings['public_booking']['enabled']);
        $this->assertSame(90, $tenant->settings['public_booking']['lesson_duration_minutes']);
    }

    public function test_non_master_admin_is_denied(): void
    {
        $user = User::create([
            'email' => 'user@example.com',
            'name' => 'User',
            'password' => Hash::make('secret'),
            'is_master_admin' => false,
        ]);
        $tenant = $this->makeTenant();

        $this->actingAs($user);

        Livewire::test(TenantSettingsAsAdmin::class, ['tenantId' => $tenant->id])
            ->assertForbidden();
    }

    private function makeTenant(): Tenant
    {
        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return $tenant;
    }
}
