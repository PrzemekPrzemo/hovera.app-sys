<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\Auth\TwoFactorController;
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
            if ($this->hasValidRememberCookie($request, (string) $user->id)) {
                $request->session()->put('two_factor_passed', true);

                return $next($request);
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }

    /**
     * Validate the encrypted "remember this device" cookie set after a
     * successful 2FA challenge. Cookie payload: {user_id, issued_at}.
     * EncryptCookies middleware decrypts the value before we see it here.
     */
    private function hasValidRememberCookie(Request $request, string $userId): bool
    {
        $raw = $request->cookie(TwoFactorController::REMEMBER_COOKIE_NAME);
        if (! is_string($raw) || $raw === '') {
            return false;
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return false;
        }

        if (($payload['user_id'] ?? null) !== $userId) {
            return false;
        }

        $issuedAt = (int) ($payload['issued_at'] ?? 0);
        if ($issuedAt <= 0) {
            return false;
        }

        $maxAge = TwoFactorController::REMEMBER_DAYS * 24 * 60 * 60;

        return (now()->timestamp - $issuedAt) <= $maxAge;
    }
}
