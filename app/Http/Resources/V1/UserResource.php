<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'locale' => $this->locale ?? 'pl',
            'timezone' => $this->timezone ?? 'Europe/Warsaw',
        ];
    }
}
