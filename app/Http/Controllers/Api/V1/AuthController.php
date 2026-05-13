<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Resources\V1\TenantResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return new JsonResponse(['error' => ['code' => 'invalid_credentials']], 401);
        }

        $deviceName = (string) ($data['device_name'] ?? 'mobile');
        $token = $user->createToken($deviceName, ['*'], now()->addDays(30))->plainTextToken;

        $memberships = TenantMembership::query()
            ->with('tenant:id,name,slug,country,brand_color')
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->get();

        return new JsonResponse([
            'token' => $token,
            'expires_at' => now()->addDays(30)->toIso8601String(),
            'user' => (new UserResource($user))->resolve(),
            'memberships' => $memberships->map(fn ($m) => [
                'tenant' => $m->tenant ? (new TenantResource($m->tenant))->resolve() : null,
                'role' => $m->role,
                'permissions' => $m->permissions,
            ])->all(),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return new JsonResponse(['error' => ['code' => 'unauthenticated']], 401);
        }

        $current = $user->currentAccessToken();
        $newToken = $user->createToken('mobile-refresh', ['*'], now()->addDays(30))->plainTextToken;
        $current?->delete();

        return new JsonResponse([
            'token' => $newToken,
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        $user?->currentAccessToken()?->delete();

        return new JsonResponse(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = $request->attributes->get('tenant');
        $membership = $request->attributes->get('tenant_membership');

        return new JsonResponse([
            'user' => (new UserResource($user))->resolve(),
            'tenant' => $tenant ? (new TenantResource($tenant))->resolve() : null,
            'role' => $membership?->role,
            'permissions' => $membership?->permissions,
        ]);
    }
}
