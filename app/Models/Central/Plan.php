<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'plans';

    protected $fillable = [
        'code', 'name', 'currency',
        'price_monthly_cents', 'price_yearly_cents',
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
        ];
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
