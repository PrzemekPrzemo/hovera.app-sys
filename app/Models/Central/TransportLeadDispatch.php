<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportLeadDispatch extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'transport_lead_dispatch';

    protected $fillable = [
        'lead_id', 'transporter_tenant_id',
        'notified_email', 'notified_push', 'notified_in_app', 'notified_at',
        'view_status', 'seen_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_email' => 'boolean',
            'notified_push' => 'boolean',
            'notified_in_app' => 'boolean',
            'notified_at' => 'datetime',
            'seen_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(TransportLead::class, 'lead_id');
    }
}
