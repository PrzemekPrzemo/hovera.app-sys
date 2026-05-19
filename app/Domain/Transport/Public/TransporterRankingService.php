<?php

declare(strict_types=1);

namespace App\Domain\Transport\Public;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportReview;
use App\Models\Central\TransportServiceArea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Wspólny ranking zweryfikowanych transporterów. Używany przez:
 *   - `/przewoznicy` katalog (paginacja, filtry, sort options)
 *   - `/transport` landing (Top 10 widget — wywołanie `top()`)
 *
 * Sort priority: `is_featured DESC, review_avg DESC, review_count DESC,
 * created_at DESC`. Featured boost jest przed reviews bo to opłacony /
 * manualny override (admin decyduje). Patrz docs/TRANSPORT.md §16.
 *
 * Aggregaty (review_average, review_count, primary_voivodeship) doczepiane
 * w jednym GROUP BY zamiast N+1 — wyciągnięte z DirectoryController żeby
 * landing nie duplikował logiki.
 */
class TransporterRankingService
{
    private const TOP_CACHE_TTL_SECONDS = 300;

    /**
     * Top N zweryfikowanych transporterów dla landing'i.
     *
     * @return Collection<int, Tenant>
     */
    public function top(int $limit = 10): Collection
    {
        $limit = max(1, min(50, $limit));

        return Cache::remember(
            "transporters.top.{$limit}",
            self::TOP_CACHE_TTL_SECONDS,
            function () use ($limit): Collection {
                $base = $this->baseVerifiedQuery();
                $this->applyFeaturedAndRatingSort($base);

                $tenants = $base->limit($limit)->get();

                $this->attachAggregates($tenants);
                $this->attachPrimaryVoivodeships($tenants);

                return $tenants;
            },
        );
    }

    /**
     * Bazowe query — verified + active. Wspólne dla landing top'u i directory listingu.
     *
     * @return Builder<Tenant>
     */
    public function baseVerifiedQuery(): Builder
    {
        return Tenant::query()
            ->where('tenants.type', TenantType::Transporter)
            ->where('tenants.verification_status', VerificationStatus::Verified)
            ->whereIn('tenants.status', ['trialing', 'active', 'past_due'])
            ->select('tenants.*');
    }

    /**
     * Sort: featured boost → review aggregate → recency. LEFT JOIN do subquery
     * agregującej, żeby nie tracić transporterów bez recenzji (idą na koniec).
     *
     * @param  Builder<Tenant>  $query
     */
    public function applyFeaturedAndRatingSort(Builder $query): void
    {
        $sub = TransportReview::query()
            ->select([
                'transporter_tenant_id',
                DB::raw('AVG(rating) as avg_rating'),
                DB::raw('COUNT(*) as review_count'),
            ])
            ->where('status', 'published')
            ->whereNotNull('rating')
            ->groupBy('transporter_tenant_id');

        $query->leftJoinSub($sub, 'review_agg', function ($join) {
            $join->on('review_agg.transporter_tenant_id', '=', 'tenants.id');
        })
            ->orderByDesc('tenants.is_featured')
            ->orderByDesc(DB::raw('COALESCE(review_agg.avg_rating, 0)'))
            ->orderByDesc(DB::raw('COALESCE(review_agg.review_count, 0)'))
            ->orderByDesc('tenants.created_at');
    }

    /**
     * Doczepia review_average + review_count w jednym zapytaniu (anti N+1).
     *
     * @param  Collection<int, Tenant>|LengthAwarePaginator<int, Tenant>  $tenants
     */
    public function attachAggregates(Collection|LengthAwarePaginator $tenants): void
    {
        $collection = $tenants instanceof LengthAwarePaginator
            ? $tenants->getCollection()
            : $tenants;

        $ids = $collection->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $aggregates = TransportReview::query()
            ->select([
                'transporter_tenant_id',
                DB::raw('AVG(rating) as avg_rating'),
                DB::raw('COUNT(*) as review_count'),
            ])
            ->where('status', 'published')
            ->whereNotNull('rating')
            ->whereIn('transporter_tenant_id', $ids)
            ->groupBy('transporter_tenant_id')
            ->get()
            ->keyBy('transporter_tenant_id');

        $collection->transform(function (Tenant $t) use ($aggregates): Tenant {
            $row = $aggregates->get($t->id);
            $t->setAttribute('review_average', $row !== null ? round((float) $row->avg_rating, 2) : 0.0);
            $t->setAttribute('review_count', $row !== null ? (int) $row->review_count : 0);

            return $t;
        });
    }

    /**
     * Doczepia primary_voivodeship + all_voivodeships per tenant w jednym
     * zapytaniu (anti N+1).
     *
     * @param  Collection<int, Tenant>|LengthAwarePaginator<int, Tenant>  $tenants
     */
    public function attachPrimaryVoivodeships(Collection|LengthAwarePaginator $tenants): void
    {
        $collection = $tenants instanceof LengthAwarePaginator
            ? $tenants->getCollection()
            : $tenants;

        $ids = $collection->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $rows = TransportServiceArea::query()
            ->whereIn('transporter_tenant_id', $ids)
            ->orderBy('voivodeship')
            ->get(['transporter_tenant_id', 'voivodeship'])
            ->groupBy('transporter_tenant_id');

        $collection->transform(function (Tenant $t) use ($rows): Tenant {
            $list = $rows->get($t->id);
            $voivodeships = $list !== null
                ? $list->pluck('voivodeship')->all()
                : [];
            $t->setAttribute('primary_voivodeship', $voivodeships[0] ?? null);
            $t->setAttribute('all_voivodeships', $voivodeships);

            return $t;
        });
    }

    /**
     * Bust cache po zmianie featured / verification / review submit.
     * Wywoływane z TransporterResource action toggleFeatured + analogicznie
     * z TransportReviewInviteService (publish) — opcjonalne, TTL 5min złapie
     * przyrost organicznie.
     */
    public function flushTopCache(): void
    {
        foreach ([5, 10, 20, 50] as $size) {
            Cache::forget("transporters.top.{$size}");
        }
    }
}
