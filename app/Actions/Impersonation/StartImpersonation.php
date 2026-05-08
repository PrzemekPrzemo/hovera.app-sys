<?php

declare(strict_types=1);

namespace App\Actions\Impersonation;

use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Services\MasterAuditLogger;
use App\Support\ImpersonationDebug;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Master admin impersonates a tenant user. RODO-compliant:
 *   - reason field mandatory (validated)
 *   - persistent record in `impersonation_sessions`
 *   - hard time limit (config)
 *   - every action by the impersonator inside /app is tagged in
 *     the per-tenant `audit_log` (see TenantAuditLogger)
 */
class StartImpersonation
{
    public function __construct(private readonly MasterAuditLogger $audit) {}

    /**
     * @return array{session_id:string, target_user:User, expires_at:\DateTimeInterface}
     */
    public function execute(
        User $masterAdmin,
        Tenant $tenant,
        User $targetUser,
        string $reason,
        Session $session,
    ): array {
        if (! $masterAdmin->is_master_admin) {
            throw new RuntimeException('Only master admins may impersonate.');
        }

        if ($masterAdmin->is($targetUser)) {
            throw new RuntimeException('Refusing to impersonate yourself.');
        }

        $hasActiveMembership = TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $targetUser->id)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasActiveMembership) {
            throw new RuntimeException('Target user has no active membership in this tenant.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new RuntimeException('Reason is required (min 5 characters) for audit purposes.');
        }

        $maxMinutes = (int) config('hovera.impersonation.max_minutes', 60);
        $expiresAt = now()->addMinutes($maxMinutes);

        return DB::connection('central')->transaction(function () use (
            $masterAdmin, $tenant, $targetUser, $reason, $session, $expiresAt
        ) {
            $row = DB::connection('central')->table('impersonation_sessions')->insertGetId([
                'id' => (string) Str::ulid(),
                'master_user_id' => $masterAdmin->id,
                'tenant_id' => $tenant->id,
                'target_user_id' => $targetUser->id,
                'reason' => $reason,
                'ip_address' => request()?->ip(),
                'started_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            // insertGetId on string PKs returns the literal id we passed
            // for some drivers; query it back to be portable.
            $sessionId = DB::connection('central')->table('impersonation_sessions')
                ->where('master_user_id', $masterAdmin->id)
                ->where('target_user_id', $targetUser->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->value('id');

            // Switch the auth user. The original master admin id is kept
            // in the session so we can switch back without re-login.
            $session->put('impersonation.original_user_id', $masterAdmin->id);
            $session->put('impersonation.session_id', $sessionId);
            $session->put('impersonation.expires_at', $expiresAt->toIso8601String());
            $session->put('current_tenant_id', $tenant->id);
            // Marker used by TenantAuditLogger to tag entries.
            $session->put('impersonation_session_id', $sessionId);

            ImpersonationDebug::snap('3_before_loginUsingId', [
                'target_user_id' => $targetUser->id,
                'master_user_id' => $masterAdmin->id,
            ]);

            Auth::loginUsingId($targetUser->id);

            ImpersonationDebug::snap('3_after_loginUsingId');

            // Critical for impersonation: AuthenticateSession middleware on
            // the /app panel compares Auth::user()->getAuthPassword() against
            // the password_hash_<guard> stored in the session. If they differ
            // (which they will, because we just swapped users), it calls
            // logoutCurrentDevice() and bounces the browser back to login.
            // Update the stored hash to match the new auth user so /app loads
            // cleanly. The recaller cookie isn't in play here (we use plain
            // session-based auth), so just the session key is sufficient.
            $guard = config('auth.defaults.guard', 'web');
            $session->put('password_hash_'.$guard, $targetUser->getAuthPassword());

            $this->audit->record(
                'impersonation.start',
                'User',
                $targetUser->id,
                $tenant->id,
                ['session_id' => $sessionId, 'reason' => $reason],
            );

            return [
                'session_id' => $sessionId,
                'target_user' => $targetUser,
                'expires_at' => $expiresAt,
            ];
        });
    }
}
