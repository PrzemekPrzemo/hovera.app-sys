<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Transport\ServiceAreas\TransportServiceAreaManager;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\TransportLead;
use App\Models\Central\TransportLeadDispatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Publiczna giełda otwartych zapytań transportowych pod
 * `/transport/marketplace`. Czwarta ścieżka odkrywania marketplace'u
 * (obok bezpośredniego /t/{slug}, broadcast'u /transport/zapytanie i
 * katalogu /przewoznicy).
 *
 * Cel: verified transporter, zalogowany w panelu /transport, może
 * przeglądać otwarte leady spoza swojego dispatch'u (np. spoza service
 * area) i dorzucić ofertę. Anonim widzi listę "co się dzieje na rynku"
 * — discovery dla potencjalnych nowych transporterów.
 *
 * Privacy: lead card pokazuje TYLKO województwa pickup/dropoff, NIE
 * pełen adres. Pełna trasa + dane klienta dostępne dopiero dla
 * zalogowanego verified transportera w panelu /transport (LeadResource
 * via dispatch albo "podejrzyj lead" akcja).
 *
 * Listing rules:
 *   - status = 'open' (kwoty już istniejące nie są ukryte — patrz spec
 *     PR 8 — open board to "open for new offers")
 *   - expires_at > now() (nie pokazujemy wygasłych)
 *   - preferred_date >= today (nie pokazujemy past dates)
 *
 * Filtry (query params):
 *   - voivodeship: pickup_voivodeship LUB dropoff_voivodeship
 *   - within_days: 7 / 14 / 30 (preferred_date <= today + N)
 *   - min_horses:  1 / 2 / 3 / 4 (horse_count >= N)
 *
 * Sortowanie domyślne: preferred_date asc (najpilniejsze najpierw).
 */
class TransportMarketplaceController extends Controller
{
    private const PER_PAGE = 20;

    /** @var list<int> */
    private const ALLOWED_WITHIN_DAYS = [7, 14, 30];

    /** @var list<int> */
    private const ALLOWED_MIN_HORSES = [1, 2, 3, 4];

    public function index(Request $request): View|Response
    {
        $voivodeships = TransportServiceAreaManager::allVoivodeships();

        $selectedVoivodeship = $this->normaliseVoivodeship(
            (string) $request->query('voivodeship', ''),
            $voivodeships,
        );

        $withinDays = $this->normaliseInt(
            (string) $request->query('within_days', ''),
            self::ALLOWED_WITHIN_DAYS,
        );

        $minHorses = $this->normaliseInt(
            (string) $request->query('min_horses', ''),
            self::ALLOWED_MIN_HORSES,
        );

        $leads = $this->fetchLeads(
            voivodeship: $selectedVoivodeship,
            withinDays: $withinDays,
            minHorses: $minHorses,
            page: (int) $request->query('page', 1),
        );

        // Filtry persisted w paginator linkach + URL helpers.
        $filterQuery = array_filter([
            'voivodeship' => $selectedVoivodeship,
            'within_days' => $withinDays,
            'min_horses' => $minHorses,
        ], static fn ($v) => $v !== null);

        return response()->view('public.transport.marketplace', [
            'leads' => $leads,
            'voivodeships' => $voivodeships,
            'selectedVoivodeship' => $selectedVoivodeship,
            'withinDays' => $withinDays,
            'minHorses' => $minHorses,
            'filterQuery' => $filterQuery,
            'allowedWithinDays' => self::ALLOWED_WITHIN_DAYS,
            'allowedMinHorses' => self::ALLOWED_MIN_HORSES,
        ])->header('Cache-Control', 'public, max-age=60, s-maxage=180');
    }

