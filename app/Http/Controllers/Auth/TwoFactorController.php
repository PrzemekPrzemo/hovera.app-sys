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
    public function __construct(private readonly TwoFactorAuthenticator $totp)
    {
    }

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

        $secret = $request->session()->get('pending_2fa_secret');
        if (!$secret) {
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

        if (!$secret || !$this->totp->verify($secret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Kod jest nieprawidłowy lub wygasł.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();
        $recoveryCodes = $this->totp->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret'         => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at'   => now(),
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
        if (!$codes) {
            return redirect('/' . config('hovera.admin.path'));
        }
        return view('auth.two-factor-recovery-codes', ['codes' => $codes]);
    }

    public function showChallenge(): View|RedirectResponse
    {
        if (session('two_factor_passed')) {
            return redirect()->intended('/' . config('hovera.admin.path'));
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
            return redirect()->intended('/' . config('hovera.admin.path'));
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
            return redirect()->intended('/' . config('hovera.admin.path'));
        }

        $audit->record('master.two_factor.challenge.failed', 'User', $user->id);

        throw ValidationException::withMessages([
            'code' => 'Nieprawidłowy kod.',
        ]);
    }
}
