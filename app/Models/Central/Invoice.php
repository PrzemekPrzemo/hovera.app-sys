<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Central-side invoice — hovera → stajnia (subskrypcja SaaS).
 * Tworzona z `StripeBillingService::onCheckoutCompleted` po pomyślnym
 * pobraniu opłaty. Stripe trzyma własny invoice (stripe_invoice_id),
 * ale my chowamy własny snapshot, żeby w razie cofnięcia integracji
 * mieć pełną historię + PDF lokalny + ksef_reference.
 */
class Invoice extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'invoices';

    protected $fillable = [
        'tenant_id', 'number', 'plan_code', 'period',
        'currency', 'amount_cents', 'vat_cents', 'total_cents',
        'issued_at', 'paid_at', 'pdf_path',
        'stripe_invoice_id', 'ksef_status', 'ksef_reference', 'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'snapshot' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
