<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for /admin. Sequence:
 *
 *   1. Authenticated?         no  → /admin/login
 *   2. is_master_admin?       no  → 403
 *   3. 2FA enrolled?          no  → /two-factor/setup     (when required)
 *   4. 2FA challenge passed?  no  → /two-factor/challenge (when required)
 */
class EnsureMasterAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->guest(filament()->getCurrentPanel()?->getLoginUrl() ?? route('login'));
        }

        if (! $user->is_master_admin) {
            abort(403, 'Forbidden');
        }

        $require2fa = (bool) config('hovera.admin.require_2fa', true);

        if ($require2fa && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        if ($require2fa && $user->hasTwoFactorEnabled() && ! $request->session()->get('two_factor_passed')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
