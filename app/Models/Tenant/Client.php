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
        'armir_producer_id', 'pesel',
        'street', 'postal_code', 'city', 'country',
        'rodo_consent_at', 'rodo_consent_source',
        'central_user_id', 'notes', 'metadata',
        'magic_link_token_hash', 'magic_link_expires_at', 'last_logged_in_at',
        'livejumping_profile_url',
    ];

    protected $hidden = [
        'magic_link_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'rodo_consent_at' => 'datetime',
            'magic_link_expires_at' => 'datetime',
            'last_logged_in_at' => 'datetime',
        ];
    }

    public function horses(): HasMany
    {
        return $this->hasMany(Horse::class, 'owner_client_id');
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(CalendarEntry::class);
    }
}
