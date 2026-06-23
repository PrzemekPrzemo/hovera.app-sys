<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wewnętrzny kanał komunikacji stajni (PR O5 Channel C, epic 2).
 *
 * Domyślne kanały (#general, #weterynaria, #transport) mają `is_default`
 * i nie podlegają usunięciu. Admin może dodać własne kanały.
 *
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 * @property string|null $created_by_user_id
 */
class InternalChannel extends TenantModel
{
    use SoftDeletes;

    protected $table = 'internal_channels';

    protected $fillable = [
        'slug', 'name', 'description', 'is_default', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(InternalChannelMember::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InternalMessage::class, 'channel_id')->orderBy('created_at');
    }

    /** @param Builder<InternalChannel> $query */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /** @param Builder<InternalChannel> $query */
    public function scopeForMember(Builder $query, string $userId): Builder
    {
        return $query->whereHas('members', fn (Builder $q) => $q->where('user_id', $userId));
    }

    public function hasMember(string $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }
}
