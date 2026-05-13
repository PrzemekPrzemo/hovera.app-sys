<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sync_version' => (int) $this->sync_version,
            'client_id' => $this->client_id,
            'author_user_id' => $this->author_user_id ?? null,
            'direction' => $this->direction ?? 'inbound',
            'body' => $this->body,
            'read_at' => optional($this->read_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
