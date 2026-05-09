<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Photo gallery for a horse — distinct from documents (passport, contracts).
 * Photos are progress / training / personality shots that the owner sees
 * in the client portal. Stored on the local (private) disk; served via
 * authenticated controller endpoints, never directly by URL.
 */
class HorsePhoto extends TenantModel
{
    use SoftDeletes;

    protected $table = 'horse_photos';

    protected $fillable = [
        'horse_id',
        'file_path', 'original_name', 'mime', 'size_bytes',
        'caption', 'sort_order',
        'uploaded_by_role', 'uploaded_by_user_id', 'uploaded_by_client_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function sizeFormatted(): string
    {
        $kb = $this->size_bytes / 1024;
        if ($kb < 1024) {
            return number_format($kb, 0).' KB';
        }

        return number_format($kb / 1024, 1, ',', '').' MB';
    }
}
