<?php

declare(strict_types=1);

namespace App\Services\CompanyLookup;

use App\Models\Central\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * CEIDG (Centralna Ewidencja i Informacja o Działalności Gospodarczej)
 * REST API client — rejestr jednoosobowych działalności gospodarczych w PL.
 *
 * GUS pokrywa wszystkie podmioty (osoby prawne + fizyczne), ale dla
 * jednoosobowych działalności CEIDG zwraca jeszcze bardziej szczegółowe
 * dane (status, daty rejestracji/zawieszenia, telefon, adres do doręczeń).
 *
 * Auth: JWT bearer token, wydany po rejestracji na biznes.gov.pl/ceidg-api.
 * Bez tokenu service zwraca null (lookup po prostu pominie CEIDG).
 *
 * Endpoint: GET /CEIDG.DataStore/api/firma?nip={nip}
 *   - 200 + JSON: znaleziona firma
 *   - 200 + brak result'ów: NIP nie ma JDG (typowo osoba prawna — fallback na GUS+KRS)
 *   - 404: NIP nie istnieje w CEIDG
 *
 * Docs: https://datastore.ceidg.gov.pl/CEIDG.DataStore/Help
 */
class CeidgApiService
{
    public const BASE_URL = 'https://datastore.ceidg.gov.pl/CEIDG.DataStore/api';

    public const CACHE_TTL_SEC = 60 * 60 * 24; // 24h — JDG zmiany rzadkie

    public function isConfigured(): bool
    {
        return SystemSetting::getSecret('ceidg.api_token') !== null;
    }

    /**
     * Szukaj JDG po NIP. Zwraca null gdy:
     *   - NIP niepoprawny formalnie
     *   - token CEIDG nie skonfigurowany
     *   - NIP nie ma odpowiednika w CEIDG (zwykle = osoba prawna, użyj GUS+KRS)
     *   - CEIDG nieosiągalny
     *
     * @return array{
     *   nip:string, name:?string, status:?string,
     *   start_date:?string, suspend_date:?string,
     *   street:?string, building:?string, apartment:?string,
     *   postal_code:?string, city:?string, province:?string,
     *   email:?string, phone:?string,
     * }|null
     */
    public function findByNip(string $nip): ?array
    {
        $nip = preg_replace('/[^0-9]/', '', $nip);
        if (strlen((string) $nip) !== 10) {
            return null;
        }
        if (! $this->isConfigured()) {
            return null;
        }

        return Cache::remember("ceidg:nip:{$nip}", self::CACHE_TTL_SEC, fn () => $this->callSearch($nip));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function callSearch(string $nip): ?array
    {
        $token = SystemSetting::getSecret('ceidg.api_token');
        if ($token === null) {
            return null;
        }

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(15)
            ->retry(2, 1000, throw: false)
            ->withUserAgent('Hovera/1.0')
            ->get(self::BASE_URL.'/firma', ['nip' => $nip]);

        // 404 = NIP nie zarejestrowany w CEIDG. Nie błąd — fallback na GUS+KRS.
        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        // CEIDG wraps wynik w `firma` array (lista, zwykle 1 element po NIP).
        $firmas = (array) ($body['firma'] ?? []);
        if ($firmas === []) {
            return null;
        }
        $firma = (array) ($firmas[0] ?? []);
        if ($firma === []) {
            return null;
        }

        return $this->normalize($firma, $nip);
    }

    /**
     * @param  array<string,mixed>  $firma
     * @return array<string,mixed>
     */
    private function normalize(array $firma, string $nip): array
    {
        $address = (array) ($firma['adresDzialalnosci'] ?? $firma['adresKorespondencyjny'] ?? []);

        return [
            'nip' => $nip,
            'name' => (string) ($firma['nazwa'] ?? '') ?: null,
            'status' => (string) ($firma['status'] ?? '') ?: null,
            'start_date' => (string) ($firma['dataRozpoczecia'] ?? '') ?: null,
            'suspend_date' => (string) ($firma['dataZawieszenia'] ?? '') ?: null,
            'street' => (string) ($address['ulica'] ?? '') ?: null,
            'building' => (string) ($address['budynek'] ?? '') ?: null,
            'apartment' => (string) ($address['lokal'] ?? '') ?: null,
            'postal_code' => (string) ($address['kod'] ?? '') ?: null,
            'city' => (string) ($address['miasto'] ?? '') ?: null,
            'province' => (string) ($address['wojewodztwo'] ?? '') ?: null,
            'email' => (string) ($firma['email'] ?? '') ?: null,
            'phone' => (string) ($firma['telefon'] ?? '') ?: null,
        ];
    }
}
