<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single middleware that:
 *   1. extracts Bearer token, resolves the central User via Sanctum,
 *   2. reads X-Tenant-Id header, validates membership,
 *   3. activates the tenant connection through TenantManager.
 *
 * On success the request has:
 *   - $request->user() set
 *   - $request->attributes->get('tenant_membership') = TenantMembership
 *   - tenant DB connection live for any subsequent Eloquent query
 */
class ApiAuthenticateAndResolveTenant
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return $this->error('missing_token', 'Authorization Bearer token required.', 401);
        }

        /** @var PersonalAccessToken|null $accessToken */
        $accessToken = PersonalAccessToken::findToken($bearer);
        if (! $accessToken || ($accessToken->expires_at && $accessToken->expires_at->isPast())) {
            return $this->error('invalid_token', 'Token is missing, malformed or expired.', 401);
        }

        $user = $accessToken->tokenable;
        if (! $user || ! method_exists($user, 'getAuthIdentifier')) {
            return $this->error('invalid_token', 'Token holder cannot be resolved.', 401);
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        $tenantId = $request->header('X-Tenant-Id');
        if (! $tenantId) {
            return $this->error('missing_tenant', 'X-Tenant-Id header is required.', 400);
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            return $this->error('unknown_tenant', 'Tenant does not exist.', 404);
        }

        $membership = TenantMembership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->whereNull('revoked_at')
            ->first();

        if (! $membership) {
            return $this->error('forbidden_tenant', 'No active membership for this tenant.', 403);
        }

        $this->tenants->setCurrent($tenant);
        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('tenant_membership', $membership);

        return $next($request);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
