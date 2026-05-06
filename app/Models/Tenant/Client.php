<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends TenantModel
{
    use SoftDeletes;

    protected $table = 'clients';

    protected $fillable = [
        'type', 'name', 'email', 'phone', 'tax_id',
        'street', 'postal_code', 'city', 'country',
        'rodo_consent_at', 'rodo_consent_source',
        'central_user_id', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'rodo_consent_at' => 'datetime',
        ];
    }

    public function horses(): HasMany
    {
        return $this->hasMany(Horse::class, 'owner_client_id');
    }
}
