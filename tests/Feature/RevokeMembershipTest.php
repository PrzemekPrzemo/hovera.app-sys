<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Memberships\RevokeMembership;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RevokeMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoke_sets_revoked_at(): void
    {
        $m = $this->makeMembership();

        $this->action()->execute($m);

        $this->assertNotNull($m->refresh()->revoked_at);
    }

    public function test_revoke_is_idempotent(): void
    {
        $m = $this->makeMembership();
        $m->forceFill(['revoked_at' => now()->subDay()])->save();
        $original = $m->revoked_at;

        $this->action()->execute($m);

        $this->assertEquals($original->toDateTimeString(), $m->refresh()->revoked_at->toDateTimeString());
    }

    public function test_reactivate_clears_revoked_at(): void
    {
        $m = $this->makeMembership();
        $m->forceFill(['revoked_at' => now()->subDay()])->save();

        $this->action()->reactivate($m);

        $this->assertNull($m->refresh()->revoked_at);
    }

    public function test_reactivate_is_idempotent_on_active_membership(): void
    {
        $m = $this->makeMembership();
        $this->assertNull($m->revoked_at);

        $this->action()->reactivate($m);

        $this->assertNull($m->refresh()->revoked_at);
    }

    private function action(): RevokeMembership
    {
        return $this->app->make(RevokeMembership::class);
    }

    private function makeMembership(): TenantMembership
    {
        $user = User::create([
            'email' => 'u@example.com',
            'name' => 'U',
            'password' => Hash::make('s'),
        ]);
        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'employee',
            'joined_at' => now(),
        ]);
    }
}
