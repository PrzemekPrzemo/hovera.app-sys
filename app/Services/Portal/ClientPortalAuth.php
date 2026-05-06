<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Models\Central\Tenant;
use App\Models\Tenant\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Magic-link auth for client portal. No passwords for MVP — riders
 * book a few times a year, and a password they'll never remember is
 * worse than an emailed link.
 *
 * Token model:
 *   1. user enters email → look up client in current tenant DB
 *   2. generate 40-char random token, store sha-256 hash + expiry
 *   3. email a Laravel signed URL with the raw token in the query
 *   4. on consume: verify signature → hash incoming token → compare
 *      to stored hash → if match and not expired, log in and clear
 *      the hash (single use)
 *
 * Session storage is namespaced per tenant slug (`client_portal.{slug}`)
 * so a user can be logged into two stables on the same device without
 * stepping on each other.
 */
class ClientPortalAuth
{
    public const TOKEN_TTL_MINUTES = 30;

    public const SESSION_KEY_PREFIX = 'client_portal.';

    /**
     * Issue a magic link. Returns the full signed URL the email should
     * link to. The raw token is embedded in the URL — never persisted
     * server-side; only the SHA-256 hash is stored on the client row.
     */
    public function issueMagicLink(Client $client, string $tenantSlug): string
    {
        $rawToken = Str::random(40);
        $expiresAt = Carbon::now()->addMinutes(self::TOKEN_TTL_MINUTES);

        $client->forceFill([
            'magic_link_token_hash' => hash('sha256', $rawToken),
            'magic_link_expires_at' => $expiresAt,
        ])->save();

        return URL::temporarySignedRoute(
            name: 'client_portal.login.consume',
            expiration: $expiresAt,
            parameters: [
                'slug' => $tenantSlug,
                'client' => $client->id,
                'token' => $rawToken,
            ],
        );
    }

    /**
     * Verify token + signature, log the client in, and clear the
     * one-time hash. Returns true on success, false on any mismatch
     * — the controller decides how to render that.
     */
    public function consume(Request $request, Client $client, string $rawToken, string $tenantSlug): bool
    {
        if (! $request->hasValidSignature()) {
            return false;
        }
        if (! $client->magic_link_token_hash || ! $client->magic_link_expires_at) {
            return false;
        }
        if ($client->magic_link_expires_at->isPast()) {
            return false;
        }
        if (! hash_equals($client->magic_link_token_hash, hash('sha256', $rawToken))) {
            return false;
        }

        $client->forceFill([
            'magic_link_token_hash' => null,
            'magic_link_expires_at' => null,
            'last_logged_in_at' => Carbon::now(),
        ])->save();

        $this->login($request, $client, $tenantSlug);

        return true;
    }

    public function login(Request $request, Client $client, string $tenantSlug): void
    {
        $request->session()->put($this->sessionKey($tenantSlug), [
            'client_id' => $client->id,
            'logged_in_at' => Carbon::now()->toIso8601String(),
        ]);
        $request->session()->regenerate();
    }

    public function logout(Request $request, string $tenantSlug): void
    {
        $request->session()->forget($this->sessionKey($tenantSlug));
        $request->session()->regenerate();
    }

    /**
     * Resolve the currently logged-in client for the given tenant slug.
     * Returns null when no session entry exists or the client row was
     * deleted out from under us.
     */
    public function current(Request $request, string $tenantSlug): ?Client
    {
        $payload = $request->session()->get($this->sessionKey($tenantSlug));
        if (! is_array($payload) || empty($payload['client_id'])) {
            return null;
        }

        return Client::query()->find($payload['client_id']);
    }

    /**
     * Builds the unsigned magic-link URL used by tests / logs. Production
     * code should always go through `issueMagicLink`.
     */
    public function magicLinkUrl(Tenant $tenant, Client $client, string $rawToken, Carbon $expiresAt): string
    {
        return URL::temporarySignedRoute(
            name: 'client_portal.login.consume',
            expiration: $expiresAt,
            parameters: [
                'slug' => $tenant->slug,
                'client' => $client->id,
                'token' => $rawToken,
            ],
        );
    }

    private function sessionKey(string $tenantSlug): string
    {
        return self::SESSION_KEY_PREFIX.$tenantSlug;
    }
}
