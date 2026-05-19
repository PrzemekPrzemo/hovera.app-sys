<?php

declare(strict_types=1);

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Cached KSeF SessionToken — patrz migration
 * 2026_05_18_230000_create_ksef_session_tokens_table.php.
 *
 * Tabelka w central DB. Wiersz reprezentuje aktywną sesję KSeF dla
 * pary (tenant, environment). Po wygaśnięciu (expires_at) usuwany lub
 * zastępowany przez KsefSessionManager.
 *
 * Pola sesji ZAWSZE wracają zaszyfrowane do toArray() — nigdy nie
 * dehydratujemy session_token / aes_key do JSONa. Akcesor `getToken()`
 * / `getAesKey()` to jedyne wejście do plaintextu i każde wywołanie
 * jest świadome (per audit).
 */
class KsefSessionToken extends Model
{
    use HasUlids;

    protected $connection = 'central';

    protected $table = 'ksef_session_tokens';

    protected $fillable = [
        'tenant_id', 'environment',
        'session_token_encrypted', 'aes_key_encrypted',
        'expires_at',
    ];

    /**
     * Zaszyfrowane sekrety NIGDY nie pojawiają się w serializacji
     * (toArray, API). Akcesory są jedynym oficjalnym wejściem.
     *
     * @var list<string>
     */
    protected $hidden = ['session_token_encrypted', 'aes_key_encrypted'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Zaszyfrowuje i zapisuje plaintext session token.
     */
    public function setToken(string $token): void
    {
        $this->session_token_encrypted = Crypt::encryptString($token);
    }

    /**
     * Pobiera plaintext session token. Zwraca null jeśli rekord
     * został zaszyfrowany na innym APP_KEY (rotacja klucza).
     */
    public function getToken(): ?string
    {
        $raw = $this->getRawOriginal('session_token_encrypted');
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Zaszyfrowuje i zapisuje AES-256 klucz binarny (wrap przez base64
     * przed Crypt, żeby nie wpaść w UTF-8 walidację Crypta).
     */
    public function setAesKey(string $binaryKey): void
    {
        $this->aes_key_encrypted = Crypt::encryptString(base64_encode($binaryKey));
    }

    /**
     * Pobiera binarny AES klucz (32 bytes dla AES-256).
     */
    public function getAesKey(): ?string
    {
        $raw = $this->getRawOriginal('aes_key_encrypted');
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $b64 = Crypt::decryptString($raw);
            $decoded = base64_decode($b64, true);

            return is_string($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Czy token wciąż jest ważny (z 60s marginesem na clock skew /
     * latency do MF — jeśli expiruje za <60s, lepiej już re-handshake).
     */
    public function isFresh(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isAfter(now()->addSeconds(60));
    }
}
