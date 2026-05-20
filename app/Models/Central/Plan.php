<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\TenantType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'plans';

    protected $fillable = [
        'code', 'audience', 'name', 'currency',
        'price_monthly_cents', 'price_yearly_cents',
        'prices_per_currency',
        'onboarding_fee_cents',
        'stripe_price_monthly_id', 'stripe_price_yearly_id',
        'limits', 'features',
        'is_active', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'prices_per_currency' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'price_monthly_cents' => 'integer',
            'price_yearly_cents' => 'integer',
            'onboarding_fee_cents' => 'integer',
        ];
    }

    /**
     * Lista wszystkich walut wspieranych przez plany transportowe.
     * Pierwsza pozycja to PLN (default base currency). Marketing spec
     * (hovera.app/produkt/transport/) wymaga: PLN, EUR, GBP, AUD, NZD.
     *
     * @return list<string>
     */
    public static function supportedCurrencies(): array
    {
        return ['PLN', 'EUR', 'GBP', 'AUD', 'NZD'];
    }

    /**
     * Zwraca cenę w cents dla podanej waluty i cyklu. Dla bazowej waluty
     * planu czyta `price_monthly_cents`/`price_yearly_cents`; dla pozostałych
     * sięga do JSON `prices_per_currency.{currency}.{cycle}_cents`.
     *
     * Zwraca `null` gdy:
     *  - plan jest "skontaktuj się" (cena = null lub 0 dla Enterprise),
     *  - waluta nieznana w overlay'u i nie jest base'em.
     *
     * @param  'monthly'|'yearly'  $cycle
     */
    public function priceFor(string $currency, string $cycle = 'monthly'): ?int
    {
        // Enterprise / "contact sales" → null jako sygnał do warstwy
        // prezentacji żeby renderować CTA zamiast ceny.
        if (! empty($this->features['is_custom_pricing'])
            || (data_get($this->features ?? [], 'marketing_cta') === 'contact_sales')) {
            return null;
        }

        $field = $cycle === 'yearly' ? 'price_yearly_cents' : 'price_monthly_cents';
        $base = (int) ($this->{$field} ?? 0);

        $currency = strtoupper($currency);
        if ($currency === strtoupper((string) ($this->currency ?? 'PLN'))) {
            return $base > 0 ? $base : null;
        }

        $overlay = $this->prices_per_currency ?? [];
        $value = data_get($overlay, $currency.'.'.$cycle.'_cents');

        return $value !== null ? (int) $value : null;
    }

    public function scopeForStables(Builder $query): Builder
    {
        return $query->where('audience', TenantType::Stable->value);
    }

    public function scopeForTransporters(Builder $query): Builder
    {
        return $query->where('audience', TenantType::Transporter->value);
    }

    public function scopeForHorseOwners(Builder $query): Builder
    {
        return $query->where('audience', TenantType::HorseOwner->value);
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(PlanAddon::class)->orderBy('sort_order');
    }
}
