<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Central-level external specialist (vet, farrier, dietetyk) z magic-link
 * auth. Cross-tenant identity — jeden vet może być zaproszony przez wiele
 * stajni do różnych koni.
 *
 * Hybrid invite flow (PR O5 Channel B):
 *   1. Stable zaprasza → ExternalSpecialist created (jeśli email nowy) +
 *      SpecialistMagicLink kind=initial_setup
 *   2. Vet klika link, ustawia hasło + email verification code
 *   3. UI thread'ów pokazuje 'unverified' badge dopóki `verified_at` null
 *   4. Master-admin manualnie potwierdza (PWZ check) — `verified_at` set
 *
 * Auth subject: w Filament Specialist panel używany jako alternatywny
 * auth provider — osobny guard 'specialist' (nie tenant, nie central
 * users).
 *
 * @property string $id
 * @property string $email
 * @property string $display_name
 * @property string|null $specialty
 * @property string|null $phone
 * @property string|null $password_hash
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $verified_at
 * @property string|null $verified_by_user_id
 * @property string|null $created_by_user_id
 * @property array<string,mixed>|null $metadata
 */
class ExternalSpecialist extends Model
{
    use HasFactory;
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    protected $connection = 'central';

    protected $table = 'external_specialists';

    protected $fillable = [
        'email', 'display_name', 'specialty', 'phone',
        'password_hash', 'email_verified_at',
        'verified_at', 'verified_by_user_id', 'created_by_user_id',
        'metadata',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(SpecialistMagicLink::class, 'specialist_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * `unverified` flag dla UI — wyświetla badge "niezweryfikowany"
     * dopóki master-admin nie potwierdzi PWZ.
     */
    protected function isVerified(): Attribute
    {
        return Attribute::get(fn () => $this->verified_at !== null);
    }

    /**
     * Czy specjalista skończył account setup (ustawił hasło). Przed
     * setup'em istnieje row ale nie można się logować.
     */
    protected function hasCompletedSetup(): Attribute
    {
        return Attribute::get(fn () => $this->password_hash !== null && $this->email_verified_at !== null);
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function routeNotificationForMail(): string
    {
        return $this->email;
    }
}
