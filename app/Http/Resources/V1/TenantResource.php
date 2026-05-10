<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'country' => $this->country ?? 'PL',
            'brand_color' => $this->brand_color ?? null,
        ];
    }
}
