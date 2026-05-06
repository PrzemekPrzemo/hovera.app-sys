<?php

declare(strict_types=1);

namespace App\Http\Controllers\Invitations;

use App\Actions\Invitations\AcceptInvitation;
use App\Services\MasterAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Public flow — anyone with a valid invitation token may set their
 * password and (if applicable) join the tenant. Routes are NOT behind
 * `auth` middleware; the token IS the credential.
 */
class AcceptInvitationController extends Controller
{
    public function __construct(private readonly AcceptInvitation $action) {}

    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invitation = $this->action->lookup($token);

        if (! $invitation || ! $invitation->isUsable()) {
            return redirect()->route('login')->withErrors([
                'invitation' => $this->problemMessage($invitation),
            ]);
        }

        return view('invitations.accept', [
            'token' => $token,
            'email' => $invitation->email,
            'tenant_name' => $invitation->tenant?->name,
        ]);
    }

    public function submit(Request $request, string $token, MasterAuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $invitation = $this->action->lookup($token);
        if (! $invitation || ! $invitation->isUsable()) {
            return redirect()->route('login')->withErrors([
                'invitation' => $this->problemMessage($invitation),
            ]);
        }

        try {
            $result = $this->action->execute($token, (string) $request->input('password'));
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['password' => $e->getMessage()]);
        }

        $audit->record(
            'invitation.accepted',
            'UserInvitation',
            $result['invitation']->id,
            $result['invitation']->tenant_id,
            ['email' => $result['user']->email],
        );

        Auth::login($result['user']);

        // Tenant invite → tenant app; standalone invite → admin/profile.
        if ($result['invitation']->tenant_id) {
            $request->session()->put('current_tenant_id', $result['invitation']->tenant_id);

            return redirect('/app');
        }

        return redirect('/'.config('hovera.admin.path'));
    }

    private function problemMessage($invitation): string
    {
        if (! $invitation) {
            return 'Link zaproszenia jest nieprawidłowy.';
        }
        if ($invitation->isAccepted()) {
            return 'To zaproszenie zostało już wykorzystane. Zaloguj się normalnie.';
        }
        if ($invitation->isExpired()) {
            return 'Link zaproszenia wygasł. Poproś o nowy.';
        }

        return 'Link zaproszenia jest nieprawidłowy.';
    }
}
