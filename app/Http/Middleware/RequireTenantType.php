<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\TenantType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wymusza zgodność typu aktywnego tenant'a z panelem. Działa za
 * `InitialiseTenantFromSession` (wymaga już hydrated $tenant w request).
 *
 * Użycie w `authMiddleware()`:
 *   RequireTenantType::class.':transporter'
 *   RequireTenantType::class.':stable'
 *
 * Jeśli typ nie pasuje — redirect na właściwy panel (stable → /app,
 * transporter → /transport). Master admin przepuszczany bez restrykcji,
 * tak jak w `InitialiseTenantFromSession`.
 */
class RequireTenantType
{
    public function handle(Request $request, Closure $next, string $expectedType): Response
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant) {
            return redirect()->route('tenant.select');
        }

        $expected = TenantType::from($expectedType);

        if ($tenant->type === $expected) {
            return $next($request);
        }

        // Master admin może odwiedzać oba panele bez membership — patrz
        // InitialiseTenantFromSession. Tu też go nie blokujemy.
        $user = $request->user();
        if ($user && $user->is_master_admin) {
            return $next($request);
        }

        return redirect('/'.$tenant->type->panelId());
    }
}
