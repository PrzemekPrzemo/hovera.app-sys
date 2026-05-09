<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Models\Central\Tenant;
use App\Models\Tenant\Client;

/**
 * Builds the seller / buyer "frozen" address blocks that go into an
 * invoice on creation. Extracted from CreateInvoiceFromPass so the bulk
 * action and the per-pass action stay in sync if invoice header schema
 * changes.
 */
class InvoiceSnapshotPreparer
{
    /** @return array<string,?string> */
    public function seller(Tenant $tenant): array
    {
        $profile = (array) (data_get($tenant->settings, 'public_profile') ?? []);
        $invoicing = (array) (data_get($tenant->settings, 'invoicing') ?? []);

        return [
            'seller_name' => (string) ($invoicing['seller_name'] ?? $tenant->legal_name ?? $tenant->name),
            'seller_nip' => ((string) ($invoicing['seller_nip'] ?? $tenant->tax_id ?? '')) ?: null,
            'seller_address' => ((string) ($invoicing['seller_address'] ?? $profile['address'] ?? '')) ?: null,
            'seller_postal_code' => ((string) ($invoicing['seller_postal_code'] ?? $profile['postal_code'] ?? '')) ?: null,
            'seller_city' => ((string) ($invoicing['seller_city'] ?? $profile['city'] ?? '')) ?: null,
            'seller_country' => (string) ($tenant->country ?? 'PL'),
        ];
    }

    /** @return array<string,?string> */
    public function buyer(Client $client): array
    {
        return [
            'buyer_name' => (string) $client->name,
            'buyer_nip' => $client->tax_id ?: null,
            'buyer_address' => $client->street ?? null,
            'buyer_postal_code' => $client->postal_code ?? null,
            'buyer_city' => $client->city ?? null,
            'buyer_country' => (string) ($client->country ?? 'PL'),
        ];
    }
}
