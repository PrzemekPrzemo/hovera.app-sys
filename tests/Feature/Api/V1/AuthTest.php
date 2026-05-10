<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        User::factory()->create([
            'email' => 'a@b.test',
            'password' => Hash::make('correct-password'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'a@b.test',
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_login_returns_token_and_memberships(): void
    {
        $user = User::factory()->create([
            'email' => 'mgr@stable.test',
            'password' => Hash::make('letmein-please'),
        ]);
        $tenant = Tenant::factory()->create(['name' => 'Stajnia A']);
        TenantMembership::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'manager',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'mgr@stable.test',
            'password' => 'letmein-please',
            'device_name' => 'iPhone 15',
        ])->assertOk();

        $response->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'email'], 'memberships']);
        $this->assertStringStartsWith('hov_', (string) $response->json('token'));
        $this->assertSame('manager', $response->json('memberships.0.role'));
    }

    public function test_protected_endpoint_requires_x_tenant_id(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'missing_tenant');
    }

    public function test_protected_endpoint_rejects_unrelated_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
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
