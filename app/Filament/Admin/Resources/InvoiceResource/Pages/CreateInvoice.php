<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InvoiceResource\Pages;

use App\Filament\Admin\Resources\InvoiceResource;
use App\Models\Central\Tenant;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Snapshot tenanta — immutable na fakturze.
        if (! empty($data['tenant_id'])) {
            $tenant = Tenant::find($data['tenant_id']);
            if ($tenant !== null) {
                $data['payload_snapshot'] = [
                    'tenant_slug' => $tenant->slug,
                    'tenant_name' => $tenant->name,
                    'legal_name' => $tenant->legal_name,
                    'tax_id' => $tenant->tax_id,
                    'currency' => $tenant->currency,
                ];
            }
        }

        $data['status'] = $data['status'] ?? 'open';

        return $data;
    }
}
