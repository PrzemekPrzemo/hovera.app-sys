<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant\Driver;
use Illuminate\Support\Facades\Auth;

/**
 * Resolve aktualnie zalogowanego user'a → Driver record w danym tenant
 * DB. Driver ma `central_user_id` linkujący do `users.id` z central DB.
 *
 * Używane przez driver-only pages w `/transport` panelu (Moje trasy,
 * Moje dokumenty) do scope'owania query do własnych rekordów.
 *
 * Cached per-request — `static $cached` żeby nie odpytywać DB raz na
 * widget. Tenant init nie utrzymuje persistent state'u, więc cache jest
 * idempotentny.
 */
class CurrentDriverResolver
{
    private static ?Driver $cached = null;

    private static ?string $cachedForUserId = null;

    public function current(): ?Driver
    {
        $userId = Auth::id();
        if ($userId === null) {
            return null;
        }

        if (self::$cached !== null && self::$cachedForUserId === (string) $userId) {
            return self::$cached;
        }

        self::$cached = Driver::query()
            ->where('central_user_id', $userId)
            ->first();
        self::$cachedForUserId = (string) $userId;

        return self::$cached;
    }

    /**
     * Reset cache — przydatne w testach gdy zmieniamy user'a w jednym scope.
     */
    public static function flush(): void
    {
        self::$cached = null;
        self::$cachedForUserId = null;
    }
}
