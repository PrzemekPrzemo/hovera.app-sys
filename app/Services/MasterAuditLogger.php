<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Central\AuditLogMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MasterAuditLogger
{
    /**
     * Persist a master-admin audit entry. Always runs against the
     * `central` connection regardless of current tenant context.
     *
     * @param  array<string,mixed>  $payload
     */
    public function record(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $tenantId = null,
        array $payload = [],
    ): void {
        if (in_array($action, (array) config('hovera.audit.ignore_actions', []), true)) {
            return;
        }

        $request = app()->bound('request') ? app('request') : null;

        AuditLogMaster::create([
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'tenant_id' => $tenantId,
            'payload' => $payload,
            'ip_address' => $request?->ip(),
            'user_agent' => $request instanceof Request ? $request->userAgent() : null,
            'created_at' => now(),
        ]);
    }
}
