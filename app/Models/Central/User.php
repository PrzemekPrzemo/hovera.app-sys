<?php

declare(strict_types=1);

namespace App\Models\Central;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasUlids, Notifiable, SoftDeletes;

    protected $connection = 'central';

    protected $table = 'users';

    protected $fillable = [
        'email', 'name', 'password',
        'locale', 'timezone',
        'is_master_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'is_master_admin' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_id');
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->whereNull('revoked_at');
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            // /admin: any authenticated user passes Filament's gate; the
            // EnsureMasterAdmin middleware that runs right after redirects
            // non-master users to /app instead of throwing 403 (friendlier
            // when an "intended URL" sends a tenant user there post-login).
            'admin' => true,
            // Master admin może wejść na /app — bez tego nie mógłby
            // się zalogować (po PR #65 wszyscy logują się przez /app/login).
            // Master nie ma membership, ale powinien mieć access do panelu
            // klienta (np. żeby zobaczyć perspektywę stajni / debugować).
            // /transport analogicznie — RequireTenantType middleware odbija
            // niewłaściwy typ tenanta na właściwy panel.
            // /owner — horse owner panel. Bez tej entry impersonacja master
            // admina ("Zaloguj jako właściciel" z /admin/horse-owners) dawała
            // 403 (Filament default → false). RequireTenantType:horse_owner
            // middleware już bouncuje wrong-type tenants, więc tu otwieramy
            // analogicznie do app/transport.
            'app', 'transport', 'owner' => $this->is_master_admin || $this->activeMemberships()->exists(),
            default => false,
        };
    }
}
