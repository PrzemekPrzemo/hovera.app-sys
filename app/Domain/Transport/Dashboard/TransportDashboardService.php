<?php

declare(strict_types=1);

namespace App\Domain\Transport\Dashboard;

use App\Enums\QuoteStatus;
use App\Enums\TransportInvoiceStatus;
use App\Models\Central\TransportLeadResponse;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportInvoice;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * KPI + agregaty dla dashboard'u firmy transportowej. Patrz docs/TRANSPORT.md
 * (krok E z feedbacku produkcyjnego).
 *
 * Wszystko per-tenant — call'ujemy z kontekstu aktywnego tenant'a.
 * Zwraca pure arrays żeby widgety mogły je trywialnie renderować.
 */
class TransportDashboardService
{
    /**
     * 4 KPI: MRR bieżący miesiąc, należności (issued), przeterminowane FV,
     * oferty do akceptu (sent + nie wygasłe).
     *
     * @return array{
     *     mrr_month_cents:int,
     *     receivables_cents:int,
     *     overdue_count:int,
     *     overdue_cents:int,
     *     pending_quotes:int,
     * }
     */
    public function kpi(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $mrr = (int) TransportInvoice::query()
            ->where('status', TransportInvoiceStatus::Paid->value)
            ->where('paid_at', '>=', $monthStart)
            ->sum('total_cents');

        $receivables = (int) TransportInvoice::query()
            ->whereIn('status', [
                TransportInvoiceStatus::Issued->value,
                TransportInvoiceStatus::Overdue->value,
            ])
            ->sum('total_cents');

        $overdueQ = TransportInvoice::query()
            ->whereIn('status', [TransportInvoiceStatus::Issued->value, TransportInvoiceStatus::Overdue->value])
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', $today);

        $overdueCount = (int) (clone $overdueQ)->count();
        $overdueCents = (int) (clone $overdueQ)->sum('total_cents');

        $pendingQuotes = (int) Quote::query()
            ->where('status', QuoteStatus::Sent->value)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $today);
            })
            ->count();

        return [
            'mrr_month_cents' => $mrr,
            'receivables_cents' => $receivables,
            'overdue_count' => $overdueCount,
            'overdue_cents' => $overdueCents,
            'pending_quotes' => $pendingQuotes,
        ];
    }

    /**
     * Top 5 zaakceptowanych ofert bez wystawionej FV — księgowy backlog.
     *
     * @return Collection<int, Quote>
     */
    public function pendingInvoices(int $limit = 5)
    {
        $invoicedQuoteIds = TransportInvoice::query()
            ->whereNotNull('quote_id')
            ->pluck('quote_id')
            ->all();

        return Quote::query()
            ->where('status', QuoteStatus::Accepted->value)
            ->whereNotIn('id', $invoicedQuoteIds)
            ->orderByDesc('accepted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Top korytarze: pickup_address → dropoff_address pogrupowane.
     *
     * @return array<int, array{from:string, to:string, count:int, share:float}>
     */
    public function topCorridors(int $limit = 10): array
    {
        // Liczymy korytarze ze quotes — zarówno wysłane, zaakceptowane jak
        // i zafakturowane (oferty pokazują 100% historycznego biznesu, FV
        // tylko zafakturowane).
        $rows = Quote::query()
            ->selectRaw('pickup_address as from_addr, dropoff_address as to_addr, COUNT(*) as cnt')
            ->whereNotIn('status', [
                QuoteStatus::Draft->value,
                QuoteStatus::Withdrawn->value,
            ])
            ->groupBy('pickup_address', 'dropoff_address')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get()
            ->toArray();

        $total = array_sum(array_column($rows, 'cnt')) ?: 1;

        return array_map(static fn ($r) => [
            'from' => self::shortenAddress($r['from_addr']),
            'to' => self::shortenAddress($r['to_addr']),
            'count' => (int) $r['cnt'],
            'share' => round(((int) $r['cnt']) / $total * 100, 1),
        ], $rows);
    }

    /**
     * Akcept. oferty z preferred_date = dziś + jutro (dispatcher view).
     *
     * @return array{today: Collection<int, Quote>, tomorrow: Collection<int, Quote>}
     */
    public function upcomingTransports(): array
    {
        $today = Carbon::today();

        return [
            'today' => Quote::query()
                ->where('status', QuoteStatus::Accepted->value)
                ->whereDate('preferred_date', $today)
                ->orderBy('preferred_time')
                ->get(),
            'tomorrow' => Quote::query()
                ->where('status', QuoteStatus::Accepted->value)
                ->whereDate('preferred_date', $today->copy()->addDay())
                ->orderBy('preferred_time')
                ->get(),
        ];
    }

    /**
     * Skraca długi adres do "Miasto" (split po pierwszym przecinku, jeśli jest).
     */
    private static function shortenAddress(?string $address): string
    {
        if (! $address) {
            return '—';
        }
        $parts = explode(',', $address, 2);

        return trim($parts[count($parts) - 1] ?: $parts[0]);
    }

    /**
     * KPI lead-flow z centralnego DB. Liczy:
     *   - leads_week: ile leadów dotarło do transportera w ciągu ostatnich 7 dni
     *     (wiersze w transport_lead_responses gdzie transporter_tenant_id =
     *     bieżący tenant + created_at w oknie 7d).
     *   - leads_week_delta: różnica procentowa vs poprzednie 7 dni (NULL gdy
     *     poprzednie okno = 0 i bieżące > 0; 0.0 gdy oba 0).
     *   - win_rate_30d: accepted / total spośród naszych odpowiedzi z ostatnich
     *     30 dni. NULL gdy mianownik = 0.
     *   - win_rate_30d_delta: punkty procentowe vs poprzednie 30 dni.
     *
     * @return array{
     *     leads_week:int,
     *     leads_week_delta:?float,
     *     win_rate_30d:?float,
     *     win_rate_30d_delta:?float,
     * }
     */
    public function leadFlowKpi(): array
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return [
                'leads_week' => 0,
                'leads_week_delta' => null,
                'win_rate_30d' => null,
                'win_rate_30d_delta' => null,
            ];
        }

        $now = Carbon::now();
        $weekAgo = $now->copy()->subWeek();
        $twoWeeksAgo = $now->copy()->subWeeks(2);
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sixtyDaysAgo = $now->copy()->subDays(60);

        $leadsWeek = (int) TransportLeadResponse::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->where('created_at', '>=', $weekAgo)
            ->count();

        $leadsPrevWeek = (int) TransportLeadResponse::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->where('created_at', '>=', $twoWeeksAgo)
            ->where('created_at', '<', $weekAgo)
            ->count();

        $leadsDelta = self::deltaPct($leadsWeek, $leadsPrevWeek);

        $win30Total = (int) TransportLeadResponse::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $win30Accepted = (int) TransportLeadResponse::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->where('status', 'accepted')
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();
        $winRate = $win30Total > 0 ? round($win30Accepted / $win30Total * 100, 1) : null;

        $winPrevTotal = (int) TransportLeadResponse::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->where('created_at', '>=', $sixtyDaysAgo)
            ->where('created_at', '<', $thirtyDaysAgo)
            ->count();
        $winPrevAccepted = (int) TransportLeadResponse::query()
            ->where('transporter_tenant_id', $tenant->id)
            ->where('status', 'accepted')
            ->where('created_at', '>=', $sixtyDaysAgo)
            ->where('created_at', '<', $thirtyDaysAgo)
            ->count();
        $winPrevRate = $winPrevTotal > 0 ? round($winPrevAccepted / $winPrevTotal * 100, 1) : null;

        $winDelta = ($winRate === null || $winPrevRate === null) ? null : round($winRate - $winPrevRate, 1);

        return [
            'leads_week' => $leadsWeek,
            'leads_week_delta' => $leadsDelta,
            'win_rate_30d' => $winRate,
            'win_rate_30d_delta' => $winDelta,
        ];
    }

    /**
     * Transporty zaplanowane w oknie [dziś, dziś + $days]. Inkluduje statusy
     * accepted oraz invoiced (FV wystawiona ale transport jeszcze przed nami).
     * Sortowanie po preferred_date asc, limit żeby widget się nie rozjechał.
     *
     * @return Collection<int, Quote>
     */
    public function upcomingTransportsWeek(int $days = 7, int $limit = 10)
    {
        $today = Carbon::today();
        $end = $today->copy()->addDays($days);

        $statuses = [QuoteStatus::Accepted->value];
        // Invoiced status w QuoteStatus tylko gdy enum to wspiera — sprawdzamy
        // safe, bo enum może nie zawierać tej wartości w starszych deployach.
        if (defined(QuoteStatus::class.'::Invoiced')) {
            $statuses[] = QuoteStatus::Invoiced->value;
        }

        return Quote::query()
            ->whereIn('status', $statuses)
            ->whereDate('preferred_date', '>=', $today)
            ->whereDate('preferred_date', '<=', $end)
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Top N FV (paid) z ostatnich $days dni, sortowane wg kwoty desc. Pomaga
     * transporterowi zobaczyć "best customers" tj. kto wnosi największe
     * przychody.
     *
     * @return Collection<int, TransportInvoice>
     */
    public function topPaidInvoices(int $limit = 5, int $days = 90)
    {
        $since = Carbon::now()->subDays($days);

        return TransportInvoice::query()
            ->where('status', TransportInvoiceStatus::Paid->value)
            ->where('paid_at', '>=', $since)
            ->orderByDesc('total_cents')
            ->limit($limit)
            ->get();
    }

    /**
     * Heatmapa par województw na podstawie odpowiedzi transportera (central
     * DB) z ostatnich $days dni. Zwraca top N par + share total.
     *
     * Używamy lead_id JOIN na transport_leads bo voivodeships są tylko na
     * leadzie. Liczymy wyłącznie wiersze gdzie pickup/dropoff_voivodeship
     * niepuste (defensywnie — historyczne wiersze sprzed pola mogą być NULL).
     *
     * @return array<int, array{from:string, to:string, count:int, share:float}>
     */
    public function topVoivodeshipPairs(int $limit = 10, int $days = 90): array
    {
        $tenant = app(TenantManager::class)->current();
        if (! $tenant) {
            return [];
        }

        $since = Carbon::now()->subDays($days);

        $rows = TransportLeadResponse::query()
            ->from('transport_lead_responses as r')
            ->join('transport_leads as l', 'l.id', '=', 'r.lead_id')
            ->where('r.transporter_tenant_id', $tenant->id)
            ->where('r.created_at', '>=', $since)
            ->whereNotNull('l.pickup_voivodeship')
            ->whereNotNull('l.dropoff_voivodeship')
            ->where('l.pickup_voivodeship', '!=', '')
            ->where('l.dropoff_voivodeship', '!=', '')
            ->selectRaw('l.pickup_voivodeship as p, l.dropoff_voivodeship as d, COUNT(*) as cnt')
            ->groupBy('l.pickup_voivodeship', 'l.dropoff_voivodeship')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get()
            ->toArray();

        if (count($rows) === 0) {
            return [];
        }

        $total = array_sum(array_column($rows, 'cnt')) ?: 1;

        return array_map(static fn ($r) => [
            'from' => (string) $r['p'],
            'to' => (string) $r['d'],
            'count' => (int) $r['cnt'],
            'share' => round(((int) $r['cnt']) / $total * 100, 1),
        ], $rows);
    }

    private static function deltaPct(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
