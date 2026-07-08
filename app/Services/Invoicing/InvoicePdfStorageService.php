<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Tenant\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Persists + serves the PDF copy of a tenant `Invoice` on hovera.app's own
 * storage (local disk by default, see `config('invoicing.pdf.disk')`),
 * bounded by a retention window.
 *
 * Business decision (owner, 2026-07): the PDF stays hosted here through the
 * calendar year it was issued in, plus a 1-month grace period — an invoice
 * issued in year Y is hosted through the end of January of year Y+1. After
 * that, we stop serving/regenerating the file locally; every submitted
 * invoice already has a permanent record in KSeF, so the customer is
 * pointed there instead (`ksefRedirectPayload()`). Cleaning up the stale
 * file + columns once the cutoff has passed is the job of the scheduled
 * `invoices:prune-expired-pdfs` command, NOT this service.
 */
class InvoicePdfStorageService
{
    public function __construct(
        private readonly InvoicePdfGenerator $generator,
    ) {}

    /**
     * End of January (23:59:59, Europe/Warsaw) of `issued_at->year + 1`.
     *
     * Defensive: an invoice without `issued_at` (shouldn't happen for a
     * real, issued invoice) is treated as "not within retention" rather
     * than throwing — we return a cutoff already in the past.
     */
    public function retentionCutoff(Invoice $invoice): Carbon
    {
        if ($invoice->issued_at === null) {
            return Carbon::now('Europe/Warsaw')->subDay();
        }

        $graceMonths = (int) config('invoicing.pdf.retention_grace_months', 1);

        return Carbon::create($invoice->issued_at->year + 1, 1, 1, 0, 0, 0, 'Europe/Warsaw')
            ->addMonthsNoOverflow($graceMonths - 1)
            ->endOfMonth();
    }

    public function isWithinRetention(Invoice $invoice): bool
    {
        return Carbon::now('Europe/Warsaw')->lessThanOrEqualTo($this->retentionCutoff($invoice));
    }

    /**
     * Ensure the invoice's PDF is available on the configured disk. Returns
     * whether it is available afterwards.
     *
     * - Outside the retention window: returns false without generating
     *   anything (stale files past the cutoff are the prune command's job).
     * - Inside the window with a missing/absent file: (re)generates + persists.
     * - Inside the window with an already-stored, existing file: no-op, true.
     */
    public function ensureStored(Invoice $invoice): bool
    {
        if (! $this->isWithinRetention($invoice)) {
            return false;
        }

        $disk = (string) config('invoicing.pdf.disk', 'local');

        if ($invoice->pdf_path !== null
            && $invoice->pdf_disk !== null
            && Storage::disk($invoice->pdf_disk)->exists($invoice->pdf_path)
        ) {
            return true;
        }

        $bytes = $this->generator->generateForTenant($invoice);
        $path = "invoices/{$invoice->id}.pdf";

        Storage::disk($disk)->put($path, $bytes);

        $invoice->forceFill([
            'pdf_disk' => $disk,
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Payload pointing the customer at KSeF once we no longer host the PDF
     * ourselves. We deliberately do NOT construct a deep-link to the exact
     * invoice — the KSeF web portal's public verification/redownload query
     * format is not something we have verified (it may require NIP + amount
     * + date, and may have changed over time). Fabricating one risks
     * shipping a broken link. Instead we expose the portal's base URL plus
     * the raw `ksef_reference_number` so the customer (or a future,
     * verified deep-link) can complete the lookup.
     *
     * @return array{ksef_reference_number: ?string, ksef_environment: ?string, ksef_portal_url: string}
     */
    public function ksefRedirectPayload(Invoice $invoice): array
    {
        return [
            'ksef_reference_number' => $invoice->ksef_reference_number,
            'ksef_environment' => $invoice->ksef_environment,
            'ksef_portal_url' => config("invoicing.ksef.portal_url.{$invoice->ksef_environment}")
                ?? config('invoicing.ksef.portal_url.production'),
        ];
    }
}
