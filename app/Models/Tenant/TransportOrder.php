<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Central\TransportLead;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Owner-side rekord zamówienia transportu. Soft-FK przez `central_lead_id`
 * do `transport_leads` w central DB — tam jest źródło prawdy dla
 * lifecycle'u (open → quoted → accepted) i dla broadcast'u do
 * transporterów. Ten model duplikuje route snapshot żeby UI listy
 * "Moje zamówienia" mógł renderować bez sięgania do central'a per row.
 *
 * Patrz docs/MARKETPLACE-ROADMAP.md PR 6.
 */
class TransportOrder extends TenantModel
{
    protected $table = 'transport_orders';

    protected $fillable = [
        'central_lead_id',
        'horse_id',
        'pickup_address',
        'dropoff_address',
        'preferred_date',
        'preferred_time',
        'calculation_mode',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(OwnerHorse::class, 'horse_id');
    }

    /**
     * Hydrate centralnego TransportLead'a po `central_lead_id`. Zwraca
     * `null` gdy lead nie istnieje (np. central rollback) — UI musi to
     * obsłużyć gracefully.
     */
    public function centralLead(): ?TransportLead
    {
        return TransportLead::query()->find($this->central_lead_id);
    }
}
