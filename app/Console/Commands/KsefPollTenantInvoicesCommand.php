<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TenantType;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\Ksef\TenantKsefSubmissionService;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Poll'uje submitted regular tenant Invoice'y w KSeF i aktualizuje
 * status do accepted/rejected/error. Mirror `KsefPollSubmittedInvoicesCommand`
 * (transport flow), ale dla regular Invoice z cert-based auth.
 *
 * Algorytm:
 *   - dla każdego active stable / horse_owner / vet tenanta (tylko ci
 *     wystawiają regular FV — transporter ma osobną pulę)…
 *   - … bierzemy invoice'y w statusie 'submitted', starsze niż 5 minut
 *     (chcemy dać MF chwilę na asynchroniczny processing — bez tego
 *     polling byłby drugorzędnym DOS na MF API), a młodsze niż 7 dni…
 *   - … per faktura wykonujemy `TenantKsefSubmissionService::refreshStatus()`…
 *   - … per tenant zakres limitowany do `--limit` (default 200).
 *
 * Bezpieczeństwo: każdy tenant wrapowany try/catch — jeden zepsuty DB
 * nie blokuje pozostałych. Logujemy summary do default channel.
 */
class KsefPollTenantInvoicesCommand extends Command
{
    protected $signature = 'ksef:poll-tenant-invoices {--tenant=} {--limit=200} {--min-age-minutes=5} {--max-age-days=7}';

    protected $description = 'Sprawdza status KSeF dla submitted regular invoice tenant\'ów i aktualizuje do accepted/rejected.';

    public function handle(TenantManager $tenants, TenantKsefSubmissionService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $minAgeMinutes = max(0, (int) $this->option('min-age-minutes'));
        $maxAgeDays = max(1, (int) $this->option('max-age-days'));

        // Tylko ci tenanci wystawiają regular FV w KSeF. Transporterzy
        // mają osobną pulę (transport_invoices + token-based auth).
        $issuingTypes = [
            TenantType::Stable->value,
            TenantType::HorseOwner->value,
        ];

        $query = Tenant::query()
            ->whereIn('type', $issuingTypes)
            ->whereIn('status', ['trialing', 'active']);
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        $tenantList = $query->get();
        if ($tenantList->isEmpty()) {
            $this->info('No active issuing tenants.');

            return self::SUCCESS;
        }

        $totals = [
            'polled' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'still_submitted' => 0,
            'errored' => 0,
            'tenants_skipped' => 0,
        ];

        foreach ($tenantList as $tenant) {
            try {
                $stats = $tenants->execute($tenant, fn () => $this->processTenant(
                    $service,
                    $limit,
                    $minAgeMinutes,
                    $maxAgeDays,
                ));

                $totals['polled'] += $stats['polled'];
                $totals['accepted'] += $stats['accepted'];
                $totals['rejected'] += $stats['rejected'];
                $totals['still_submitted'] += $stats['still_submitted'];
                $totals['errored'] += $stats['errored'];

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
            } catch (Throwable $e) {
                $totals['tenants_skipped']++;
                $this->error("× {$tenant->slug}: {$e->getMessage()}");
                report($e);
            }
        }

        $summary = sprintf(
            'KSeF tenant poll: polled=%d accepted=%d rejected=%d still_submitted=%d errored=%d tenants_skipped=%d',
            $totals['polled'],
            $totals['accepted'],
            $totals['rejected'],
            $totals['still_submitted'],
            $totals['errored'],
            $totals['tenants_skipped'],
        );

        $this->info($summary);
        Log::info('ksef.tenant_poll.summary', $totals);

        return self::SUCCESS;
    }

    /**
     * @return array{polled: int, accepted: int, rejected: int, still_submitted: int, errored: int}
     */
    private function processTenant(
        TenantKsefSubmissionService $service,
        int $limit,
        int $minAgeMinutes,
        int $maxAgeDays,
    ): array {
        $cutoffMin = now()->subMinutes($minAgeMinutes);
        $cutoffMax = now()->subDays($maxAgeDays);

        $stats = [
            'polled' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'still_submitted' => 0,
            'errored' => 0,
        ];

        // Cursor — nie ładujemy całej kolekcji do pamięci jednocześnie.
        Invoice::query()
            ->where('ksef_status', TenantKsefSubmissionService::STATUS_SUBMITTED)
            ->whereNotNull('ksef_reference_number')
            ->where('ksef_submitted_at', '<=', $cutoffMin)
            ->where('ksef_submitted_at', '>=', $cutoffMax)
            ->orderBy('ksef_submitted_at')
            ->limit($limit)
            ->cursor()
            ->each(function (Invoice $invoice) use ($service, &$stats) {
                $stats['polled']++;
                try {
                    $result = $service->refreshStatus($invoice);
                } catch (Throwable $e) {
                    $stats['errored']++;
                    Log::warning('ksef.tenant_poll.invoice_failed', [
                        'invoice_id' => $invoice->id,
                        'reference' => $invoice->ksef_reference_number,
                        'error' => $e->getMessage(),
                    ]);

                    return;
                }

                match ($result->status) {
                    TenantKsefSubmissionService::STATUS_ACCEPTED => $stats['accepted']++,
                    TenantKsefSubmissionService::STATUS_REJECTED => $stats['rejected']++,
                    TenantKsefSubmissionService::STATUS_SUBMITTED => $stats['still_submitted']++,
                    default => $stats['errored']++,
                };
            });

        return $stats;
    }
}
