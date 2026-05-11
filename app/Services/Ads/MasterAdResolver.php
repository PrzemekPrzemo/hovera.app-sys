<?php

declare(strict_types=1);

namespace App\Services\Ads;

use App\Models\Central\MasterAd;
use App\Models\Central\MasterAdDismissal;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\User;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves which `MasterAd`s should display for the current user/tenant.
 * Filters by:
 *   - active + within date window (MasterAd::current scope)
 *   - targeting JSON matches current user/tenant/role/locale
 *   - user hasn't dismissed yet
 */
class MasterAdResolver
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * @return Collection<int,MasterAd>
     */
    public function forUser(User $user, ?Tenant $tenant = null): Collection
    {
        $tenant ??= $this->tenants->current();
        $role = $this->roleFor($user, $tenant);

        $dismissed = MasterAdDismissal::query()
            ->where('user_id', $user->id)
            ->pluck('ad_id')
            ->all();

        return MasterAd::query()
            ->current()
            ->when($dismissed !== [], fn ($q) => $q->whereNotIn('id', $dismissed))
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (MasterAd $ad) => $ad->appliesTo($user, $tenant, $role))
            ->values();
    }

    public function dismiss(User $user, string $adId): void
    {
        MasterAdDismissal::query()->updateOrCreate(
            ['ad_id' => $adId, 'user_id' => $user->id],
            ['dismissed_at' => now()],
        );
    }

    public function trackImpression(MasterAd $ad): void
    {
        $ad->newQuery()->whereKey($ad->id)->increment('impressions_count');
    }

    public function trackClick(MasterAd $ad): void
    {
        $ad->newQuery()->whereKey($ad->id)->increment('clicks_count');
    }

    private function roleFor(User $user, ?Tenant $tenant): ?string
    {
        if (! $tenant) {
            return null;
        }

        $membership = TenantMembership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->whereNull('revoked_at')
            ->first();

        return $membership?->role;
    }
}
