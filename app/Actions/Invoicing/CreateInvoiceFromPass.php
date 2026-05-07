<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Central\Tenant;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\InvoiceItem;
use App\Models\Tenant\Pass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Auto-generate FV gdy stajnia sprzeda klientowi karnet.
 *
 * Tworzy Draft (kind=fv) z 1 pozycją "Karnet 10x — ..." po cenie
 * zapisanej w Pass.price_cents. Domyślny VAT 23%; owner może zmienić
 * w PassResource przed sprzedażą lub w samej fakturze przed
 * wystawieniem (Issue).
 *
 * Faktura JEST od razu wystawiana (Draft → Issued) chyba że caller
 * przekaże $autoIssue=false. To match'uje typowy flow stajni — przy
 * sprzedaży karnetu wystawiamy fakturę natychmiast.
 */
class CreateInvoiceFromPass
{
    public function __construct(
        private readonly IssueInvoice $issuer,
    ) {}

    public function execute(
        Tenant $tenant,
        Pass $pass,
        bool $autoIssue = true,
        ?int $vatRate = null,
        ?int $paymentDueDays = null,
    ): ?Invoice {
        if (! $pass->client_id || ! $pass->price_cents) {
            return null;
        }

        $client = $pass->client;
        if (! $client) {
            return null;
        }

        $sellerSnapshot = $this->snapshotSeller($tenant);
        $buyerSnapshot = $this->snapshotBuyer($client);

        $vat = $vatRate ?? 23;
        $netRaw = (int) round($pass->price_cents / (1 + $vat / 100));
        $vatCents = (int) $pass->price_cents - $netRaw;
        $issueDate = Carbon::now();
        $dueDate = $paymentDueDays !== null
            ? $issueDate->copy()->addDays($paymentDueDays)
            : $issueDate->copy()->addDays(7);

        $invoice = Invoice::create([
            'id' => (string) Str::ulid(),
            'kind' => InvoiceKind::Fv->value,
            'status' => InvoiceStatus::Draft->value,
            'client_id' => $client->id,
            'related_pass_id' => $pass->id,
            ...$sellerSnapshot,
            ...$buyerSnapshot,
            'currency' => 'PLN',
            'sale_date' => $issueDate->toDateString(),
            'due_at' => $dueDate->toDateString(),
            'subtotal_cents' => $netRaw,
            'vat_cents' => $vatCents,
            'total_cents' => $netRaw + $vatCents,
        ]);

        InvoiceItem::create([
            'id' => (string) Str::ulid(),
            'invoice_id' => $invoice->id,
            'position' => 1,
            'name' => "Karnet: {$pass->name}",
            'description' => "Karnet {$pass->total_uses}x".(
                $pass->valid_until ? ' · ważny do '.$pass->valid_until->format('Y-m-d') : ''
            ),
            'quantity' => 1,
            'unit' => 'szt.',
            'vat_rate' => (string) $vat,
            'unit_price_cents' => $netRaw,
            'net_cents' => $netRaw,
            'vat_cents' => $vatCents,
            'total_cents' => $netRaw + $vatCents,
        ]);

        if ($autoIssue) {
            return $this->issuer->execute($invoice, $issueDate);
        }

        return $invoice->refresh();
    }

    /** @return array<string,?string> */
    private function snapshotSeller(Tenant $tenant): array
    {
        $profile = (array) (data_get($tenant->settings, 'public_profile') ?? []);
        $invoicing = (array) (data_get($tenant->settings, 'invoicing') ?? []);

        return [
            'seller_name' => (string) ($invoicing['seller_name'] ?? $tenant->legal_name ?? $tenant->name),
            'seller_nip' => (string) ($invoicing['seller_nip'] ?? $tenant->tax_id ?? '') ?: null,
            'seller_address' => (string) ($invoicing['seller_address'] ?? $profile['address'] ?? '') ?: null,
            'seller_postal_code' => (string) ($invoicing['seller_postal_code'] ?? $profile['postal_code'] ?? '') ?: null,
            'seller_city' => (string) ($invoicing['seller_city'] ?? $profile['city'] ?? '') ?: null,
            'seller_country' => (string) ($tenant->country ?? 'PL'),
        ];
    }

    /** @return array<string,?string> */
    private function snapshotBuyer($client): array
    {
        return [
            'buyer_name' => (string) $client->name,
            'buyer_nip' => $client->tax_id ?: null,
            'buyer_address' => $client->street ?: null,
            'buyer_postal_code' => $client->postal_code ?: null,
            'buyer_city' => $client->city ?: null,
            'buyer_country' => (string) ($client->country ?? 'PL'),
        ];
    }
}
