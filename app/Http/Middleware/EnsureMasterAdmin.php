<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for /admin. Requires:
 *   - authenticated user
 *   - is_master_admin = true
 *   - 2FA confirmed (when HOVERA_ADMIN_REQUIRE_2FA is on)
 */
class EnsureMasterAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if (!$user->is_master_admin) {
            abort(403, 'Forbidden');
        }

        if (config('hovera.admin.require_2fa') && !$user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
