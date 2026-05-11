<?php

declare(strict_types=1);

namespace App\Models\Central;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reklama / komunikat publikowany przez master admina do paneli stajni.
 * Targeting przez JSON `targeting`. Display orkiestruje `MasterAdResolver`.
 */
class MasterAd extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'master_ads';

    protected $fillable = [
        'title', 'body', 'cta_label', 'cta_url',
        'placement', 'variant', 'is_active',
        'starts_at', 'ends_at',
        'targeting',
        'impressions_count', 'clicks_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'targeting' => 'array',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
        ];
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(MasterAdDismissal::class, 'ad_id');
    }

    public function scopeCurrent(Builder $q): Builder
    {
        $now = Carbon::now();

        return $q->where('is_active', true)
            ->where(fn ($qq) => $qq->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($qq) => $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Czy reklama matchuje danego usera z kontekstem (rola w tenancie + lokal).
     */
    public function appliesTo(User $user, ?Tenant $tenant = null, ?string $role = null): bool
    {
        $t = $this->targeting ?? [];

        // user_ids override — gdy non-empty, matchujemy tylko po user_id
        if (! empty($t['user_ids'])) {
            return in_array((string) $user->id, (array) $t['user_ids'], true);
        }

        if (! empty($t['tenant_ids']) && $tenant && ! in_array((string) $tenant->id, (array) $t['tenant_ids'], true)) {
            return false;
        }

        if (! empty($t['roles']) && $role && ! in_array($role, (array) $t['roles'], true)) {
            return false;
        }

        if (! empty($t['countries']) && $tenant && ! in_array((string) $tenant->country, (array) $t['countries'], true)) {
            return false;
        }

        if (! empty($t['locales']) && $user->locale && ! in_array((string) $user->locale, (array) $t['locales'], true)) {
            return false;
        }

        return true;
    }
}
