<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Enums\TransportKsefStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportInvoice extends TenantModel
{
    use SoftDeletes;

    protected $table = 'transport_invoices';

    protected $fillable = [
        'number', 'kind', 'status',
        'quote_id', 'response_id', 'corrects_invoice_id',
        'seller_name', 'seller_nip', 'seller_address',
        'seller_postal_code', 'seller_city', 'seller_country',
        'seller_iban', 'seller_bank_name',
        'buyer_name', 'buyer_nip', 'buyer_address',
        'buyer_postal_code', 'buyer_city', 'buyer_country',
        'buyer_email',
        'pickup_address', 'dropoff_address', 'service_date',
        'distance_km', 'vehicle_id', 'driver_id',
        'issued_at', 'sale_date', 'due_at', 'paid_at',
        'currency', 'subtotal_cents', 'vat_cents', 'total_cents',
        'ksef_status', 'ksef_reference', 'ksef_sent_at',
        'ksef_reference_number', 'ksef_submitted_at', 'ksef_accepted_at',
        'ksef_xml', 'ksef_error_payload',
        'notes', 'pdf_url', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'kind' => TransportInvoiceKind::class,
            'status' => TransportInvoiceStatus::class,
            'service_date' => 'date',
            'distance_km' => 'decimal:2',
            'issued_at' => 'date',
            'sale_date' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'ksef_status' => TransportKsefStatus::class,
            'ksef_sent_at' => 'datetime',
            'ksef_submitted_at' => 'datetime',
            'ksef_accepted_at' => 'datetime',
            'ksef_error_payload' => 'array',
            'subtotal_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransportInvoiceItem::class, 'invoice_id')->orderBy('position');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function netTotal(): float
    {
        return $this->subtotal_cents / 100;
    }

    public function vatTotal(): float
    {
        return $this->vat_cents / 100;
    }

    public function grossTotal(): float
    {
        return $this->total_cents / 100;
    }
}
