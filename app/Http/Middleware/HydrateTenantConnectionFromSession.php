<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight middleware: jeśli session ma `current_tenant_id`,
 * ustawia tenant connection BEZ żadnych dodatkowych sprawdzeń
 * (membership, status, redirect).
 *
 * Po co: Filament Livewire endpoints (np. `/livewire/update`) działają
 * w domyślnym `web` middleware stacku, BEZ naszego `InitialiseTenantFromSession`
 * (ten jest tylko w `authMiddleware` panel'u). Bez tego Livewire requesty
 * dla resource'ów typu HorseResource padały z "Access denied for ''@'localhost'"
 * przy pluck() na BoardingService.
 *
 * Pełna walidacja (membership, tenant status, redirect na tenant.select)
 * dalej leży w `InitialiseTenantFromSession` — to middleware tylko fizycznie
 * podłącza connection żeby DB query nie wybuchała.
 */
class HydrateTenantConnectionFromSession
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->session()->get('current_tenant_id');

        if ($tenantId && ! $this->tenants->hasTenant()) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant) {
                $this->tenants->setCurrent($tenant);
            }
        }

        return $next($request);
    }
}
