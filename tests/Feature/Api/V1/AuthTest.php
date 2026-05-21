<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?string $email = null, string $password = 'secret123'): User
    {
        return User::create([
            'email' => $email ?? 'user-'.uniqid().'@example.test',
            'name' => 'Test User',
            'password' => Hash::make($password),
        ]);
    }

    private function makeTenant(string $name = 'Test Stable', TenantType $type = TenantType::Stable): Tenant
    {
        $u = uniqid();
        $tenant = new Tenant([
            'slug' => 'tst-'.$u,
            'name' => $name,
            'type' => $type,
            'db_name' => 'hovera_t_'.$u,
            'db_username' => 'hovera_t_'.$u,
            'status' => 'active',
        ]);
        $tenant->db_password = 'irrelevant';
        $tenant->save();

        return $tenant;
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $this->makeUser('a@b.test', 'correct-password');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'a@b.test',
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_login_returns_token_and_memberships(): void
    {
        $user = $this->makeUser('mgr@stable.test', 'letmein-please');
        $tenant = $this->makeTenant('Stajnia A');
        TenantMembership::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'manager',
            'joined_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'mgr@stable.test',
            'password' => 'letmein-please',
            'device_name' => 'iPhone 15',
        ])->assertOk();

        $response->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'email'], 'memberships']);
        $this->assertStringContainsString('hov_', (string) $response->json('token'));
        $this->assertSame('manager', $response->json('memberships.0.role'));
    }

    public function test_protected_endpoint_requires_x_tenant_id(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'missing_tenant');
    }

    public function test_protected_endpoint_rejects_unrelated_tenant(): void
    {
        $user = $this->makeUser();
        $tenant = $this->makeTenant();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => $tenant->id,
        ])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden_tenant');
    }
}
