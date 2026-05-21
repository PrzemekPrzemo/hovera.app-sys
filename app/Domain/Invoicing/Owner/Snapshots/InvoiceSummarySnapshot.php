<?php

declare(strict_types=1);

namespace App\Domain\Invoicing\Owner\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Lekki snapshot faktury dla list view'u (Owner panel + API). Bez itemów
 * — te ładujemy lazy w InvoiceDetailSnapshot przy "Pokaż szczegóły".
 *
 * `stableTenantId` + `id` razem tworzą composite identifier — invoice
 * ID nie jest globalnie unikalny, musi być scoped do tenant'a. URL
 * convention: `/owner/invoices/{stableTenantId}/{invoiceId}`.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.3".
 */
final readonly class InvoiceSummarySnapshot
{
    public function __construct(
        public string $id,                   // ULID invoice'a w stable DB
        public string $stableTenantId,        // ULID tenant'a stajni (route param)
        public string $stableTenantName,      // Wyświetlana nazwa stajni
        public ?string $number,               // FV/2026/05/0042 lub null dla draft
        public string $kind,                  // 'fv' | 'fv_proforma' | 'fv_korekta'
        public string $status,                // 'draft' | 'issued' | 'paid' | ...
        public ?Carbon $issuedAt,
        public ?Carbon $dueAt,
        public ?Carbon $paidAt,
        public string $currency,
        public int $totalCents,
        public ?string $billingPeriod,        // YYYY-MM jeśli auto-billing
        public ?string $centralHorseId,       // jeśli faktura per koń (z metadata)
        public ?string $horseName,            // snapshot z metadata
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stable_tenant_id' => $this->stableTenantId,
            'stable_tenant_name' => $this->stableTenantName,
            'number' => $this->number,
            'kind' => $this->kind,
            'status' => $this->status,
            'issued_at' => $this->issuedAt?->toDateString(),
            'due_at' => $this->dueAt?->toDateString(),
            'paid_at' => $this->paidAt?->toIso8601String(),
            'currency' => $this->currency,
            'total_cents' => $this->totalCents,
            'billing_period' => $this->billingPeriod,
            'central_horse_id' => $this->centralHorseId,
            'horse_name' => $this->horseName,
        ];
    }
}
