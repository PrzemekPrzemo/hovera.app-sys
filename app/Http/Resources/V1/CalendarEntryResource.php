<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sync_version' => (int) $this->sync_version,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'starts_at' => optional($this->starts_at)->toIso8601String(),
            'ends_at' => optional($this->ends_at)->toIso8601String(),
            'horse_id' => $this->horse_id,
            'instructor_id' => $this->instructor_id,
            'arena_id' => $this->arena_id,
            'client_id' => $this->client_id,
            'recurrence_id' => $this->recurrence_id,
            'recurrence_occurrence' => $this->recurrence_occurrence,
            'title' => $this->title,
            'notes' => $this->notes,
            'price_cents' => (int) ($this->price_cents ?? 0),
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
