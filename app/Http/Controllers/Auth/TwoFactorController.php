<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\Central\User;
use App\Services\MasterAuditLogger;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorAuthenticator $totp) {}

    /**
     * Show the QR + confirmation form. If the user already enabled 2FA,
     * redirect to /admin instead of regenerating a secret.
     */
    public function showSetup(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.challenge');
        }

        // Remember where to send the user once enrolment finishes.
        if ($referer = $request->headers->get('referer')) {
            $request->session()->put('two_factor.return_to', $referer);
        }

        $secret = $request->session()->get('pending_2fa_secret');
        if (! $secret) {
            $secret = $this->totp->generateSecret();
            $request->session()->put('pending_2fa_secret', $secret);
        }

        $uri = $this->totp->provisioningUri($user, $secret);
        $qr = $this->totp->provisioningQrSvg($uri);

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'qr_svg' => $qr,
        ]);
    }

    public function confirmSetup(Request $request, MasterAuditLogger $audit): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $secret = $request->session()->get('pending_2fa_secret');

        if (! $secret || ! $this->totp->verify($secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Kod jest nieprawidłowy lub wygasł.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();
        $recoveryCodes = $this->totp->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('pending_2fa_secret');
        $request->session()->put('two_factor_passed', true);
        $request->session()->put('two_factor_recovery_codes_view', $recoveryCodes);

        $audit->record('master.two_factor.enabled', 'User', $user->id);

        return redirect()->route('two-factor.recovery-codes');
    }

    public function showRecoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->pull('two_factor_recovery_codes_view');
        if (! $codes) {
            return redirect($this->returnUrlAfterTwoFactor($request));
        }

        return view('auth.two-factor-recovery-codes', [
            'codes' => $codes,
            'return_to' => $this->returnUrlAfterTwoFactor($request, peek: true),
        ]);
    }

    public function showChallenge(): View|RedirectResponse
    {
        if (session('two_factor_passed')) {
            return redirect()->intended('/'.config('hovera.admin.path'));
        }

        return view('auth.two-factor-challenge');
    }

    public function submitChallenge(Request $request, MasterAuditLogger $audit): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'min:6', 'max:12']]);

        /** @var User $user */
        $user = Auth::user();
        $code = $request->string('code')->toString();

        if ($this->totp->verify($user->two_factor_secret, $code)) {
            $request->session()->put('two_factor_passed', true);
            $audit->record('master.two_factor.challenge.passed', 'User', $user->id);

            return redirect()->intended('/'.config('hovera.admin.path'));
        }

        // Recovery code branch
        $recovery = (array) $user->two_factor_recovery_codes;
        if (in_array(strtoupper($code), $recovery, true)) {
            $remaining = array_values(array_diff($recovery, [strtoupper($code)]));
            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
            $request->session()->put('two_factor_passed', true);
            $audit->record('master.two_factor.recovery_used', 'User', $user->id, null, [
                'remaining' => count($remaining),
            ]);

            return redirect()->intended('/'.config('hovera.admin.path'));
        }

        $audit->record('master.two_factor.challenge.failed', 'User', $user->id);

        throw ValidationException::withMessages([
            'code' => 'Nieprawidłowy kod.',
        ]);
    }

    /**
     * Decide where to send the user after a 2FA flow concludes:
     *   1. session-recorded referer (if it points to one of our panels)
     *   2. master admins → /admin
     *   3. tenant users → /app
     *
     * `peek` keeps the value in the session (used by the recovery codes
     * view, which links to the returnUrl). Otherwise we pull-and-clear.
     */
    private function returnUrlAfterTwoFactor(Request $request, bool $peek = false): string
    {
        $stored = $peek
            ? $request->session()->get('two_factor.return_to')
            : $request->session()->pull('two_factor.return_to');

        $appBase = $request->getSchemeAndHttpHost();
        if ($stored && str_starts_with($stored, $appBase)) {
            $path = parse_url($stored, PHP_URL_PATH) ?: '/';
            if (str_starts_with($path, '/app/') || str_starts_with($path, '/'.config('hovera.admin.path').'/')) {
                return $stored;
            }
        }

        $user = Auth::user();
        if ($user?->is_master_admin) {
            return '/'.config('hovera.admin.path');
        }

        return '/app';
    }
}
