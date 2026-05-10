<?php

declare(strict_types=1);

namespace App\Services\Sync;

/**
 * Cursor format: base64( "{tenant_id}:{sync_version}" ).
 * Treated as opaque by clients; server can validate that the tenant_id
 * matches the request to detect cursors stolen from another tenant.
 */
class CursorCodec
{
    public static function encode(int|string $tenantId, int $version): string
    {
        return rtrim(strtr(base64_encode($tenantId.':'.$version), '+/', '-_'), '=');
    }

    /**
     * @return array{0: string, 1: int} [tenantId, version]
     */
    public static function decode(?string $cursor): array
    {
        if (! $cursor) {
            return ['', 0];
        }
        $padded = str_pad(strtr($cursor, '-_', '+/'), strlen($cursor) % 4 === 0 ? strlen($cursor) : strlen($cursor) + (4 - strlen($cursor) % 4), '=');
        $raw = base64_decode($padded, true);
        if ($raw === false || ! str_contains($raw, ':')) {
            return ['', 0];
        }
        [$tid, $v] = explode(':', $raw, 2);

        return [$tid, max(0, (int) $v)];
    }
}
