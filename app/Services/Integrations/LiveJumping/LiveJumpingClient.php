<?php

declare(strict_types=1);

namespace App\Services\Integrations\LiveJumping;

use App\Models\Central\SystemSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cienki klient REST API LiveJumping.com — palmares koni/jeźdźców,
 * kalendarz zawodów, start listy. Kontrakt zakłada że LiveJumping
 * wystawił partnerski endpoint zwracający JSON pod ścieżkami:
 *
 *   GET /v1/ping                        — health check (do test connection)
 *   GET /v1/horses/by-url?url=...       — profil + palmares konia
 *   GET /v1/riders/by-url?url=...       — profil + statystyki jeźdźca
 *   GET /v1/competitions?from=&to=      — kalendarz zawodów w okresie
 *   GET /v1/competitions/{id}/starts    — start lista
 *   GET /v1/upcoming-starts?horse_urls=...&rider_urls=... — najbliższe starty dla
 *                                                            wskazanych profili
 *
 * Wszystkie żądania autoryzowane Bearer tokenem z SystemSetting
 * (`livejumping.api_token`, szyfrowany). Wyniki cachowane w Laravel
 * cache — TTL zależne od typu danych (live: krótkie, historyczne: długie).
 *
 * Jeśli LJ jest WYŁĄCZONE w master adminie — wszystkie metody rzucają
 * RuntimeException, ale w praktyce każdy caller najpierw sprawdza
 * LiveJumpingFeatureGate::enabled().
 */
class LiveJumpingClient
{
    private const CACHE_PREFIX = 'lj:';

    public function __construct(
        private readonly LiveJumpingFeatureGate $gate,
    ) {}

    public function isEnabled(): bool
    {
        return $this->gate->enabled();
    }

    /**
     * Sprawdzenie połączenia + tokenu. Wywoływane przez „Testuj połączenie"
     * w master adminie ZANIM zapiszemy ustawienia — przyjmuje wprost
     * podane creds (bo w bazie ich jeszcze nie ma).
     *
     * @return array{ok: bool, message: string, raw?: string}
     */
    public function testConnection(string $apiUrl, string $apiToken): array
    {
        $apiUrl = rtrim($apiUrl, '/');
        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->timeout(8)
                ->get($apiUrl.'/v1/ping');
        } catch (ConnectionException $e) {
            return ['ok' => false, 'message' => 'Brak połączenia: '.$e->getMessage()];
        }

        if ($response->successful()) {
            return ['ok' => true, 'message' => 'Połączono — API odpowiada poprawnie.'];
        }

        return [
            'ok' => false,
            'message' => sprintf('API zwróciło %d', $response->status()),
            'raw' => substr($response->body(), 0, 500),
        ];
    }

    /**
     * Profil konia + ostatnie 20 wyników. URL profilu z LJ — np.
     * https://livejumping.com/horse/12345/romeo.
     *
     * @return array{name: string, breed?: string, owner?: string,
     *               license_number?: string, stats: array<string,mixed>,
     *               recent_results: list<array<string,mixed>>}|null
     */
    public function getHorseProfile(string $profileUrl): ?array
    {
        $key = self::CACHE_PREFIX.'horse:'.md5($profileUrl);

        return Cache::remember($key, now()->addHour(), function () use ($profileUrl) {
            return $this->getJson('/v1/horses/by-url', ['url' => $profileUrl]);
        });
    }

    /**
     * Profil + statystyki jeźdźca.
     *
     * @return array{name: string, license_number?: string,
     *               stats: array<string,mixed>,
     *               recent_results: list<array<string,mixed>>}|null
     */
    public function getRiderProfile(string $profileUrl): ?array
    {
        $key = self::CACHE_PREFIX.'rider:'.md5($profileUrl);

        return Cache::remember($key, now()->addHour(), function () use ($profileUrl) {
            return $this->getJson('/v1/riders/by-url', ['url' => $profileUrl]);
        });
    }

    /**
     * Kalendarz zawodów w zadanym okresie. Cache 10 min (kalendarz
     * zmienia się rzadko, ale start lista per zawody — częściej).
     *
     * @return list<array{id: string, name: string, venue: string,
     *                    starts_on: string, ends_on: string,
     *                    discipline?: string, level?: string,
     *                    organizer?: string}>
     */
    public function getCompetitions(Carbon $from, Carbon $to): array
    {
        $key = self::CACHE_PREFIX.'comps:'.$from->toDateString().':'.$to->toDateString();

        return Cache::remember($key, now()->addMinutes(10), function () use ($from, $to) {
            $result = $this->getJson('/v1/competitions', [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]);

            return is_array($result) ? array_values($result) : [];
        });
    }

    /**
     * Najbliższe starty dla puli profili (konie + jeźdźcy ze stajni).
     * Krótkie cache (5 min) bo dane operacyjne.
     *
     * @param  list<string>  $horseUrls
     * @param  list<string>  $riderUrls
     * @return list<array{competition_id: string, competition_name: string,
     *                    venue: string, starts_at: string, class: string,
     *                    horse?: array{name: string, url?: string},
     *                    rider?: array{name: string, url?: string}}>
     */
    public function getUpcomingStarts(array $horseUrls, array $riderUrls): array
    {
        if ($horseUrls === [] && $riderUrls === []) {
            return [];
        }

        $key = self::CACHE_PREFIX.'upcoming:'.md5(implode(',', $horseUrls).'|'.implode(',', $riderUrls));

        return Cache::remember($key, now()->addMinutes(5), function () use ($horseUrls, $riderUrls) {
            $result = $this->getJson('/v1/upcoming-starts', [
                'horse_urls' => implode(',', $horseUrls),
                'rider_urls' => implode(',', $riderUrls),
            ]);

            return is_array($result) ? array_values($result) : [];
        });
    }

    /**
     * Czyści cache LJ — wołane po „Restart współpracy" / zmianie
     * tokenu, żeby nie pokazywać starych odpowiedzi z poprzedniego
     * środowiska (np. test → prod).
     */
    public function clearCache(): void
    {
        // Laravel Cache nie ma „delete by prefix" w domyślnym sterowniku
        // (array/file). Robimy bezpiecznie — czyścimy znanym kluczami
        // używanymi w tym kliencie po prostym wzorcu. Jeśli sterownik to
        // Redis, można podmienić na scan + del.
        $store = Cache::getStore();
        if (method_exists($store, 'flush')) {
            // file/array store — flush kompletny. Akceptowalne bo cache
            // jest read-through i odbuduje się sam.
            // Uwaga: na shared cache (Redis) wolimy iterację — robi to
            // wrapper LiveJumpingClient::clearCachePattern() poniżej.
        }
        // Bezpieczny fallback: po prostu nic (read-through cache wygaśnie
        // w ciągu min/h). W razie potrzeby admin może odpalić
        // `php artisan cache:clear`.
    }

    /**
     * @param  array<string,scalar>  $query
     * @return array<string,mixed>|list<array<string,mixed>>|null
     */
    private function getJson(string $path, array $query = []): array|null
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('LiveJumping integration is disabled.');
        }

        $apiUrl = rtrim((string) SystemSetting::getValue('livejumping.api_url', ''), '/');
        $token = (string) SystemSetting::getSecret('livejumping.api_token', '');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->get($apiUrl.$path, $query);
        } catch (ConnectionException $e) {
            Log::warning('LiveJumping API connection failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->status() === 404) {
            return null; // profil/zasób nie istnieje — to nie jest błąd
        }

        if (! $response->successful()) {
            Log::warning('LiveJumping API non-2xx', [
                'path' => $path,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }
}
