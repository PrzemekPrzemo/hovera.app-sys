<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\ServiceAreas\TransportServiceAreaManager;
use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use App\Models\Central\TransportReview;
use App\Models\Central\TransportServiceArea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Publiczny katalog zweryfikowanych przewoźników pod /przewoznicy.
 *
 * Trzecia ścieżka odkrywania marketplace'u (obok bezpośredniego /t/{slug}
 * i broadcast'u /transport/zapytanie). Zamyka discovery gap — wcześniej
 * profil był osiągalny tylko przez bezpośredni link lub sitemap.
 *
 * Listing rules:
 * - tylko verification_status = verified (jak /t/{slug})
 * - tylko status ∈ {trialing, active, past_due} (suspended/cancelled = niewidoczni)
 * - soft-deleted (deleted_at) wykluczeni automatycznie przez Tenant::query()
 *
 * Sort default: ocena malejąco, potem recency (najnowsze rejestracje powyżej).
 * Cache aggregates w jednym zapytaniu zamiast N+1 — patrz attachAggregates().
 *
 * Patrz docs/TRANSPORT.md §16 oraz fragment §12 o dyskryminatorach marketplace.
 */
class TransporterDirectoryController extends Controller
{
    private const PER_PAGE = 20;

    /** @var list<string> */
    private const ALLOWED_SORTS = ['recent', 'rating_desc', 'name'];

    public function index(Request $request): View|Response
    {
        $voivodeships = TransportServiceAreaManager::allVoivodeships();

        $selectedVoivodeship = $this->normaliseVoivodeship(
            (string) $request->query('voivodeship', ''),
            $voivodeships,
        );

        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) > 80) {
            $query = mb_substr($query, 0, 80);
        }

        $sort = (string) $request->query('sort', 'rating_desc');
        if (! in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'rating_desc';
        }

        $transporters = $this->fetchTransporters(
            voivodeship: $selectedVoivodeship,
            query: $query,
            sort: $sort,
            page: (int) $request->query('page', 1),
        );

        $this->attachAggregates($transporters);
        $this->attachPrimaryVoivodeships($transporters);

        // Total count = paginator total (filtered). Hero string used both
        // przez SEO meta i w nagłówku — nie róbmy dwóch zapytań.
        $totalVerifiedCount = $transporters->total();

        return response()->view('public.transport.directory', [
            'transporters' => $transporters,
            'voivodeships' => $voivodeships,
            'selectedVoivodeship' => $selectedVoivodeship,
            'query' => $query,
            'sort' => $sort,
            'totalVerifiedCount' => $totalVerifiedCount,
            // Persist filters in pagination links + URL helpers.
            'filterQuery' => array_filter([
                'voivodeship' => $selectedVoivodeship,
                'q' => $query !== '' ? $query : null,
                'sort' => $sort !== 'rating_desc' ? $sort : null,
            ], static fn ($v) => $v !== null && $v !== ''),
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }

    /**
     * @param  list<string>  $voivodeships
     */
    private function normaliseVoivodeship(string $value, array $voivodeships): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === 'all') {
            return null;
        }

        return in_array($value, $voivodeships, true) ? $value : null;
    }

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    private function fetchTransporters(
        ?string $voivodeship,
        string $query,
        string $sort,
        int $page,
    ): LengthAwarePaginator {
        $base = Tenant::query()
            ->where('tenants.type', TenantType::Transporter)
            ->where('tenants.verification_status', VerificationStatus::Verified)
            ->whereIn('tenants.status', ['trialing', 'active', 'past_due'])
            ->select('tenants.*');

        if ($voivodeship !== null) {
            // DISTINCT bo transporter może mieć wiele rekordów w
            // transport_service_areas, ale tu joinujemy po jednym
            // konkretnym województwie — w praktyce 1 row per tenant.
            $base->join('transport_service_areas as tsa', 'tsa.transporter_tenant_id', '=', 'tenants.id')
                ->where('tsa.voivodeship', $voivodeship)
                ->distinct();
        }

        if ($query !== '') {
            $needle = '%'.mb_strtolower($query).'%';
            $base->whereRaw('LOWER(tenants.name) LIKE ?', [$needle]);
        }

        // Sort. rating_desc = LEFT JOIN aggregate subquery i sortujemy po avg.
        match ($sort) {
            'name' => $base->orderBy('tenants.name'),
            'recent' => $base->orderByDesc('tenants.created_at'),
            'rating_desc' => $this->applyRatingSort($base),
            default => $base->orderByDesc('tenants.created_at'),
        };

        return $base->paginate(
            perPage: self::PER_PAGE,
            page: max(1, $page),
        );
    }

    /**
     * LEFT JOIN do subquery agregującej średnią ocen — żeby nie tracić
     * transporterów bez recenzji (idą na koniec listy). Ranking:
     * najpierw avg DESC, potem count DESC (więcej opinii = bardziej
     * zaufany), na końcu created_at DESC (nowi widoczni).
     *
     * @param  Builder<Tenant>  $base
     */
    private function applyRatingSort($base): void
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

        $base->leftJoinSub($sub, 'review_agg', function ($join) {
            $join->on('review_agg.transporter_tenant_id', '=', 'tenants.id');
        })
            ->orderByDesc(DB::raw('COALESCE(review_agg.avg_rating, 0)'))
            ->orderByDesc(DB::raw('COALESCE(review_agg.review_count, 0)'))
            ->orderByDesc('tenants.created_at');
    }

    /**
     * Doczepia do każdego tenanta w paginatorze pola review_average i
     * review_count w JEDNYM zapytaniu (anti N+1). Nie wołamy
     * TransportReview::aggregateFor() per-row — to byłoby 20 cache hitów
     * w najlepszym wypadku, 20 GROUP BY w najgorszym.
     *
     * @param  LengthAwarePaginator<int, Tenant>  $transporters
     */
    private function attachAggregates(LengthAwarePaginator $transporters): void
    {
        $ids = $transporters->getCollection()->pluck('id')->all();
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

        $transporters->getCollection()->transform(function (Tenant $t) use ($aggregates): Tenant {
            $row = $aggregates->get($t->id);
            $t->setAttribute('review_average', $row !== null ? round((float) $row->avg_rating, 2) : 0.0);
            $t->setAttribute('review_count', $row !== null ? (int) $row->review_count : 0);

            return $t;
        });
    }

    /**
     * Doczepia primary voivodeship (pierwszy z listy) per tenant w jednym
     * zapytaniu. Karta na liście pokazuje to jako pill — najbardziej
     * relewantne info dla użytkownika filtrującego po regionie.
     *
     * @param  LengthAwarePaginator<int, Tenant>  $transporters
     */
    private function attachPrimaryVoivodeships(LengthAwarePaginator $transporters): void
    {
        $ids = $transporters->getCollection()->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $rows = TransportServiceArea::query()
            ->whereIn('transporter_tenant_id', $ids)
            ->orderBy('voivodeship')
            ->get(['transporter_tenant_id', 'voivodeship'])
            ->groupBy('transporter_tenant_id');

        $transporters->getCollection()->transform(function (Tenant $t) use ($rows): Tenant {
            $list = $rows->get($t->id);
            $voivodeships = $list !== null
                ? $list->pluck('voivodeship')->all()
                : [];
            $t->setAttribute('primary_voivodeship', $voivodeships[0] ?? null);
            $t->setAttribute('all_voivodeships', $voivodeships);

            return $t;
        });
    }
}
