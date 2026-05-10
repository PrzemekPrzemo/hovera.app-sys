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
        'trial_max_horses', 'trial_max_clients',
        'stripe_customer_id', 'stripe_subscription_id',
        'current_period_ends_at', 'subscription_ends_at',
        'branding', 'settings',
        'custom_domain', 'custom_domain_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'custom_domain_verified_at' => 'datetime',
            'health_score' => 'integer',
            'db_port' => 'integer',
            'trial_max_horses' => 'integer',
            'trial_max_clients' => 'integer',
        ];
    }

    /**
     * True when this tenant has a custom domain that has been verified
     * (DNS confirmed by the admin). Until verified, the middleware
     * ignores the column to prevent half-configured CNAMEs from blackholing.
     */
    public function hasVerifiedCustomDomain(): bool
    {
        return $this->custom_domain !== null && $this->custom_domain_verified_at !== null;
    }

    /**
     * Plan-level gate — only plans with `features.vanity_domain = true`
     * may set a custom domain. Master admin flips the feature in plans.
     */
    public function planAllowsVanityDomain(): bool
    {
        return (bool) data_get($this->plan?->features ?? [], 'vanity_domain', false);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
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
            'driver' => 'mysql',
            'host' => $this->db_host,
            'port' => $this->db_port,
            'database' => $this->db_name,
            'username' => $this->db_username,
            'password' => $this->db_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ];
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'past_due'], true);
    }

    /**
     * True when the trial has ended AND no Stripe subscription is bound —
     * i.e. the tenant must pick a paid plan to keep using the panel.
     * Master admins / Free plan are checked higher up the call stack.
     */
    public function trialHasExpired(): bool
    {
        if ($this->stripe_subscription_id !== null) {
            return false;
        }

        return $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Aktualne limity planu z poprawką na trial — stajnia w trialu
     * ma WSZYSTKIE feature'y planu Pro, ale konie i klienci są przycięte
     * do `trial_max_horses` / `trial_max_clients`. Po flipie statusu na
     * `active` (Stripe webhook), limity przepadają i schodzimy do pełnej
     * tabeli z `plan->limits`.
     *
     * Zwraca tablicę z kluczami:
     *   - max_horses, max_clients, max_users, max_storage_mb
     * `-1` traktowane jako unlimited (jak w PlansSeeder).
     *
     * @return array<string,int>
     */
    public function effectiveLimits(): array
    {
        $planLimits = $this->plan?->limits ?? [];

        $defaults = [
            'max_horses' => (int) ($planLimits['max_horses'] ?? 0),
            'max_clients' => (int) ($planLimits['max_clients'] ?? 0),
            'max_users' => (int) ($planLimits['max_users'] ?? 0),
            'max_storage_mb' => (int) ($planLimits['max_storage_mb'] ?? 0),
        ];

        if ($this->status !== 'trialing') {
            return $defaults;
        }

        if ($this->trial_max_horses !== null) {
            $defaults['max_horses'] = (int) $this->trial_max_horses;
        }
        if ($this->trial_max_clients !== null) {
            $defaults['max_clients'] = (int) $this->trial_max_clients;
        }

        return $defaults;
    }
}
