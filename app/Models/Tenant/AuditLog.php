<?php

declare(strict_types=1);

namespace App\Models\Tenant;

class AuditLog extends TenantModel
{
    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'actor_central_user_id', 'action',
        'target_type', 'target_id',
        'payload', 'ip_address',
        'via_impersonation', 'impersonation_session_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'via_impersonation' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
