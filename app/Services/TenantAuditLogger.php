<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant\AuditLog;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Writes per-tenant audit entries (`audit_log` in the active tenant DB).
 *
 * The TenantManager must already have a current tenant — caller is
 * responsible for that. We also auto-tag entries with the impersonation
 * context if one is active in the session.
 */
class TenantAuditLogger
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * @param  array<string,mixed>  $payload
     */
    public function record(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        array $payload = [],
    ): void {
        // Silently no-op if no tenant is active. Trying to write to the
        // tenant connection without one set would explode confusingly.
        if (! $this->tenants->hasTenant()) {
            return;
        }

        $request = $this->resolveRequest();
        $session = $this->resolveSession($request);
        $impersonationId = $session?->get('impersonation_session_id');

        AuditLog::create([
            'actor_central_user_id' => Auth::id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'payload' => $payload ?: null,
            'ip_address' => $request?->ip(),
            'via_impersonation' => (bool) $impersonationId,
            'impersonation_session_id' => $impersonationId,
            'created_at' => now(),
        ]);
    }

    private function resolveRequest(): ?Request
    {
        $req = app()->bound('request') ? app('request') : null;

        return $req instanceof Request ? $req : null;
    }

    private function resolveSession(?Request $request): ?Session
    {
        if ($request === null || ! $request->hasSession()) {
            return null;
        }

        return $request->session();
    }
}
