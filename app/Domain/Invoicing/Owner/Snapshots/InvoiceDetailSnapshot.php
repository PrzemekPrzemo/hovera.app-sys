<?php

declare(strict_types=1);

namespace App\Domain\Invoicing\Owner\Snapshots;

use Illuminate\Support\Carbon;

/**
 * Pełen snapshot faktury — wszystkie pola + items embedded. Używane
 * przez Owner panel InvoiceShow page i `GET /api/owner/invoices/.../{id}`.
 *
 * Patrz docs/OWNER-STABLE-ROADMAP.md "Faza 3 PR 3.3".
 */
final readonly class InvoiceDetailSnapshot
{
    /**
     * @param  list<InvoiceItemSnapshot>  $items
     */
    public function __construct(
        public string $id,
        public string $stableTenantId,
        public string $stableTenantName,
        public ?string $number,
        public string $kind,
        public string $status,
        public ?Carbon $issuedAt,
        public ?Carbon $saleDate,
        public ?Carbon $dueAt,
        public ?Carbon $paidAt,
        // Sprzedawca (snapshot z momentu wystawienia)
        public string $sellerName,
        public ?string $sellerNip,
        public ?string $sellerAddress,
        public ?string $sellerCity,
        public ?string $sellerPostalCode,
        // Nabywca (snapshot)
        public string $buyerName,
        public ?string $buyerNip,
        public ?string $buyerAddress,
        public ?string $buyerCity,
        public ?string $buyerPostalCode,
        public string $currency,
        public int $subtotalCents,
        public int $vatCents,
        public int $totalCents,
        public array $items,
        public ?string $notes,
        public ?string $billingPeriod,
        public ?string $centralHorseId,
        public ?string $horseName,
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
            'sale_date' => $this->saleDate?->toDateString(),
            'due_at' => $this->dueAt?->toDateString(),
            'paid_at' => $this->paidAt?->toIso8601String(),
            'seller_name' => $this->sellerName,
            'seller_nip' => $this->sellerNip,
            'seller_address' => $this->sellerAddress,
            'seller_city' => $this->sellerCity,
            'seller_postal_code' => $this->sellerPostalCode,
            'buyer_name' => $this->buyerName,
            'buyer_nip' => $this->buyerNip,
            'buyer_address' => $this->buyerAddress,
            'buyer_city' => $this->buyerCity,
            'buyer_postal_code' => $this->buyerPostalCode,
            'currency' => $this->currency,
            'subtotal_cents' => $this->subtotalCents,
            'vat_cents' => $this->vatCents,
            'total_cents' => $this->totalCents,
            'items' => array_map(fn (InvoiceItemSnapshot $i) => $i->toArray(), $this->items),
            'notes' => $this->notes,
            'billing_period' => $this->billingPeriod,
            'central_horse_id' => $this->centralHorseId,
            'horse_name' => $this->horseName,
        ];
    }
}
