<?php

declare(strict_types=1);

namespace App\Domain\Transport\Invoices;

use App\Domain\Transport\Currency\NbpExchangeRateService;
use App\Enums\TransportInvoiceKind;
use App\Enums\TransportInvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Quote;
use App\Models\Tenant\TransportInvoice;
use App\Models\Tenant\TransportInvoiceItem;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Tworzy fakturę transportową ze snapshotu zaakceptowanej Quote.
 * Patrz docs/TRANSPORT.md §9 faza 3 (krok C).
 *
 * Flow:
 *   1. Walidacja: quote.status=Accepted, brak istniejącej FV dla quote
 *   2. Numer z TransportInvoiceNumberGenerator
 *   3. Snapshot sprzedawcy z Tenant.legal_name/tax_id/branding
 *   4. Snapshot nabywcy z Quote.customer_*
 *   5. 1-2 line items: distance×rate + opcjonalnie fuel surcharge
 *   6. Wszystko w transakcji per-tenant DB
 *
 * Zwraca utworzony TransportInvoice w status=Issued.
 */
class IssueTransportInvoiceFromQuote
{
    public function __construct(
        private readonly TransportInvoiceNumberGenerator $numbers,
        private readonly TenantManager $tenants,
        private readonly NbpExchangeRateService $nbp,
    ) {}

    /**
     * @throws \DomainException gdy quote nie jest accepted lub FV już istnieje
     */
    public function execute(
        Quote $quote,
        int $paymentTermsDays = 14,
        ?Carbon $issueDate = null,
        TransportInvoiceKind $kind = TransportInvoiceKind::Fv,
    ): TransportInvoice {
        if (! in_array($quote->status?->value, ['accepted'], true)) {
            throw new \DomainException('Faktura może być wystawiona wyłącznie dla zaakceptowanej oferty.');
        }

        $existing = TransportInvoice::query()->where('quote_id', $quote->id)->first();
        if ($existing) {
            throw new \DomainException('Dla tej oferty już istnieje faktura: '.$existing->number);
        }

        $issueDate ??= Carbon::now();
        $tenant = $this->tenants->tenantOrFail();

        return DB::connection('tenant')->transaction(function () use ($quote, $kind, $issueDate, $paymentTermsDays, $tenant) {
            $invoice = TransportInvoice::create($this->buildInvoiceAttrs($quote, $kind, $issueDate, $paymentTermsDays, $tenant));

            $this->buildLineItems($invoice, $quote);

            $this->refreshTotalsFromItems($invoice);

            return $invoice->fresh('items');
        });
    }

    private function buildInvoiceAttrs(
        Quote $quote,
        TransportInvoiceKind $kind,
        Carbon $issueDate,
        int $paymentTermsDays,
        Tenant $tenant,
    ): array {
        $number = $this->numbers->next($kind, $issueDate);
        $branding = (array) ($tenant->branding ?? []);
        $exchange = $this->resolveExchangeRate((string) $quote->currency, $issueDate);

        return [
            'id' => (string) Str::ulid(),
            'number' => $number,
            'kind' => $kind,
            'status' => TransportInvoiceStatus::Issued,

            'quote_id' => $quote->id,
            'response_id' => $quote->response_id,

            // Snapshot sprzedawcy z tenant'a + branding (per-tenant w
            // central tenants.branding JSON). IBAN/bank wymagane do
            // wystawienia FV w PL — gdy brakuje, dopiszemy z notką.
            'seller_name' => (string) ($tenant->legal_name ?: $tenant->name),
            'seller_nip' => $tenant->tax_id,
            'seller_address' => (string) ($branding['address'] ?? ''),
            'seller_postal_code' => (string) ($branding['postal_code'] ?? ''),
            'seller_city' => (string) ($branding['city'] ?? ''),
            'seller_country' => (string) ($tenant->country ?: 'PL'),
            'seller_iban' => (string) ($branding['iban'] ?? ''),
            'seller_bank_name' => (string) ($branding['bank_name'] ?? ''),

            // Snapshot nabywcy z Quote. Buyer type wnioskujemy z obecności
            // NIP-u: klient na public quote landing wybrał "Firma" → NIP set,
            // wybrał "Osoba prywatna" → NIP brak → buyer_type=individual,
            // FV idzie tylko z imieniem (KSeF FA(3) tolerated dla osób
            // fizycznych nieprowadzących działalności).
            'buyer_name' => $quote->customer_company ?: $quote->customer_name,
            'buyer_nip' => $quote->customer_tax_id,
            'buyer_address' => $quote->customer_address,
            'buyer_postal_code' => null,
            'buyer_city' => null,
            'buyer_country' => 'PL',
            'buyer_email' => $quote->customer_email,
            'buyer_type' => $quote->customer_tax_id ? 'company' : 'individual',

            // Snapshot trasy
            'pickup_address' => $quote->pickup_address,
            'dropoff_address' => $quote->dropoff_address,
            'service_date' => $quote->preferred_date,
            'distance_km' => $quote->distance_km,
            'vehicle_id' => $quote->vehicle_id,
            'driver_id' => $quote->driver_id,

            // Daty
            'issued_at' => $issueDate->toDateString(),
            'sale_date' => $issueDate->toDateString(),
            'due_at' => $issueDate->copy()->addDays($paymentTermsDays)->toDateString(),

            // Currency — z Quote. Snapshot kursu NBP (Art. 31a ust. 1
            // ustawy o VAT) tylko dla walut obcych. Soft-fail: gdy NBP API
            // padło, kurs zostaje null + log warning — nie blokujemy
            // wystawienia FV, transporter może uzupełnić ręcznie.
            'currency' => $quote->currency,
            'exchange_rate' => $exchange['rate'],
            'exchange_rate_date' => $exchange['date'],
            'exchange_rate_source' => $exchange['source'],
            'subtotal_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
        ];
    }

