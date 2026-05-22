<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Domain\Transport\Currency\NbpExchangeRateService;
use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use App\Services\Invoicing\InvoiceNumberGenerator;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Move invoice from Draft → Issued. Generates a number, sets issued_at,
 * recomputes totals, writes audit. Idempotent — re-issuing already-issued
 * faktury jest no-op.
 *
 * Re-issue (np. korekta) tworzy NOWĄ fakturę przez CreateCorrection,
 * nigdy nie modyfikujemy issued_at / number na istniejącej.
 */
class IssueInvoice
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly TenantManager $tenants,
        private readonly TenantAuditLogger $audit,
        private readonly NbpExchangeRateService $nbp,
    ) {}

    public function execute(Invoice $invoice, ?Carbon $issueDate = null): Invoice
    {
        if ($invoice->status === InvoiceStatus::Issued) {
            return $invoice; // already issued, no-op
        }

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Można wystawić tylko fakturę w wersji roboczej.',
            ]);
        }

        if ($invoice->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Faktura musi mieć przynajmniej jedną pozycję.',
            ]);
        }

        if ($invoice->buyer_name === '' || $invoice->buyer_name === null) {
            throw ValidationException::withMessages([
                'buyer_name' => 'Brakuje danych nabywcy.',
            ]);
        }

        $tenant = $this->tenants->tenantOrFail();
        $date = $issueDate ?? Carbon::now();

        $number = $this->numbers->next($tenant, $invoice->kind, $date);

        // Re-pull items + recompute żeby suma była konsystentna w momencie
        // wystawienia (chroni przed sytuacją gdy form zapisał stare totals).
        $invoice->load('items');
        foreach ($invoice->items as $item) {
            $item->recomputeAmounts()->save();
        }
        $invoice->load('items')->recomputeTotals();

        $fillData = [
            'number' => $number,
            'status' => InvoiceStatus::Issued->value,
            'issued_at' => $date->toDateString(),
            'sale_date' => $invoice->sale_date ?? $date->toDateString(),
        ];

        // VAT-correct snapshot kursu NBP dla FV w walucie obcej (Art. 31a
        // ust. 1 ustawy o VAT). Tylko gdy kurs jeszcze nie zapisany —
        // pozwalamy override z UI (np. master admin chce zachować kurs
        // z innego dnia, choć w 99% przypadków snapshot tu jest poprawny).
        if ($invoice->currency !== 'PLN' && $invoice->exchange_rate === null) {
            $snapshot = $this->nbp->rateForInvoiceDate((string) $invoice->currency, $date);
            if ($snapshot['rate'] !== null) {
                $fillData['exchange_rate'] = $snapshot['rate'];
                $fillData['exchange_rate_date'] = $snapshot['date'];
                $fillData['exchange_rate_source'] = $snapshot['source'];
            } else {
                // Bez kursu nie blokujemy wystawienia (offline-friendly), ale
                // logujemy żeby user mógł uzupełnić ręcznie i ponownie issue.
                Log::warning('Invoice issued without NBP rate snapshot (API offline)', [
                    'invoice_id' => $invoice->id,
                    'currency' => $invoice->currency,
                    'issued_at' => $date->toDateString(),
                ]);
            }
        }

        $invoice->forceFill($fillData)->save();

        $this->audit->record('invoice.issued', 'Invoice', (string) $invoice->id, [
            'number' => $number,
            'kind' => $invoice->kind->value,
            'total_cents' => $invoice->total_cents,
        ]);

        return $invoice->refresh();
    }
}
