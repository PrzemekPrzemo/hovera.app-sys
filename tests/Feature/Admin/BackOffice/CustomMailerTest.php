<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\BackOffice;

use App\Filament\Admin\Pages\TenantAsAdmin\TenantMailer;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TenantMessage;
use App\Models\Central\User;
use App\Notifications\CustomTenantMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CustomMailerTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_admin_can_send_message_to_owners(): void
    {
        Notification::fake();

        $admin = User::create([
            'email' => 'master@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $owner = User::create([
            'email' => 'owner@stajnia.pl',
            'name' => 'Jan',
            'password' => Hash::make('secret'),
        ]);
        $tenant = $this->makeTenant();
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(TenantMailer::class, ['tenantId' => $tenant->id])
            ->set('data.subject', 'Witaj')
            ->set('data.body', 'Treść wiadomości od admina.')
            ->set('data.template', 'custom')
            ->call('send')
            ->assertHasNoErrors();

        Notification::assertSentTo($owner, CustomTenantMessage::class);

        $message = TenantMessage::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('Witaj', $message->subject);
        $this->assertSame(1, $message->recipients_count);
        $this->assertEqualsCanonicalizing(['owner@stajnia.pl'], $message->recipients);
    }

    public function test_no_recipients_returns_error(): void
    {
        Notification::fake();

        $admin = User::create([
            'email' => 'master@hovera.app',
            'name' => 'Master',
            'password' => Hash::make('secret'),
            'is_master_admin' => true,
        ]);
        $tenant = $this->makeTenant();

        $this->actingAs($admin);

        Livewire::test(TenantMailer::class, ['tenantId' => $tenant->id])
            ->set('data.subject', 'Witaj')
            ->set('data.body', 'Treść wiadomości od admina.')
            ->call('send');

        Notification::assertNothingSent();
        $this->assertSame(0, TenantMessage::query()->where('tenant_id', $tenant->id)->count());
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