    /**
     * @return array{rate: ?float, date: ?string, source: ?string}
     */
    private function resolveExchangeRate(string $currency, Carbon $issueDate): array
    {
        $snapshot = $this->nbp->rateForInvoiceDate($currency, $issueDate);
        if ($currency !== 'PLN' && $snapshot['rate'] === null) {
            Log::warning('TransportInvoice issued without NBP rate snapshot (API offline)', [
                'currency' => $currency,
                'issued_at' => $issueDate->toDateString(),
            ]);
        }

        return $snapshot;
    }

    private function buildLineItems(TransportInvoice $invoice, Quote $quote): void
    {
        $vatRate = (string) ((int) round((float) $quote->vat_rate));

        // Item 1: usługa transportowa = base_cost (distance × rate) + minimum_adjustment
        // (jedna pozycja, bo klient widzi to jako 1 usługę; szczegóły rozbicia są w
        // ofercie, FV agreguje do business-friendly stawki)
        $servicePriceNet = (float) $quote->base_cost + (float) $quote->minimum_adjustment;
        $this->createItem($invoice, [
            'position' => 1,
            'name' => __('transport/invoice.item.transport_service'),
            'description' => $this->buildServiceDescription($quote),
            'quantity' => 1,
            'unit' => 'usł.',
            'vat_rate' => $vatRate,
            'unit_price_pln' => $servicePriceNet,
        ]);

        // Item 2: dopłata paliwowa (jeśli > 0)
        if ((float) $quote->fuel_surcharge > 0) {
            $this->createItem($invoice, [
                'position' => 2,
                'name' => __('transport/invoice.item.fuel_surcharge'),
                'description' => null,
                'quantity' => 1,
                'unit' => 'szt.',
                'vat_rate' => $vatRate,
                'unit_price_pln' => (float) $quote->fuel_surcharge,
            ]);
        }
    }

    /**
     * @param  array{position:int, name:string, description:?string, quantity:float|int, unit:string, vat_rate:string, unit_price_pln:float}  $attrs
     */
    private function createItem(TransportInvoice $invoice, array $attrs): TransportInvoiceItem
    {
        $unitPriceCents = (int) round($attrs['unit_price_pln'] * 100);
        $netCents = (int) round($unitPriceCents * $attrs['quantity']);
        $vatCents = (int) round($netCents * ((float) $attrs['vat_rate']) / 100);
        $totalCents = $netCents + $vatCents;

        return TransportInvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'position' => $attrs['position'],
            'name' => $attrs['name'],
            'description' => $attrs['description'],
            'quantity' => $attrs['quantity'],
            'unit' => $attrs['unit'],
            'vat_rate' => $attrs['vat_rate'],
            'unit_price_cents' => $unitPriceCents,
            'net_cents' => $netCents,
            'vat_cents' => $vatCents,
            'total_cents' => $totalCents,
        ]);
    }

    private function buildServiceDescription(Quote $quote): string
    {
        return __('transport/invoice.item.transport_service_description', [
            'from' => $quote->pickup_address,
            'to' => $quote->dropoff_address,
            'date' => $quote->preferred_date->format('Y-m-d'),
            'km' => number_format((float) $quote->distance_km, 2, ',', ' '),
        ]);
    }

    private function refreshTotalsFromItems(TransportInvoice $invoice): void
    {
        $subtotal = $invoice->items()->sum('net_cents');
        $vat = $invoice->items()->sum('vat_cents');
        $total = $invoice->items()->sum('total_cents');

        $invoice->forceFill([
            'subtotal_cents' => (int) $subtotal,
            'vat_cents' => (int) $vat,
            'total_cents' => (int) $total,
        ])->save();
    }
}
