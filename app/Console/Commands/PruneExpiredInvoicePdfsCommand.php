<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Services\Invoicing\InvoicePdfStorageService;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cleanup dla lokalnie hostowanych PDF-ów faktur, których retencja minęła.
 *
 * Business decision (owner, 2026-07): hovera.app hostuje PDF faktury przez
 * rok wystawienia + 1 miesiąc grace (patrz `InvoicePdfStorageService`). Ten
 * command chodzi po wszystkich tenantach i dla każdej `Invoice` z
 * `pdf_path IS NOT NULL`, której cutoff już minął, usuwa plik z jej
 * `pdf_disk` i czyści `pdf_disk`/`pdf_path`/`pdf_generated_at`. Po tym
 * momencie klient jest kierowany do KSeF przez `InvoiceController::pdf()`.
 *
 * Mirror `KsefPollTenantInvoicesCommand` — per-tenant try/catch (jeden
 * zepsuty tenant nie blokuje reszty), cursor-based query żeby nie ładować
 * całej kolekcji do pamięci.
 */
class PruneExpiredInvoicePdfsCommand extends Command
{
    protected $signature = 'invoices:prune-expired-pdfs {--tenant=}';

    protected $description = 'Usuwa lokalnie hostowane PDF faktur, których retencja (rok wystawienia + 1 miesiąc) minęła.';

    public function handle(TenantManager $tenants, InvoicePdfStorageService $storage): int
    {
        $query = Tenant::query()->whereIn('status', ['trialing', 'active', 'past_due', 'suspended']);
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        $tenantList = $query->get();
        if ($tenantList->isEmpty()) {
            $this->info('No tenants to process.');

            return self::SUCCESS;
        }

        $totals = [
            'pruned' => 0,
            'errored' => 0,
            'tenants_skipped' => 0,
        ];

        foreach ($tenantList as $tenant) {
            try {
                $stats = $tenants->execute($tenant, fn () => $this->processTenant($storage));

                $totals['pruned'] += $stats['pruned'];
                $totals['errored'] += $stats['errored'];

                if ($stats['pruned'] > 0 || $stats['errored'] > 0) {
                    $this->line(sprintf(
                        '  %s: pruned=%d errored=%d',
                        $tenant->slug,
                        $stats['pruned'],
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
            'Invoice PDF prune: pruned=%d errored=%d tenants_skipped=%d',
            $totals['pruned'],
            $totals['errored'],
            $totals['tenants_skipped'],
        );

        $this->info($summary);
        Log::info('invoices.pdf_prune.summary', $totals);

        return self::SUCCESS;
    }

    /**
     * @return array{pruned: int, errored: int}
     */
    private function processTenant(InvoicePdfStorageService $storage): array
    {
        $stats = ['pruned' => 0, 'errored' => 0];

        Invoice::query()
            ->whereNotNull('pdf_path')
            ->cursor()
            ->each(function (Invoice $invoice) use ($storage, &$stats) {
                if ($storage->isWithinRetention($invoice)) {
                    return;
                }

                try {
                    if ($invoice->pdf_disk !== null) {
                        Storage::disk($invoice->pdf_disk)->delete($invoice->pdf_path);
                    }

                    $invoice->forceFill([
                        'pdf_disk' => null,
                        'pdf_path' => null,
                        'pdf_generated_at' => null,
                    ])->save();

                    $stats['pruned']++;
                } catch (Throwable $e) {
                    $stats['errored']++;
                    Log::warning('invoices.pdf_prune.invoice_failed', [
                        'invoice_id' => $invoice->id,
                        'pdf_disk' => $invoice->pdf_disk,
                        'pdf_path' => $invoice->pdf_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            });

        return $stats;
    }
}
