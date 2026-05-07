<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use App\Services\Invoicing\InvoiceNumberGenerator;
use App\Services\TenantAuditLogger;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
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

        $invoice->forceFill([
            'number' => $number,
            'status' => InvoiceStatus::Issued->value,
            'issued_at' => $date->toDateString(),
            'sale_date' => $invoice->sale_date ?? $date->toDateString(),
        ])->save();

        $this->audit->record('invoice.issued', 'Invoice', (string) $invoice->id, [
            'number' => $number,
            'kind' => $invoice->kind->value,
            'total_cents' => $invoice->total_cents,
        ]);

        return $invoice->refresh();
    }
}
