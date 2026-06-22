<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Magic link token dla `ExternalSpecialist` auth flow (PR O5 Channel B).
 *
 * Token storage: tylko hash (sha256 raw token). Plain token zwracany przez
 * `issue()` raz — embedowany w mailu — potem nie żyje już nigdzie.
 *
 * Lifecycle:
 *   - initial_setup → 7d expiry, jednorazowy, po kliknięciu → `used_at`
 *   - password_reset → 1h expiry (osobny PR)
 *   - login          → 15min expiry (passwordless future, osobny PR)
 *
 * @property string $id
 * @property string $specialist_id
 * @property string $token_hash
 * @property string $kind
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property string|null $issued_from_ip
 * @property string|null $issued_for_tenant_id
 */
class SpecialistMagicLink extends Model
{
    use HasUlids;

    public const KIND_INITIAL_SETUP = 'initial_setup';

    public const KIND_PASSWORD_RESET = 'password_reset';

    public const KIND_LOGIN = 'login';

    /**
     * TTL per kind (per captured decisions §3 — initial_setup = 7d).
     */
    public const TTL_BY_KIND = [
        self::KIND_INITIAL_SETUP => '+7 days',
        self::KIND_PASSWORD_RESET => '+1 hour',
        self::KIND_LOGIN => '+15 minutes',
    ];

    protected $connection = 'central';

    protected $table = 'specialist_magic_links';

    protected $fillable = [
        'specialist_id', 'token_hash', 'kind',
        'expires_at', 'used_at',
        'issued_from_ip', 'issued_for_tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(ExternalSpecialist::class, 'specialist_id');
    }

    public function issuingTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'issued_for_tenant_id');
    }

    /**
     * Generuje fresh magic link + zapisuje hash. Zwraca PLAIN token —
     * caller (zwykle invite service) wstawia go do mailera. Plain token
     * nigdzie poza return value nie żyje.
     *
     * @return array{model: self, plain_token: string}
     */
    public static function issue(
        ExternalSpecialist $specialist,
        string $kind,
        ?string $tenantId = null,
        ?string $ipAddress = null,
    ): array {
        if (! array_key_exists($kind, self::TTL_BY_KIND)) {
            throw new \InvalidArgumentException('Unknown magic link kind: '.$kind);
        }

        // 256-bit token (64 hex chars). cryptographically secure random.
        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);

        $model = self::create([
            'specialist_id' => $specialist->id,
            'token_hash' => $hash,
            'kind' => $kind,
            'expires_at' => Carbon::parse(self::TTL_BY_KIND[$kind]),
            'issued_from_ip' => $ipAddress,
            'issued_for_tenant_id' => $tenantId,
        ]);

        return ['model' => $model, 'plain_token' => $plain];
    }

    /**
     * Lookup linku po plain token + kind — używane przy click'u z maila.
     * Zwraca model gdy valid (nie expired, nie used, kind match), null
     * w przeciwnym razie. NIE oznacza linku jako used — to robi caller
     * po pomyślnym wykonaniu akcji (setup password etc.).
     */
    public static function findByPlainToken(string $plainToken, string $kind): ?self
    {
        $hash = hash('sha256', $plainToken);

        return self::query()
            ->where('token_hash', $hash)
            ->where('kind', $kind)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Mark link as used. Idempotent — drugie wywołanie nie zmieni nic.
     */
    public function markUsed(): void
    {
        if ($this->used_at !== null) {
            return;
        }
        $this->forceFill(['used_at' => now()])->save();
    }

    /**
     * Cleanup expired/used links — wywoływane przez cron (osobny PR).
     * Usuwa wszystko old by uniknąć table bloat'u.
     *
     * @return int liczba usuniętych
     */
    public static function pruneExpired(): int
    {
        return self::query()
            ->where(function ($q) {
                $q->where('expires_at', '<', now()->subDays(7))
                    ->orWhere(fn ($qq) => $qq->whereNotNull('used_at')->where('used_at', '<', now()->subDays(30)));
            })
            ->delete();
    }
}
