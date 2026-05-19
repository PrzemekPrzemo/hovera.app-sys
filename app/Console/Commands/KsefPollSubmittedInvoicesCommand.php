<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Transport\Ksef\KsefNotConfiguredException;
use App\Domain\Transport\Ksef\TransporterKsefService;
use App\Enums\TenantType;
use App\Enums\TransportKsefStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\TransportInvoice;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Poll submitted KSeF invoices i aktualizuj ich status do accepted /
 * rejected. Wywoływane przez scheduler co 30 minut w godzinach
 * 06:00–22:00 Warsaw (patrz routes/console.php).
 *
 * Algorytm:
 *   - dla każdego active transporter tenanta…
 *   - … bierzemy invoice'y w statusie `submitted`, starsze niż 5 minut
 *     (chcemy dać MF chwilę na asynchroniczny processing — bez tego
 *     polling byłby drugorzędnym DOS na MF API), a młodsze niż 7 dni
 *     (po tygodniu rezygnujemy; jeśli MF nie odpowiedział, to jakaś
 *     poważna awaria po ich stronie — manualnie obsługujemy)…
 *   - … per faktura wykonujemy `refreshStatus()` (handshake + GET status)…
 *   - … per tenant zakres limitowany do `--limit` (default 200) by jedna
 *     duża stajnia nie zjadała całego okna polluera.
 *
 * Bezpieczeństwo: każdy tenant wrapowany try/catch — jeden zepsuty DB
 * nie blokuje pozostałych. Logujemy summary do default channel.
 */
class KsefPollSubmittedInvoicesCommand extends Command
{
    protected $signature = 'transport:ksef:poll-submitted
        {--tenant= : Slug pojedynczego transportera (default: wszyscy active)}
        {--limit=200 : Maks liczba invoice\'ów per tenant na uruchomienie}
        {--min-age-minutes=5 : Pomiń wiersze młodsze niż X minut}
        {--max-age-days=7 : Pomiń wiersze starsze niż X dni}';

    protected $description = 'Sprawdza status KSeF dla submitted invoice\'ów i aktualizuje do accepted/rejected.';

    public function handle(TenantManager $tenants, TransporterKsefService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $minAgeMinutes = max(0, (int) $this->option('min-age-minutes'));
        $maxAgeDays = max(1, (int) $this->option('max-age-days'));

        $query = Tenant::query()
            ->where('type', TenantType::Transporter->value)
            ->whereIn('status', ['trialing', 'active']);
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        $tenantList = $query->get();
        if ($tenantList->isEmpty()) {
            $this->info('No active transporters.');

            return self::SUCCESS;
        }

        $totalsPolled = 0;
        $totalsAccepted = 0;
        $totalsRejected = 0;
        $totalsStillSubmitted = 0;
        $totalsErrored = 0;
        $tenantsSkipped = 0;

        foreach ($tenantList as $tenant) {
            try {
                $stats = $tenants->execute($tenant, fn () => $this->processTenant(
                    $service,
                    $limit,
                    $minAgeMinutes,
                    $maxAgeDays,
                ));

                $totalsPolled += $stats['polled'];
                $totalsAccepted += $stats['accepted'];
                $totalsRejected += $stats['rejected'];
                $totalsStillSubmitted += $stats['still_submitted'];
                $totalsErrored += $stats['errored'];

                if ($stats['polled'] > 0) {
                    $this->line(sprintf(
                        '  %s: polled=%d accepted=%d rejected=%d still=%d err=%d',
                        $tenant->slug,
                        $stats['polled'],
                        $stats['accepted'],
                        $stats['rejected'],
                        $stats['still_submitted'],
                        $stats['errored'],
                    ));
                }
            } catch (\Throwable $e) {
                $tenantsSkipped++;
                $this->error("× {$tenant->slug}: {$e->getMessage()}");
                report($e);
            }
        }

        $summary = sprintf(
            'KSeF poll: polled=%d accepted=%d rejected=%d still_submitted=%d errored=%d tenants_skipped=%d',
            $totalsPolled,
            $totalsAccepted,
            $totalsRejected,
            $totalsStillSubmitted,
            $totalsErrored,
            $tenantsSkipped,
        );

        $this->info($summary);
        Log::info('ksef.poll.summary', [
            'polled' => $totalsPolled,
            'accepted' => $totalsAccepted,
            'rejected' => $totalsRejected,
            'still_submitted' => $totalsStillSubmitted,
            'errored' => $totalsErrored,
            'tenants_skipped' => $tenantsSkipped,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{polled: int, accepted: int, rejected: int, still_submitted: int, errored: int}
     */
    private function processTenant(
        TransporterKsefService $service,
        int $limit,
        int $minAgeMinutes,
        int $maxAgeDays,
    ): array {
        // KsefNotConfiguredException dla tenanta, który nie ma jeszcze
        // gotowego KSeF (brak tokenu, etc.) — to NIE jest błąd, tylko
        // brak konfiguracji. Po prostu pomijamy.
        if (! $service->isEnabledForCurrentTransporter()) {
            return ['polled' => 0, 'accepted' => 0, 'rejected' => 0, 'still_submitted' => 0, 'errored' => 0];
        }

        $cutoffMin = now()->subMinutes($minAgeMinutes);
        $cutoffMax = now()->subDays($maxAgeDays);

        $polled = 0;
        $accepted = 0;
        $rejected = 0;
        $stillSubmitted = 0;
        $errored = 0;

        // Cursor-based: nie ładujemy całej kolekcji do pamięci jednocześnie.
        TransportInvoice::query()
            ->where('ksef_status', TransportKsefStatus::Submitted->value)
            ->whereNotNull('ksef_reference_number')
            ->where('ksef_submitted_at', '<=', $cutoffMin)
            ->where('ksef_submitted_at', '>=', $cutoffMax)
            ->orderBy('ksef_submitted_at')
            ->limit($limit)
            ->cursor()
            ->each(function (TransportInvoice $invoice) use (
                $service,
                &$polled,
                &$accepted,
                &$rejected,
                &$stillSubmitted,
                &$errored,
            ) {
                $polled++;
                try {
                    $result = $service->refreshStatus($invoice);
                } catch (KsefNotConfiguredException $e) {
                    // Konfiguracja zniknęła w trakcie cyklu — pomijamy resztę.
                    $errored++;

                    return false; // krótkie zwarcie pętli .each()
                } catch (\Throwable $e) {
                    $errored++;
                    Log::warning('ksef.poll.invoice_failed', [
                        'invoice_id' => $invoice->id,
                        'reference' => $invoice->ksef_reference_number,
                        'error' => $e->getMessage(),
                    ]);

                    return; // kontynuuj kolejny wiersz
                }

                match ($result->status) {
                    TransportKsefStatus::Accepted => $accepted++,
                    TransportKsefStatus::Rejected => $rejected++,
                    TransportKsefStatus::Submitted => $stillSubmitted++,
                    default => $errored++,
                };
            });

        return [
            'polled' => $polled,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'still_submitted' => $stillSubmitted,
            'errored' => $errored,
        ];
    }
}
