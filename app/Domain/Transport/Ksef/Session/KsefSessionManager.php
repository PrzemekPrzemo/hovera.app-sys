<?php

declare(strict_types=1);

namespace App\Domain\Transport\Ksef\Session;

use App\Domain\Transport\Ksef\Api\KsefHttpClient;
use App\Models\Central\KsefSessionToken;
use App\Models\Central\Tenant;
use Illuminate\Support\Carbon;

/**
 * Zarządza cyklem życia SessionToken'a KSeF per (tenant, environment).
 *
 * KSeF wymaga handshake'u (AuthorisationChallenge → InitToken) PRZED
 * każdym call'em /Invoice/Send i /Invoice/Status. Bez cache'owania
 * sesji każda operacja kosztuje 3 round-tripy. Tutaj trzymamy aktywny
 * SessionToken + AES klucz w `ksef_session_tokens` (central DB) i
 * zwracamy go dopóki jest fresh (>60s do expiry).
 *
 * Po wygaśnięciu — re-handshake. Stary wiersz nadpisujemy (unique
 * constraint na tenant_id+environment chroni przed śmieciem).
 */
class KsefSessionManager
{
    public function __construct(
        private readonly KsefHttpClient $client,
    ) {}

    /**
     * Pobierz aktywny session token + AES klucz dla danego tenanta i
     * środowiska. Jeśli cache zawiera fresh wiersz — zwracamy. Inaczej
     * wykonujemy pełny handshake i nadpisujemy cache.
     *
     * @return array{token: string, aes_key: string, expires_at: Carbon}
     */
    public function getActiveSession(
        Tenant $tenant,
        string $environment,
        string $authToken,
        string $nip,
    ): array {
        $cached = KsefSessionToken::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment', $environment)
            ->first();

        if ($cached !== null && $cached->isFresh()) {
            $token = $cached->getToken();
            $key = $cached->getAesKey();
            if ($token !== null && $key !== null) {
                return [
                    'token' => $token,
                    'aes_key' => $key,
                    'expires_at' => $cached->expires_at,
                ];
            }
            // Crypt rotation: kasujemy, re-handshake.
            $cached->delete();
        }

        return $this->performHandshake($tenant, $environment, $authToken, $nip, $cached);
    }

    /**
     * Wymuś re-handshake (np. po HTTP 401 z KSeF — token revoked).
     *
     * @return array{token: string, aes_key: string, expires_at: Carbon}
     */
    public function forceRefresh(
        Tenant $tenant,
        string $environment,
        string $authToken,
        string $nip,
    ): array {
        KsefSessionToken::query()
            ->where('tenant_id', $tenant->id)
            ->where('environment', $environment)
            ->delete();

        return $this->performHandshake($tenant, $environment, $authToken, $nip, null);
    }

    /**
     * @return array{token: string, aes_key: string, expires_at: Carbon}
     */
    private function performHandshake(
        Tenant $tenant,
        string $environment,
        string $authToken,
        string $nip,
        ?KsefSessionToken $existingRow,
    ): array {
        $challenge = $this->client->getAuthorizationChallenge($environment, $nip);
        $session = $this->client->initSessionTokenWithToken(
            environment: $environment,
            nip: $nip,
            authToken: $authToken,
            challenge: $challenge['challenge'],
            timestamp: $challenge['timestamp'],
        );

        // updateOrCreate — race condition (dwa worker'y handshake'ujące
        // równolegle) jest tu OK: ostatni wygrywa, oba mają sensowny
        // session token. Unique constraint chroni przed dwoma wierszami.
        $row = $existingRow ?? new KsefSessionToken;
        $row->tenant_id = $tenant->id;
        $row->environment = $environment;
        $row->setToken($session['session_token']);
        $row->setAesKey($session['aes_key']);
        $row->expires_at = $session['expires_at'];
        $row->save();

        return [
            'token' => $session['session_token'],
            'aes_key' => $session['aes_key'],
            'expires_at' => $session['expires_at'],
        ];
    }
}
