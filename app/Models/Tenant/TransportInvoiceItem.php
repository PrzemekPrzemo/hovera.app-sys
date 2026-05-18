<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportInvoiceItem extends TenantModel
{
    protected $table = 'transport_invoice_items';

    protected $fillable = [
        'invoice_id', 'position', 'name', 'description',
        'quantity', 'unit', 'vat_rate',
        'unit_price_cents', 'net_cents', 'vat_cents', 'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'position' => 'integer',
            'unit_price_cents' => 'integer',
            'net_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TransportInvoice::class, 'invoice_id');
    }
}
