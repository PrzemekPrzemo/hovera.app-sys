<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * After the 30-day trial ends and before the tenant has bound a Stripe
 * subscription, redirect every /app/* request to /app/billing so the
 * owner is forced through the plan picker.
 *
 * Bypasses:
 *   - master admins (debug / impersonation)
 *   - the billing routes themselves (else infinite loop)
 *   - logout (so the user can leave)
 *   - Filament's own auth bootstrap endpoints (/app/login, /app/logout)
 */
class RedirectIfTrialExpired
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user === null) {
            return $next($request);
        }

        // Master admins bypass billing gate entirely.
        if ($user->is_master_admin === true) {
            return $next($request);
        }

        $tenant = $this->tenants->current();
        if ($tenant === null || ! $tenant->trialHasExpired()) {
            return $next($request);
        }

        if ($this->isAllowedPath($request)) {
            return $next($request);
        }

        return redirect()->route('billing.show');
    }

    private function isAllowedPath(Request $request): bool
    {
        $path = trim($request->path(), '/');

        // Billing routes themselves — never redirect-loop.
        if (str_starts_with($path, 'app/billing')) {
            return true;
        }

        // Logout & login flows.
        if ($path === 'app/logout' || $path === 'app/login') {
            return true;
        }

        // Tenant switch / select — owner can hop to a different stable.
        if (str_starts_with($path, 'tenant/')) {
            return true;
        }

        // Filament Livewire endpoint must keep working on the billing
        // page itself; the redirect rule above already covers other
        // panel routes via initial GET.
        if (str_starts_with($path, 'livewire/')) {
            return true;
        }

        return false;
    }
}
