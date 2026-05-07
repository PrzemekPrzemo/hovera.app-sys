<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Services\TenantAuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tworzy fakturę korygującą z fakturą-źródłem.
 *
 * Domyślne zachowanie: pełna korekta na zero — kopiuje pozycje z
 * wartościami ujemnymi. Caller może następnie edytować pozycje
 * (np. korekta tylko jednej pozycji + nowa kwota).
 */
class CreateInvoiceCorrection
{
    public function __construct(
        private readonly TenantAuditLogger $audit,
    ) {}

    public function execute(Invoice $original): Invoice
    {
        if ($original->kind === InvoiceKind::FvKorekta) {
            throw ValidationException::withMessages([
                'kind' => 'Nie można korygować korekty — wystaw nową fakturę.',
            ]);
        }
        if ($original->status === InvoiceStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Korektę można wystawić tylko do faktury wystawionej.',
            ]);
        }

        $korekta = Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::FvKorekta->value,
            'status' => InvoiceStatus::Draft->value,
            'client_id' => $original->client_id,
            'corrects_invoice_id' => $original->id,
            'related_pass_id' => $original->related_pass_id,
            'related_payment_id' => $original->related_payment_id,
            'seller_name' => $original->seller_name,
            'seller_nip' => $original->seller_nip,
            'seller_address' => $original->seller_address,
            'seller_postal_code' => $original->seller_postal_code,
            'seller_city' => $original->seller_city,
            'seller_country' => $original->seller_country,
            'buyer_name' => $original->buyer_name,
            'buyer_nip' => $original->buyer_nip,
            'buyer_address' => $original->buyer_address,
            'buyer_postal_code' => $original->buyer_postal_code,
            'buyer_city' => $original->buyer_city,
            'buyer_country' => $original->buyer_country,
            'currency' => $original->currency,
            'sale_date' => $original->sale_date,
            'notes' => 'Korekta do '.$original->number,
            'subtotal_cents' => 0,
            'vat_cents' => 0,
            'total_cents' => 0,
        ]);

        // Klonuj pozycje z odwrotnym znakiem (full reversal). Caller
        // edytuje co potrzeba.
        foreach ($original->items as $item) {
            InvoiceItem::create([
                'id' => (string) Str::ulid(),
                'invoice_id' => $korekta->id,
                'position' => $item->position,
                'name' => $item->name,
                'description' => $item->description,
                'quantity' => -1 * (float) $item->quantity,
                'unit' => $item->unit,
                'vat_rate' => $item->vat_rate,
                'unit_price_cents' => $item->unit_price_cents,
                'net_cents' => -1 * (int) $item->net_cents,
                'vat_cents' => -1 * (int) $item->vat_cents,
                'total_cents' => -1 * (int) $item->total_cents,
            ]);
        }

        $korekta->load('items')->recomputeTotals()->save();

        $this->audit->record('invoice.correction_created', 'Invoice', (string) $korekta->id, [
            'corrects_invoice_id' => $original->id,
            'corrects_number' => $original->number,
        ]);

        return $korekta->refresh();
    }
}
