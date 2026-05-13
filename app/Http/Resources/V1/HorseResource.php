<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class HorseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sync_version' => (int) $this->sync_version,
            'name' => $this->name,
            'microchip' => $this->microchip,
            'passport_number' => $this->passport_number,
            'ueln' => $this->ueln,
            'breed' => $this->breed,
            'sex' => $this->sex,
            'color' => $this->color,
            'birth_date' => optional($this->birth_date)->toDateString(),
            'owner_client_id' => $this->owner_client_id,
            'box_id' => $this->box_id ?? null,
            'cover_image_path' => $this->cover_image_path,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
