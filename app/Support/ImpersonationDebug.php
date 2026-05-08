<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Diagnostic-only logger for the master-admin impersonation flow.
 *
 * Each call snapshots auth + the session keys that matter for impersonation
 * + AuthenticateSession middleware. Intentionally chatty — meant to be
 * enabled briefly on prod (`HOVERA_IMPERSONATION_DEBUG=true`) while
 * reproducing the bug, then disabled.
 */
class ImpersonationDebug
{
    public static function snap(string $event, array $extra = []): void
    {
        if (! config('hovera.impersonation.debug_log', true)) {
            return;
        }

        $session = session();
        $guard = config('auth.defaults.guard', 'web');
        $user = Auth::user();

        $passwordHash = $session->get('password_hash_'.$guard);
        $userPasswordHash = $user?->getAuthPassword();

        Log::channel('impersonation')->info($event, array_merge([
            'event' => $event,
            'request' => request()?->fullUrl(),
            'method' => request()?->method(),
            'session_id' => $session->getId(),
            'auth_id' => Auth::id(),
            'auth_email' => $user?->email,
            'is_master_admin' => (bool) ($user->is_master_admin ?? false),
            'session.impersonation.original_user_id' => $session->get('impersonation.original_user_id'),
            'session.impersonation.session_id' => $session->get('impersonation.session_id'),
            'session.impersonation.expires_at' => $session->get('impersonation.expires_at'),
            'session.current_tenant_id' => $session->get('current_tenant_id'),
            'session.password_hash_present' => $passwordHash !== null,
            'session.password_hash_matches_user' => $passwordHash !== null
                && $userPasswordHash !== null
                && hash_equals($passwordHash, $userPasswordHash),
            'session.has_impersonation_intent' => $session->has('impersonation.intent'),
        ], $extra));
    }
}
