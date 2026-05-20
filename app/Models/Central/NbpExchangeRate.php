<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Cache średnich kursów NBP (tabela A). Patrz
 * docs/MARKETPLACE-ROADMAP.md "Multi-currency z NBP exchange rate".
 *
 * Snapshot per (currency_code, effective_date) — daily insert,
 * idempotent przez unique constraint.
 *
 * `rate_to_pln` = ile PLN za 1 jednostkę. Konwersja PLN→target:
 * `target_amount = pln_amount / rate_to_pln`.
 */
class NbpExchangeRate extends Model
{
    public $timestamps = false;

    protected $connection = 'central';

    protected $table = 'nbp_exchange_rates';

    protected $fillable = [
        'currency_code',
        'effective_date',
        'rate_to_pln',
        'source',
        'raw_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            // Trzymamy effective_date jako string (bez Carbon cast) —
            // analogicznie do FuelPrice, żeby updateOrCreate był
            // idempotentny w retry'u (Carbon serializacja może rozjechać
            // formaty 'Y-m-d' vs 'Y-m-d 00:00:00').
            'rate_to_pln' => 'decimal:4',
            'raw_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function scopeForCurrency(Builder $q, string $code): Builder
    {
        return $q->where('currency_code', strtoupper($code));
    }
}
