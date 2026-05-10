<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class TenantApiTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_tokens_query_returns_only_tokens_of_users_with_membership(): void
    {
        [$tenant, $member, $loneAdmin] = $this->scenario();

        $member->createToken('mobile-app', ['mobile']);
        $loneAdmin->createToken('admin-script', ['admin-all']);

        $memberIds = TenantMembership::query()
            ->whereNull('revoked_at')
            ->pluck('user_id')
            ->unique();

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $memberIds)
            ->get();

        $this->assertCount(1, $tokens);
        $this->assertSame('mobile-app', $tokens->first()->name);
    }

    public function test_filter_by_tenant_id_via_membership(): void
    {
        [$tenant1, $member1] = $this->scenario();

        $tenant2 = new Tenant([
            'slug' => 'other',
            'name' => 'Other',
            'db_name' => 'hovera_t_other',
            'db_username' => 'hovera_t_other',
            'status' => 'active',
        ]);
        $tenant2->db_password = 'x';
        $tenant2->save();

        $member2 = User::create(['email' => 'm2@example.com', 'name' => 'M2', 'password' => Hash::make('s')]);
        TenantMembership::create([
            'tenant_id' => $tenant2->id,
            'user_id' => $member2->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $member1->createToken('t1-token', ['mobile']);
        $member2->createToken('t2-token', ['mobile']);

        $userIdsForT1 = TenantMembership::query()
            ->where('tenant_id', $tenant1->id)
            ->whereNull('revoked_at')
            ->pluck('user_id');

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIdsForT1)
            ->get();

        $this->assertCount(1, $tokens);
        $this->assertSame('t1-token', $tokens->first()->name);
    }

    public function test_revoking_a_tenant_token_deletes_it(): void
    {
        [, $member] = $this->scenario();
        $newToken = $member->createToken('to-revoke', ['mobile']);
        $id = $newToken->accessToken->id;

        $newToken->accessToken->delete();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $id]);
    }

    /**
     * @return array{0:Tenant,1:User,2:User}
     */
    private function scenario(): array
    {
        $tenant = new Tenant([
            'slug' => 'acme',
            'name' => 'Acme',
            'db_name' => 'hovera_t_acme',
            'db_username' => 'hovera_t_acme',
            'status' => 'active',
        ]);
        $tenant->db_password = 'x';
        $tenant->save();

        $member = User::create([
            'email' => 'owner@example.com',
            'name' => 'Owner',
            'password' => Hash::make('s'),
        ]);
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $member->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $loneAdmin = User::create([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => Hash::make('s'),
            'is_master_admin' => true,
        ]);

        return [$tenant, $member, $loneAdmin];
    }
}
