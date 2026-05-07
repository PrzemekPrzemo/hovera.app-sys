<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\StableActivityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StableActivity extends TenantModel
{
    use SoftDeletes;

    protected $table = 'stable_activities';

    protected $fillable = [
        'horse_id', 'type', 'performed_at', 'performed_by',
        'summary', 'details', 'cost_cents', 'metadata',
        'created_by_central_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => StableActivityType::class,
            'performed_at' => 'datetime',
            'cost_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function scopeForHorse(Builder $query, string $horseId): Builder
    {
        return $query->where('horse_id', $horseId);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('performed_at', '>=', now()->subDays($days));
    }

    public function costFormatted(): ?string
    {
        if ($this->cost_cents === null) {
            return null;
        }

        return number_format($this->cost_cents / 100, 2, ',', ' ').' zł';
    }
}
