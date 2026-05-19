<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanAddon extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'plan_addons';

    public const TYPE_ONE_TIME = 'one_time';

    public const TYPE_RECURRING_MONTHLY = 'recurring_monthly';

    protected $fillable = [
        'plan_id', 'is_global', 'code', 'name', 'description',
        'addon_type', 'resource_type', 'quantity',
        'price_monthly_cents', 'price_yearly_cents',
        'prices_per_currency',
        'currency',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_global' => 'boolean',
            'quantity' => 'integer',
            'price_monthly_cents' => 'integer',
            'price_yearly_cents' => 'integer',
            'prices_per_currency' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Cena add-onu w danej walucie. Dla `one_time` addonów semantycznie
     * używamy pola `price_monthly_cents` jako kwoty jednorazowej (nie ma
     * sensu trzymać osobnego pola). Logika identyczna jak `Plan::priceFor`.
     */
    public function priceFor(string $currency, string $cycle = 'monthly'): ?int
    {
        $field = $cycle === 'yearly' ? 'price_yearly_cents' : 'price_monthly_cents';
        $base = (int) ($this->{$field} ?? 0);

        $currency = strtoupper($currency);
        $baseCurrency = strtoupper((string) ($this->currency ?? 'PLN'));
        if ($currency === $baseCurrency) {
            return $base > 0 ? $base : null;
        }

        $overlay = $this->prices_per_currency ?? [];
        $value = data_get($overlay, $currency.'.'.$cycle.'_cents');

        return $value !== null ? (int) $value : null;
    }
}
