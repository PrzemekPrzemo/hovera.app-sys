<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sister middleware to RedirectIfTrialExpired. When a master admin
 * flips tenant.status = "suspended" via the back-office, every
 * /app/* request is rerouted to a single "konto zawieszone" page so
 * the owner gets a clear message instead of half-loaded panels.
 *
 * Master admins bypass entirely — they need to suspend/unsuspend
 * without locking themselves out of their own debugging tools.
 */
class RedirectIfTenantSuspended
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $next($request);
        }

        if ($user->is_master_admin === true) {
            return $next($request);
        }

        $tenant = $this->tenants->current();
        if ($tenant === null || $tenant->status !== 'suspended') {
            return $next($request);
        }

        if ($this->isAllowedPath($request)) {
            return $next($request);
        }

        return redirect()->route('tenant.suspended');
    }

    private function isAllowedPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        // The suspended landing page itself, logout, and Livewire's
        // own endpoints must remain reachable; otherwise the page
        // can't render and the user can't log out.
        return $path === 'app/suspended'
            || $path === 'app/logout'
            || $path === 'app/login'
            || str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'tenant/');
    }
}
