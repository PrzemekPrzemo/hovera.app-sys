<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends TenantModel
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id', 'horse_id', 'position', 'name', 'description',
        'quantity', 'unit', 'vat_rate',
        'unit_price_cents', 'net_cents', 'vat_cents', 'total_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_cents' => 'integer',
            'net_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'horse_id' => 'string',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Scope: tylko items związane z konkretnym koniem (per
     * central_horse_id). Używane przez Owner panel filtrowanie
     * "Faktury za Iskrę". Patrz docs/OWNER-STABLE-ROADMAP.md Faza 3.
     */
    public function scopeForHorse(Builder $query, string $centralHorseId): Builder
    {
        return $query->where('horse_id', $centralHorseId);
    }

    /**
     * Recompute net/vat/total from quantity, unit_price_cents, vat_rate.
     * Bezpieczne dla "zw" / "np" / "0" — vat_rate jest stringiem.
     */
    public function recomputeAmounts(): self
    {
        $qty = (float) $this->quantity;
        $unit = (int) $this->unit_price_cents;
        $netRaw = $qty * $unit;
        $net = (int) round($netRaw);

        $vatRate = $this->numericVatRate();
        $vat = (int) round($net * ($vatRate / 100));

        $this->forceFill([
            'net_cents' => $net,
            'vat_cents' => $vat,
            'total_cents' => $net + $vat,
        ]);

        return $this;
    }

    /**
     * Numeric VAT rate, treating "zw" / "np" / "oo" as 0.
     */
    public function numericVatRate(): int
    {
        $r = (string) $this->vat_rate;
        if (! is_numeric($r)) {
            return 0;
        }

        return (int) $r;
    }

    public static function vatRateOptions(): array
    {
        return [
            '23' => '23%',
            '8' => '8%',
            '5' => '5%',
            '0' => '0%',
            'zw' => 'zw. (zwolniona)',
            'np' => 'np. (nie podlega)',
            'oo' => 'oo. (odwrotne obciążenie)',
        ];
    }
}
