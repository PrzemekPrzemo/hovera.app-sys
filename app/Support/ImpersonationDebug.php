<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Diagnostic-only logger for the master-admin impersonation flow.
 *
 * Each call snapshots auth + the session keys that matter for impersonation
 * + AuthenticateSession middleware. Intentionally chatty — meant to be
 * enabled briefly on prod (`HOVERA_IMPERSONATION_DEBUG=true`) while
 * reproducing the bug, then disabled.
 *
 * Failures (e.g. log file permission denied on Plesk) are swallowed —
 * a broken diagnostic must never break the flow it's diagnosing.
 */
class ImpersonationDebug
{
    public static function snap(string $event, array $extra = []): void
    {
        if (! config('hovera.impersonation.debug_log', true)) {
            return;
        }

        try {
            $session = session();
            $guard = config('auth.defaults.guard', 'web');
            $user = Auth::user();

            $passwordHash = $session->get('password_hash_'.$guard);
            $userPasswordHash = $user?->getAuthPassword();

            // Use the default channel (whatever the project ships with —
            // typically `single` → laravel.log). The dedicated
            // `impersonation` channel needs its own writable file which
            // doesn't always exist on first run on shared hosts (Plesk).
            // The "[impersonation]" prefix makes grepping easy.
            Log::info('[impersonation] '.$event, array_merge([
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
        } catch (Throwable) {
            // Diagnostic must never break the flow. Silent fail.
        }
    }
}
