<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\Public\TransporterRankingService;
use App\Domain\Transport\ServiceAreas\TransportServiceAreaManager;
use App\Models\Central\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
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
 * Sort default `rating_desc`: featured boost → rating → recency. Ranking
 * + aggregaty wyciągnięte do TransporterRankingService — landing /transport
 * używa tego samego service'u.
 *
 * Patrz docs/TRANSPORT.md §16 oraz fragment §12 o dyskryminatorach marketplace.
 */
class TransporterDirectoryController extends Controller
{
    private const PER_PAGE = 20;

    /** @var list<string> */
    private const ALLOWED_SORTS = ['recent', 'rating_desc', 'name'];

    public function __construct(private readonly TransporterRankingService $ranking) {}

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

        $this->ranking->attachAggregates($transporters);
        $this->ranking->attachPrimaryVoivodeships($transporters);

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
        $base = $this->ranking->baseVerifiedQuery();

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

        // Sort. rating_desc używa featured-boost + review aggregate; recent/name
        // ignorują featured (user explicit chce inny sort, respektujemy intencję).
        match ($sort) {
            'name' => $base->orderBy('tenants.name'),
            'recent' => $base->orderByDesc('tenants.created_at'),
            'rating_desc' => $this->ranking->applyFeaturedAndRatingSort($base),
            default => $base->orderByDesc('tenants.created_at'),
        };

        return $base->paginate(
            perPage: self::PER_PAGE,
            page: max(1, $page),
        );
    }
}
