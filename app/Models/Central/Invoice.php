<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Central-side invoice — hovera → stajnia (subskrypcja SaaS).
 * Tworzona z `StripeBillingService::onCheckoutCompleted` po pomyślnym
 * pobraniu opłaty. Stripe trzyma własny invoice (stripe_invoice_id),
 * ale my chowamy własny snapshot, żeby w razie cofnięcia integracji
 * mieć pełną historię + PDF lokalny + ksef_reference + p24 link.
 *
 * Status pól:
 *   - status: draft|open|paid|void|uncollectible (kompatybilne z Stripe)
 *   - p24_*: dane do/z P24 link "opłać fakturę" (jednorazowa płatność)
 *   - ksef_*: status push do KSeF (hovera jako podatnik VAT)
 *   - payload_snapshot: snapshot tenanta przy wystawieniu (immutable)
 */
class Invoice extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'invoices';

    protected $fillable = [
        'tenant_id', 'subscription_id',
        'number', 'kind', 'plan_code', 'period',
        'currency', 'amount_cents', 'vat_cents', 'total_cents', 'vat_rate',
        'status',
        'issued_at', 'due_at', 'paid_at', 'pdf_path',
        'stripe_invoice_id',
        'p24_session_id', 'p24_payment_url', 'p24_order_id', 'p24_paid_at',
        'ksef_status', 'ksef_uuid', 'ksef_reference', 'ksef_pushed_at', 'ksef_last_response',
        'peppol_status',
        'snapshot', 'payload_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'vat_rate' => 'integer',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'p24_paid_at' => 'datetime',
            'ksef_pushed_at' => 'datetime',
            'ksef_last_response' => 'array',
            'snapshot' => 'array',
            'payload_snapshot' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null || $this->status === 'paid';
    }

    public function totalAmount(): float
    {
        return $this->total_cents / 100;
    }
}
