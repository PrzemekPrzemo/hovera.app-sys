<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * For tenant-app routes (`/app/...`):
 *   - require auth
 *   - require an active membership matching the tenant_id stored in session
 *   - load the tenant and switch the `tenant` connection
 *   - reject requests if the membership was revoked or the tenant suspended
 */
class InitialiseTenantFromSession
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        /** @var User $user */
        $user = Auth::user();
        $tenantId = $request->session()->get('current_tenant_id');

        // Master admin bez aktywnego tenanta — redirectuj na /admin
        // zamiast tenant.select (master nie ma membership ani nie potrzebuje
        // wybierać stajni; logując się przez /app/login powinien wpaść do
        // panelu master admina).
        if (! $tenantId) {
            if ($user->is_master_admin) {
                return redirect('/'.config('hovera.admin.path', 'admin'));
            }

            return redirect()->route('tenant.select');
        }

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if (! $membership) {
            // Master admin może wejść na /app jako "viewer" tenanta
            // bez membership (np. po impersonacji lub przez direct URL
            // do tenant settings). Tenant musi istnieć i być active.
            if ($user->is_master_admin) {
                $tenant = Tenant::query()
                    ->where('id', $tenantId)
                    ->whereIn('status', ['active', 'trialing', 'past_due'])
                    ->first();
                if ($tenant) {
                    $this->tenants->setCurrent($tenant);
                    $request->attributes->set('tenant', $tenant);

                    return $next($request);
                }
            }

            $request->session()->forget('current_tenant_id');

            return redirect()->route('tenant.select')
                ->withErrors(['tenant' => 'Brak dostępu do wybranej stajni.']);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->isUsable()) {
            $request->session()->forget('current_tenant_id');

            return redirect()->route('tenant.select')
                ->withErrors(['tenant' => 'Stajnia jest niedostępna.']);
        }

        $this->tenants->setCurrent($tenant);

        $request->attributes->set('tenant', $tenant);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
