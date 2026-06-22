<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specialist;

use App\Http\Controllers\Controller;
use App\Models\Central\SpecialistMagicLink;
use App\Services\TenantAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Specialist setup flow (PR O5 Channel B).
 *
 * GET  /specialist/setup/{token}  — landing page po kliknięciu w mail.
 *                                    Walidujemy token, pokazujemy form
 *                                    z hasłem + confirmation.
 * POST /specialist/setup/{token}  — przyjmuje hasło, hash'uje, marks
 *                                    email_verified_at (klik link =
 *                                    weryfikacja emaila, nie potrzeba
 *                                    osobnego code), marks link used,
 *                                    redirect do specialist login.
 *
 * Per captured decisions §3: 7d magic link + password setup. Decyzja
 * "email verification code" implementowana jako "klik w link =
 * verified email" — link jest dostarczony do verified email (mail się
 * dotarł), więc dodatkowy 6-digit code byłby double-confirmation.
 *
 * Brak rate limit'u na token check — token sam jest rate limit'em
 * (256-bit, 64 hex chars, brute force niemożliwy w 7d window).
 */
class SetupController extends Controller
{
    public function __construct(
        private readonly TenantAuditLogger $audit,
    ) {}

    /**
     * GET /specialist/setup/{token}
     */
    public function show(string $token): View|RedirectResponse
    {
        $link = SpecialistMagicLink::findByPlainToken($token, SpecialistMagicLink::KIND_INITIAL_SETUP);

        if ($link === null) {
            return redirect()
                ->route('specialist.setup.invalid')
                ->with('error', __('specialist/setup.error.invalid_or_expired'));
        }

        return view('specialist.setup', [
            'token' => $token,
            'specialist' => $link->specialist,
        ]);
    }

    /**
     * POST /specialist/setup/{token}
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $link = SpecialistMagicLink::findByPlainToken($token, SpecialistMagicLink::KIND_INITIAL_SETUP);

        if ($link === null) {
            return redirect()
                ->route('specialist.setup.invalid')
                ->with('error', __('specialist/setup.error.invalid_or_expired'));
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        DB::connection('central')->transaction(function () use ($link, $validated): void {
            $specialist = $link->specialist;
            $specialist->forceFill([
                'password_hash' => Hash::make($validated['password']),
                // Klik w mail = email verified (mail się dotarł).
                'email_verified_at' => $specialist->email_verified_at ?? now(),
            ])->save();

            $link->markUsed();

            $this->audit->record('specialist.setup.completed', 'ExternalSpecialist', (string) $specialist->id, [
                'email' => $specialist->email,
                'ip' => request()->ip(),
            ]);
        });

        return redirect()
            ->route('specialist.setup.completed')
            ->with('success', __('specialist/setup.success.account_ready'));
    }

    /**
     * Landing po invalid / expired link.
     */
    public function invalid(): View
    {
        return view('specialist.setup-invalid');
    }

    /**
     * Landing po successful setup — w przyszłości link do specialist
     * panel login (PR O5 Channel B follow-up: SpecialistPanelProvider).
     */
    public function completed(): View
    {
        return view('specialist.setup-completed');
    }
}
