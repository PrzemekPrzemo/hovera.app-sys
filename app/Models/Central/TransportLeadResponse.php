<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportLeadResponse extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'transport_lead_responses';

    protected $fillable = [
        'lead_id', 'transporter_tenant_id',
        'price_net', 'price_gross', 'currency', 'distance_km',
        'proposed_date', 'proposed_time',
        'terms', 'pdf_url', 'quote_id',
        'status', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'price_net' => 'decimal:2',
            'price_gross' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'proposed_date' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(TransportLead::class, 'lead_id');
    }
}
