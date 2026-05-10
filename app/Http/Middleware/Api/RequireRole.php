<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use App\Models\Central\TenantMembership;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage in routes/api.php:
 *   Route::post('/calendar-entries', ...)->middleware('api.role:instructor,manager');
 *
 * Roles are resolved from the active TenantMembership stamped by
 * ApiAuthenticateAndResolveTenant. Membership.permissions JSON may also
 * grant fine-grained overrides via 'allow' / 'deny' arrays.
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var TenantMembership|null $membership */
        $membership = $request->attributes->get('tenant_membership');
        if (! $membership) {
            return new JsonResponse(['error' => ['code' => 'no_membership']], 403);
        }

        $perms = is_array($membership->permissions) ? $membership->permissions : [];
        $deny = (array) ($perms['deny'] ?? []);
        $allow = (array) ($perms['allow'] ?? []);

        $role = (string) $membership->role;
        $route = $request->route()?->getName() ?? $request->path();

        if (in_array($route, $deny, true)) {
            return new JsonResponse(['error' => ['code' => 'forbidden_route']], 403);
        }

        if (in_array($role, $roles, true) || in_array($route, $allow, true)) {
            return $next($request);
        }

        return new JsonResponse([
            'error' => [
                'code' => 'insufficient_role',
                'message' => sprintf('Role "%s" not in [%s].', $role, implode(',', $roles)),
            ],
        ], 403);
    }
}
