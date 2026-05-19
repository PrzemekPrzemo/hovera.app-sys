<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportLead extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'transport_leads';

    protected $fillable = [
        'access_slug', 'access_revoked_at',
        'originator_tenant_id', 'originator_user_id',
        'originator_email', 'originator_phone', 'originator_name',
        'mode', 'targeted_transporter_ids',
        'pickup_address', 'pickup_lat', 'pickup_lng', 'pickup_voivodeship',
        'dropoff_address', 'dropoff_lat', 'dropoff_lng', 'dropoff_voivodeship',
        'preferred_date', 'preferred_time', 'flexible_date',
        'horse_count', 'horses', 'notes',
        'status', 'accepted_response_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'targeted_transporter_ids' => 'array',
            'horses' => 'array',
            'pickup_lat' => 'float',
            'pickup_lng' => 'float',
            'dropoff_lat' => 'float',
            'dropoff_lng' => 'float',
            'preferred_date' => 'date',
            'expires_at' => 'datetime',
            'access_revoked_at' => 'datetime',
            'flexible_date' => 'boolean',
            'horse_count' => 'integer',
        ];
    }

    /**
     * Czy klient publiczny może otworzyć portal po slug'u (link z maila).
     * Link działa permanentnie, ale możemy revoke'ować ręcznie z admin'a.
     */
    public function isPortalAccessible(): bool
    {
        return $this->access_slug !== null && $this->access_revoked_at === null;
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(TransportLeadDispatch::class, 'lead_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(TransportLeadResponse::class, 'lead_id');
    }
}
