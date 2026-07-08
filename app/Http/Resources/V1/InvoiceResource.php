<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

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
            'pdf_url' => $this->pdf_path ? route('api.v1.invoices.pdf', $this->id) : null,
            'issued_at' => optional($this->issued_at ?? null)?->toIso8601String(),
            'due_at' => optional($this->due_at ?? null)?->toIso8601String(),
            'paid_at' => optional($this->paid_at ?? null)?->toIso8601String(),
            'client_id' => $this->client_id ?? null,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
