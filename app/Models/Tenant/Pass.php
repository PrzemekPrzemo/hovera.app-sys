<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\PassStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Pass extends TenantModel
{
    use SoftDeletes;

    protected $table = 'passes';

    protected $fillable = [
        'client_id', 'name',
        'total_uses', 'remaining_uses',
        'valid_from', 'valid_until',
        'price_cents', 'status',
        'cancellation_policy_hours',
        'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'metadata' => 'array',
            'status' => PassStatus::class,
            'total_uses' => 'integer',
            'remaining_uses' => 'integer',
            'price_cents' => 'integer',
            'cancellation_policy_hours' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uses(): HasMany
    {
        return $this->hasMany(PassUse::class);
    }

    public function activeUses(): HasMany
    {
        return $this->uses()->whereNull('restored_at');
    }

    /**
     * "Will accept a new use right now."
     */
    public function isUsable(): bool
    {
        if ($this->status !== PassStatus::Active) {
            return false;
        }
        if ($this->remaining_uses <= 0) {
            return false;
        }
        if ($this->valid_from && $this->valid_from->isFuture()) {
            return false;
        }
        if ($this->valid_until && $this->valid_until->endOfDay()->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Hot-path filter for picking a usable pass for a client. Status
     * isn't required to be 'active' here because we want to exclude
     * cancelled / expired explicitly via dedicated checks; relying on
     * status alone misses passes whose denormalised status is stale.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->where('remaining_uses', '>', 0)
            ->whereIn('status', [PassStatus::Active->value])
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString());
            });
    }

    /**
     * Recomputes denormalised columns from the use history. Called by
     * PassUseManager whenever uses change. Cheap (single COUNT).
     */
    public function recomputeFromUses(): void
    {
        $consumed = $this->uses()->whereNull('restored_at')->count();
        $remaining = max(0, $this->total_uses - $consumed);

        $newStatus = match (true) {
            $this->status === PassStatus::Cancelled => PassStatus::Cancelled,
            $this->valid_until && $this->valid_until->endOfDay()->isPast() => PassStatus::Expired,
            $remaining <= 0 => PassStatus::Exhausted,
            default => PassStatus::Active,
        };

        $this->forceFill([
            'remaining_uses' => $remaining,
            'status' => $newStatus->value,
        ])->save();
    }

    public function effectiveCancellationHours(int $tenantDefaultHours): int
    {
        return $this->cancellation_policy_hours ?? $tenantDefaultHours;
    }

    public function isWithinCancellationWindow(Carbon $bookingStartsAt, int $tenantDefaultHours): bool
    {
        $threshold = $bookingStartsAt->copy()->subHours($this->effectiveCancellationHours($tenantDefaultHours));

        return now()->lte($threshold);
    }
}
