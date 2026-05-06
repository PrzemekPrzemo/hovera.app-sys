<?php

declare(strict_types=1);

namespace App\Actions\Impersonation;

use App\Models\Central\User;
use App\Services\MasterAuditLogger;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StopImpersonation
{
    public function __construct(private readonly MasterAuditLogger $audit) {}

    /**
     * @return array{returned_to:?User}
     */
    public function execute(Session $session): array
    {
        $sessionId = $session->pull('impersonation.session_id');
        $originalUserId = $session->pull('impersonation.original_user_id');
        $session->forget('impersonation.expires_at');
        $session->forget('impersonation_session_id');

        if ($sessionId !== null) {
            DB::connection('central')->table('impersonation_sessions')
                ->where('id', $sessionId)
                ->update(['ended_at' => now()]);
        }

        if ($originalUserId === null) {
            // Defensive — user was not impersonating, just log them out.
            Auth::logout();

            return ['returned_to' => null];
        }

        $original = User::find($originalUserId);
        if (! $original) {
            Auth::logout();

            return ['returned_to' => null];
        }

        Auth::loginUsingId($original->id);
        // Drop tenant context — master admin shouldn't sit in /app.
        $session->forget('current_tenant_id');

        $this->audit->record(
            'impersonation.stop',
            null,
            $sessionId,
            null,
            ['ended_session_id' => $sessionId],
        );

        return ['returned_to' => $original];
    }
}
