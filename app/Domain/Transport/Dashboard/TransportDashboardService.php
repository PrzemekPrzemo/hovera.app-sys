<?php

declare(strict_types=1);

namespace App\Domain\Transport\Dashboard;

use App\Enums\QuoteStatus;
use App\Enums\TransportInvoiceStatus;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportInvoice;
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
     * @return \Illuminate\Database\Eloquent\Collection<int, Quote>
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
     * @return array{today: \Illuminate\Database\Eloquent\Collection<int, Quote>, tomorrow: \Illuminate\Database\Eloquent\Collection<int, Quote>}
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
}