    /**
     * "Złóż ofertę" CTA z karty leada. Verified transporter opt-inuje do
     * leada spoza swojego dispatchu: tworzymy `transport_lead_dispatches`
     * row (idempotent — duplikatów nie ma dzięki unique constraintowi) i
     * redirectujemy do LeadResource view w panelu /transport.
     *
     * Auth flow:
     *   - Nie zalogowany → /transport/login (Filament panel) z `intended`
     *     URL, żeby po zalogowaniu trafić tu z powrotem.
     *   - Zalogowany, ale aktywny tenant nie jest verified transporterem
     *     → flash error + redirect do katalogu (CTA "zostań przewoźnikiem").
     *   - Zalogowany verified transporter → claim + redirect na widok leada.
     *
     * Idempotent: drugi click przez tego samego transportera nie tworzy
     * duplikatu, po prostu reuse'uje istniejący dispatch row.
     */
    public function claim(Request $request, string $leadId): RedirectResponse
    {
        $user = Auth::user();
        if ($user === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->to('/transport/login');
        }

        $lead = TransportLead::query()
            ->where('id', $leadId)
            ->where('status', 'open')
            ->where('expires_at', '>', now())
            ->first();

        if ($lead === null) {
            return redirect()->route('public.transport.marketplace')
                ->with('error', __('public/transport_marketplace.claim.lead_unavailable'));
        }

        $tenant = $this->resolveVerifiedTransporterFor($user->id);
        if ($tenant === null) {
            return redirect()->route('public.transporters.directory')
                ->with('error', __('public/transport_marketplace.claim.not_verified_transporter'));
        }

        // Idempotent claim — firstOrCreate gwarantuje że drugi click nie
        // tworzy duplikatu (unique constraint na (lead_id, transporter_tenant_id)).
        TransportLeadDispatch::query()->firstOrCreate(
            [
                'lead_id' => $lead->id,
                'transporter_tenant_id' => $tenant->id,
            ],
            [
                'view_status' => 'unseen',
                'notified_at' => now(),
            ],
        );

        return redirect()->to('/transport/leads/'.$lead->id);
    }

    /**
     * Pobierz aktywny tenant usera, jeśli to verified transporter.
     * Wybieramy pierwszego (po updated_at desc) — w praktyce użytkownik
     * mający multi-tenant context używa switcher'a, a tu mamy publiczny
     * marketplace bez kontekstu tenant'a w sesji.
     */
    private function resolveVerifiedTransporterFor(string $userId): ?Tenant
    {
        $memberships = TenantMembership::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->orderByDesc('updated_at')
            ->pluck('tenant_id')
            ->all();

        if ($memberships === []) {
            return null;
        }

        foreach (Tenant::query()->whereIn('id', $memberships)->get() as $candidate) {
            if ($candidate->isVerifiedTransporter()) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return LengthAwarePaginator<int, TransportLead>
     */
    private function fetchLeads(
        ?string $voivodeship,
        ?int $withinDays,
        ?int $minHorses,
        int $page,
    ): LengthAwarePaginator {
        $now = now();
        $today = $now->toDateString();

        $query = TransportLead::query()
            ->where('status', 'open')
            ->where('expires_at', '>', $now)
            ->whereDate('preferred_date', '>=', $today);

        if ($voivodeship !== null) {
            // Lead pasuje gdy pickup ALBO dropoff jest w wybranym województwie.
            // Większość transporterów obsługuje konkretne województwa —
            // pasujemy lead gdy KTÓRYKOLWIEK z punktów leży w obszarze
            // zainteresowania.
            $query->where(function (Builder $q) use ($voivodeship) {
                $q->where('pickup_voivodeship', $voivodeship)
                    ->orWhere('dropoff_voivodeship', $voivodeship);
            });
        }

        if ($withinDays !== null) {
            $query->whereDate('preferred_date', '<=', $now->copy()->addDays($withinDays)->toDateString());
        }

        if ($minHorses !== null) {
            $query->where('horse_count', '>=', $minHorses);
        }

        return $query
            ->orderBy('preferred_date')
            ->orderByDesc('created_at')
            ->paginate(
                perPage: self::PER_PAGE,
                page: max(1, $page),
            );
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
     * @param  list<int>  $allowed
     */
    private function normaliseInt(string $value, array $allowed): ?int
    {
        $value = trim($value);
        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }
        $n = (int) $value;

        return in_array($n, $allowed, true) ? $n : null;
    }
}
