<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends TenantModel
{
    use SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'number', 'kind', 'status',
        'client_id', 'related_payment_id', 'related_pass_id', 'corrects_invoice_id',
        'seller_name', 'seller_nip', 'seller_address', 'seller_postal_code', 'seller_city', 'seller_country',
        'buyer_name', 'buyer_nip', 'buyer_address', 'buyer_postal_code', 'buyer_city', 'buyer_country', 'buyer_type',
        'issued_at', 'sale_date', 'due_at', 'paid_at',
        'currency', 'exchange_rate', 'exchange_rate_date', 'exchange_rate_source',
        'subtotal_cents', 'vat_cents', 'total_cents',
        'ksef_status', 'ksef_reference', 'ksef_sent_at',
        'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'kind' => InvoiceKind::class,
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'sale_date' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
            'ksef_sent_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'exchange_rate' => 'decimal:6',
            'exchange_rate_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * Faktura, którą koryguje ta korekta (dla `kind=fv_korekta`).
     */
    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_invoice_id');
    }

    /**
     * Alias dla `corrects()` używany przez `KsefInvoiceXmlBuilder`
     * (FA(3) XML wymaga referencji do oryginalnej faktury w korekcie).
     * Trzymamy oba żeby nie łamać pre-existing callers — `corrects`
     * jest naturalną nazwą w kontekście "co koryguje", `correctsInvoice`
     * trzyma się Ksef konwencji nazewniczej (`correctsInvoiceId`).
     */
    public function correctsInvoice(): BelongsTo
    {
        return $this->corrects();
    }

    /**
     * Korekty wystawione do tej faktury.
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_invoice_id');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            InvoiceStatus::Draft->value,
            InvoiceStatus::Void->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->where('status', InvoiceStatus::Issued->value)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString());
    }

    public function scopeForClient(Builder $query, string $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function totalFormatted(): string
    {
        return number_format($this->total_cents / 100, 2, ',', ' ').' '.$this->currency;
    }

    /**
     * Recalculate sums z items. Zwraca self żeby chainować ->save().
     * NIE zapisuje sam — caller decyduje kiedy.
     */
    public function recomputeTotals(): self
    {
        $sub = 0;
        $vat = 0;
        foreach ($this->items as $item) {
            $sub += (int) $item->net_cents;
            $vat += (int) $item->vat_cents;
        }
        $this->forceFill([
            'subtotal_cents' => $sub,
            'vat_cents' => $vat,
            'total_cents' => $sub + $vat,
        ]);

        return $this;
    }

    public function isCorrected(): bool
    {
        return $this->corrections()->exists();
    }
}
