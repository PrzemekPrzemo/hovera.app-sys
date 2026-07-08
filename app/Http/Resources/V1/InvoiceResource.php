<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Services\Invoicing\InvoicePdfStorageService;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sync_version' => (int) $this->sync_version,
            'number' => $this->number ?? null,
            'amount_cents' => (int) ($this->total_cents ?? 0),
            'currency' => $this->currency ?? 'PLN',
            'ksef_status' => $this->ksef_status ?? null,
            'ksef_reference_number' => $this->ksef_reference_number ?? null,
            'ksef_environment' => $this->ksef_environment ?? null,
            // The PDF is generated lazily on first hit of the /pdf endpoint
            // (InvoicePdfStorageService::ensureStored), so `pdf_path` being
            // unset locally does NOT mean the PDF is unavailable — it means
            // it hasn't been fetched yet. Gate the link on the retention
            // window itself, not on whether we've already cached a copy,
            // otherwise the link would never appear for an invoice nobody
            // has opened before.
            'pdf_url' => app(InvoicePdfStorageService::class)->isWithinRetention($this->resource)
                ? route('api.v1.invoices.pdf', $this->id)
                : null,
            'issued_at' => optional($this->issued_at ?? null)?->toIso8601String(),
            'due_at' => optional($this->due_at ?? null)?->toIso8601String(),
            'paid_at' => optional($this->paid_at ?? null)?->toIso8601String(),
            'client_id' => $this->client_id ?? null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
