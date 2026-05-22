<?php

declare(strict_types=1);

namespace App\Services\CompanyLookup;

use App\Models\Central\SystemSetting;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * VIES (VAT Information Exchange System) — walidacja NIP-ów UE.
 * Patrz: https://ec.europa.eu/taxation_customs/vies/.
 *
 * Endpoint REST (publiczny, bez API key):
 *   GET {base}/check-vat-number?countryCode=DE&vatNumber=123456789
 *
 * Zwraca JSON z polami:
 *   isValid (bool), name (string|null), address (string|null),
 *   countryCode, vatNumber, requestDate
 *
 * Adres VIES NIE jest podzielony na street/postal/city — to single
 * string multiline. UI musi obsłużyć to inaczej niż GUS (gdzie mamy
 * granular split).
 *
 * Cache: 24h na (cc, vat) — VIES nie zmienia się często, a API ma
 * rate-limit (per-MS, ~30/min). Cache wartością `null` znaczy "valid=false"
 * lub "not_found"; cache wartością array znaczy "valid + data".
 *
 * Soft-fail: gdy VIES API padnie / timeout, zwracamy null + log warning.
 * Caller widzi UI feedback "nie udało się zweryfikować" i może spróbować
 * ponownie lub wpisać dane ręcznie.
 */
class ViesService
{
    private const DEFAULT_BASE_URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api';

    /**
     * 27 państw członkowskich UE (kody ISO 3166-1 alpha-2 z wyjątkami:
     * Grecja = EL, nie GR — to wymóg systemu VIES).
     *
     * @var list<string>
     */
    private const EU_COUNTRY_CODES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'EL', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /** @return list<string> */
    public static function euCountryCodes(): array
    {
        return self::EU_COUNTRY_CODES;
    }

    public static function isEuCountryCode(string $code): bool
    {
        return in_array(strtoupper(trim($code)), self::EU_COUNTRY_CODES, true);
    }

    /**
     * Próbuje rozpoznać i wyciągnąć (countryCode, vatNumber) z dowolnego
     * input'u typu "DE123456789" / "de 123 456 789" / "PL 526-025-02-74".
     * Zwraca null gdy brak prefix UE.
     *
     * @return array{country_code: string, vat_number: string}|null
     */
    public static function parseEuVatId(string $input): ?array
    {
        $clean = strtoupper(preg_replace('/[\s\-\.]/', '', trim($input)) ?? '');
        if (strlen($clean) < 4) {
            return null;
        }
        $cc = substr($clean, 0, 2);
        if (! self::isEuCountryCode($cc)) {
            return null;
        }
        $num = substr($clean, 2);
        if (! preg_match('/^[A-Z0-9+\*]{2,12}$/', $num)) {
            return null;
        }

        return ['country_code' => $cc, 'vat_number' => $num];
    }

    /**
     * Główna metoda — sprawdza NIP UE w VIES. Zwraca array z danymi
     * firmy gdy valid + dostępne, null gdy invalid / not found / API down.
     *
     * @return array{valid: bool, country_code: string, vat_number: string, name: ?string, address: ?string, source: string}|null
     */
    public function validate(string $countryCode, string $vatNumber): ?array
    {
        $cc = strtoupper(trim($countryCode));
        $vat = preg_replace('/[\s\-\.]/', '', trim($vatNumber)) ?? '';
        if (! self::isEuCountryCode($cc) || $vat === '') {
            return null;
        }

        $cacheKey = "vies:{$cc}:{$vat}";

        return Cache::remember($cacheKey, now()->addDay(), function () use ($cc, $vat) {
            // Priorytet: master-admin override w `central.system_settings`
            // (SystemSetting::setValue('vies.base_url', ...)) → env / config
            // services.vies.base_url → default. Pozwala admin'owi przepiąć
            // na proxy / mirror bez deploy'u.
            $adminOverride = (string) (SystemSetting::getValue('vies.base_url', '') ?? '');
            $baseUrl = $adminOverride !== ''
                ? $adminOverride
                : (string) (Config::get('services.vies.base_url') ?: self::DEFAULT_BASE_URL);

            try {
                $response = $this->http
                    ->timeout(15)
                    ->acceptJson()
                    ->get($baseUrl.'/check-vat-number', [
                        'countryCode' => $cc,
                        'vatNumber' => $vat,
                    ]);

                if (! $response->successful()) {
                    Log::info('VIES API non-2xx', [
                        'cc' => $cc, 'vat' => $vat, 'status' => $response->status(),
                    ]);

                    return null;
                }

                $payload = $response->json();
                $isValid = (bool) data_get($payload, 'isValid', false);
                if (! $isValid) {
                    return [
                        'valid' => false,
                        'country_code' => $cc,
                        'vat_number' => $vat,
                        'name' => null,
                        'address' => null,
                        'source' => 'vies',
                    ];
                }

                $address = (string) data_get($payload, 'address', '');
                // VIES czasem zwraca "---" jako placeholder gdy państwo
                // nie udostępnia danych firmy (DE, ES — privacy).
                $name = (string) data_get($payload, 'name', '');
                $name = ($name === '---' || $name === '') ? null : $name;
                $address = ($address === '---' || $address === '') ? null : trim($address);

                return [
                    'valid' => true,
                    'country_code' => $cc,
                    'vat_number' => $vat,
                    'name' => $name,
                    'address' => $address,
                    'source' => 'vies',
                ];
            } catch (Throwable $e) {
                Log::warning('VIES API call failed', [
                    'cc' => $cc, 'vat' => $vat, 'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }
}
