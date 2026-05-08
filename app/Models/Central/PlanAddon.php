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

    protected $fillable = [
        'plan_id', 'code', 'name', 'description',
        'resource_type', 'quantity',
        'price_monthly_cents', 'price_yearly_cents',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'quantity' => 'integer',
            'price_monthly_cents' => 'integer',
            'price_yearly_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
