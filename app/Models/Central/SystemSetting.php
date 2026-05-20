<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Master-admin global settings store. Key/value pairs scoped to the
 * whole Hovera installation (NOT per stajnia). Used for shared config
 * like GUS API key, KRS cache TTL, KSeF feature flags.
 *
 * Sensitive keys (API tokens, secrets) should be encrypted before
 * storing — convenience helpers `setSecret()` / `getSecret()` wrap
 * Laravel Crypt::encryptString to keep call sites clean.
 *
 * Per-tenant config lives on `tenants.settings` JSON; do NOT store
 * tenant-specific values here.
 */
class SystemSetting extends Model
{
    protected $connection = 'central';

    protected $table = 'system_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['key', 'value', 'updated_at'];

    protected $casts = [
        'value' => 'array',
        'updated_at' => 'datetime',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        try {
            $row = static::query()->find($key);
        } catch (\Throwable) {
            // DB nieosiągalna albo tabela `system_settings` nie istnieje
            // (np. podczas route:list przed `migrate`, w container boot przy
            // CLI commands, w fresh installu). Caller dostaje $default —
            // providery powinny falbackować na config('...') / .env.
            return $default;
        }

        return $row?->value ?? $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()],
        );
    }

    /**
     * Stash an encrypted secret. Wraps Crypt::encryptString — bypasses
     * the JSON cast by storing as a raw string under the special form
     * `['__crypt' => '...']`.
     */
    public static function setSecret(string $key, string $plaintext): void
    {
        static::setValue($key, ['__crypt' => Crypt::encryptString($plaintext)]);
    }

    public static function getSecret(string $key, ?string $default = null): ?string
    {
        $value = static::getValue($key);
        if (! is_array($value) || ! isset($value['__crypt'])) {
            return $default;
        }
        try {
            return Crypt::decryptString((string) $value['__crypt']);
        } catch (\Throwable) {
            return $default;
        }
    }
}
