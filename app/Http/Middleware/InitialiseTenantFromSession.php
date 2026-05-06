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

        $tenantId = $request->session()->get('current_tenant_id');

        if (! $tenantId) {
            return redirect()->route('tenant.select');
        }

        /** @var User $user */
        $user = Auth::user();

        $membership = TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if (! $membership) {
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
