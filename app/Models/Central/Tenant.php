<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Tenant extends Model
{
    use HasUlids, SoftDeletes;

    protected $connection = 'central';
    protected $table = 'tenants';

    protected $fillable = [
        'slug', 'name', 'legal_name', 'tax_id',
        'db_host', 'db_port', 'db_name', 'db_username', 'db_password_encrypted',
        'country', 'locale', 'timezone', 'currency',
        'plan_id', 'status', 'trial_ends_at',
        'branding', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'branding'          => 'array',
            'settings'          => 'array',
            'trial_ends_at'     => 'datetime',
            'suspended_at'      => 'datetime',
            'last_activity_at'  => 'datetime',
            'health_score'      => 'integer',
            'db_port'           => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getDbPasswordAttribute(): string
    {
        return Crypt::decryptString($this->db_password_encrypted);
    }

    public function setDbPasswordAttribute(string $value): void
    {
        $this->attributes['db_password_encrypted'] = Crypt::encryptString($value);
    }

    /**
     * Connection config used to talk to this tenant's database.
     * Cached on the request via TenantManager — do not call hot.
     */
    public function databaseConnectionConfig(): array
    {
        return [
            'driver'         => 'mysql',
            'host'           => $this->db_host,
            'port'           => $this->db_port,
            'database'       => $this->db_name,
            'username'       => $this->db_username,
            'password'       => $this->db_password,
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => 'InnoDB',
        ];
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'past_due'], true);
    }
}
