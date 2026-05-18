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
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'price_monthly_cents' => 'integer',
            'price_yearly_cents' => 'integer',
            'onboarding_fee_cents' => 'integer',
        ];
    }

    public function scopeForStables(Builder $query): Builder
    {
        return $query->where('audience', TenantType::Stable->value);
    }

    public function scopeForTransporters(Builder $query): Builder
    {
        return $query->where('audience', TenantType::Transporter->value);
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
