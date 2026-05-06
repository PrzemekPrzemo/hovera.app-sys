<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Horse extends TenantModel
{
    use SoftDeletes;

    protected $table = 'horses';

    protected $fillable = [
        'name', 'microchip', 'passport_number', 'ueln',
        'breed', 'sex', 'color', 'birth_date',
        'owner_client_id', 'cover_image_path',
        'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'owner_client_id');
    }

    public function healthRecords(): HasMany
    {
        return $this->hasMany(HealthRecord::class);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->diffInYears(now()) !== null
            ? (int) $this->birth_date->diffInYears(now())
            : null;
    }
}
