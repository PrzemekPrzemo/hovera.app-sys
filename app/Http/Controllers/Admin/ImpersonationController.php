<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Impersonation\StartImpersonation;
use App\Actions\Impersonation\StopImpersonation;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Finalises an impersonation request whose intent was stored in the
     * session by a Filament action. We do the auth switch in a fresh
     * request — running Auth::loginUsingId() inside a Livewire action
     * fights AuthenticateSession (the password-hash check + session
     * migration mid-response), which is what made "Zaloguj jako stajnia"
     * silently no-op. A plain GET → 302 sequence is reliable.
     */
    public function start(Request $request, StartImpersonation $action): RedirectResponse
    {
        $intent = $request->session()->pull('impersonation.intent');

        abort_unless(is_array($intent), 400, 'Brak intencji impersonacji w sesji.');
        abort_unless(
            isset($intent['target_user_id'], $intent['tenant_id'], $intent['reason'], $intent['issued_at']),
            400,
            'Niekompletna intencja impersonacji.',
        );
        abort_if(
            (int) $intent['issued_at'] < now()->subMinute()->timestamp,
            400,
            'Intencja impersonacji wygasła — kliknij ponownie.',
        );

        /** @var User $master */
        $master = Auth::user();
        abort_unless($master?->is_master_admin, 403);

        $tenant = Tenant::query()->findOrFail($intent['tenant_id']);
        $target = User::query()->findOrFail($intent['target_user_id']);

        $action->execute(
            masterAdmin: $master,
            tenant: $tenant,
            targetUser: $target,
            reason: (string) $intent['reason'],
            session: $request->session(),
        );

        return redirect('/app');
    }

    public function stop(Request $request, StopImpersonation $action): RedirectResponse
    {
        $result = $action->execute($request->session());

        if ($result['returned_to']) {
            return redirect('/'.config('hovera.admin.path'))
                ->with('status', 'Wróciłeś do swojego konta master admina.');
        }

        return redirect()->route('login');
    }
}
