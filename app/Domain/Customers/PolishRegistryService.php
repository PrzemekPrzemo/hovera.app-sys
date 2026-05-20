<?php

declare(strict_types=1);

namespace App\Domain\Customers;

use App\Domain\Customers\Data\CompanyLookupResult;
use App\Domain\Customers\Exceptions\CompanyLookupException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Weryfikacja danych firmowych w polskich publicznych rejestrach. Używamy
 * FREE/no-auth API:
 *
 *   - **MF Biała Lista Podatników VAT** (NIP lookup):
 *     GET https://wl-api.mf.gov.pl/api/search/nip/{nip}?date=YYYY-MM-DD
 *     Plus: free, no auth, oficjalne źródło NIP/VAT
 *     Minus: tylko aktywni płatnicy VAT (sole props bez VAT się nie znajdą)
 *
 *   - **KRS API** (KRS number lookup, S.A. / sp. z o.o. / sp.k.):
 *     GET https://api-krs.ms.gov.pl/api/krs/OdpisAktualny/{number}?rejestr=P&format=json
 *     Plus: free, no auth, Ministerstwo Sprawiedliwości
 *     Minus: tylko rejestr przedsiębiorców (P) — fundacje/stowarzyszenia
 *            wymagają rejestru S (out-of-scope dla transportu).
 *
 *   - **CEIDG** (sole proprietorships): planowany follow-up — wymaga API
 *     key + dłuższy bootstrap (datastore.ceidg.gov.pl). Aktualnie polecamy
 *     userom wpisać dane sole prop'a ręcznie.
 *
 * Patrz user feedback "weryfikacja danych w gus, krs, ceidg itp dla
 * późniejszego wystawienia fv".
 */
final class PolishRegistryService
{
    private const MF_BASE_URL = 'https://wl-api.mf.gov.pl/api/search/nip';

    private const KRS_BASE_URL = 'https://api-krs.ms.gov.pl/api/krs/OdpisAktualny';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly int $timeoutSeconds = 8,
    ) {}

    public function lookupByNip(string $nip): CompanyLookupResult
    {
        $normalized = preg_replace('/\D+/', '', $nip);
        if (strlen($normalized) !== 10) {
            throw CompanyLookupException::invalidIdentifier('mf', $nip, 'NIP musi mieć dokładnie 10 cyfr');
        }

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get(self::MF_BASE_URL.'/'.$normalized, [
                    'date' => now()->toDateString(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('MF Biała Lista lookup failed', ['nip' => $normalized, 'error' => $e->getMessage()]);

            throw CompanyLookupException::apiError('mf', 0, $e->getMessage());
        }

        if ($response->status() === 404) {
            throw CompanyLookupException::notFound('mf', $normalized);
        }
        if (! $response->successful()) {
            throw CompanyLookupException::apiError('mf', $response->status(), (string) $response->body());
        }

        $subject = $response->json('result.subject');
        if (! is_array($subject)) {
            throw CompanyLookupException::notFound('mf', $normalized);
        }

        $addressParts = array_filter([
            (string) ($subject['workingAddress'] ?? $subject['residenceAddress'] ?? ''),
        ]);
        $address = $addressParts === [] ? null : implode(', ', $addressParts);

        return new CompanyLookupResult(
            source: 'mf',
            name: (string) ($subject['name'] ?? '') ?: null,
            taxId: (string) ($subject['nip'] ?? $normalized),
            regon: (string) ($subject['regon'] ?? '') ?: null,
            krsNumber: (string) ($subject['krs'] ?? '') ?: null,
            address: $address,
            status: (string) ($subject['statusVat'] ?? '') ?: null,
            raw: $subject,
        );
    }

    public function lookupByKrs(string $krsNumber): CompanyLookupResult
    {
        $normalized = preg_replace('/\D+/', '', $krsNumber);
        if (strlen($normalized) !== 10) {
            throw CompanyLookupException::invalidIdentifier('krs', $krsNumber, 'KRS musi mieć 10 cyfr');
        }

        try {
            $response = $this->http
                ->timeout($this->timeoutSeconds)
                ->acceptJson()
                ->get(self::KRS_BASE_URL.'/'.$normalized, [
                    'rejestr' => 'P',
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            Log::warning('KRS lookup failed', ['krs' => $normalized, 'error' => $e->getMessage()]);

            throw CompanyLookupException::apiError('krs', 0, $e->getMessage());
        }

        if ($response->status() === 404) {
            throw CompanyLookupException::notFound('krs', $normalized);
        }
        if (! $response->successful()) {
            throw CompanyLookupException::apiError('krs', $response->status(), (string) $response->body());
        }

        $payload = (array) $response->json();
        // KRS JSON ma głęboko zagnieżdżoną strukturę pod
        // odpis.dane.dzial1.danePodmiotu. Wyciągamy bezpiecznie.
        $danePodmiotu = (array) data_get($payload, 'odpis.dane.dzial1.danePodmiotu', []);
        $siedzibaAdres = (array) data_get($payload, 'odpis.dane.dzial1.siedzibaIAdres.adres', []);
        $identyfikatory = (array) data_get($payload, 'odpis.dane.dzial1.identyfikatory', []);

        $name = (string) ($danePodmiotu['nazwa'] ?? '');
        $address = $this->formatKrsAddress($siedzibaAdres);

        return new CompanyLookupResult(
            source: 'krs',
            name: $name !== '' ? $name : null,
            taxId: (string) ($identyfikatory['nip'] ?? '') ?: null,
            regon: (string) ($identyfikatory['regon'] ?? '') ?: null,
            krsNumber: $normalized,
            address: $address !== '' ? $address : null,
            raw: $payload,
        );
    }

    /** @param array<string,mixed> $a */
    private function formatKrsAddress(array $a): string
    {
        if ($a === []) {
            return '';
        }

        $street = trim(
            ((string) ($a['ulica'] ?? '')).' '.
            ((string) ($a['nrDomu'] ?? '')).
            (! empty($a['nrLokalu']) ? '/'.((string) $a['nrLokalu']) : '')
        );
        $city = trim(
            ((string) ($a['kodPocztowy'] ?? '')).' '.
            ((string) ($a['miejscowosc'] ?? ''))
        );

        return trim(implode(', ', array_filter([$street, $city])));
    }
}
