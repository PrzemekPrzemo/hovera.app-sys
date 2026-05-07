<?php

declare(strict_types=1);

namespace App\Services\CompanyLookup;

/**
 * Orchestrates GUS + KRS lookup for company auto-fill in Client form.
 *
 *   1. Validate NIP checksum locally (saves an API call on typos)
 *   2. Hit GUS by NIP — gets nazwa, REGON, address, type
 *   3. Optionally hit KRS to enrich (board members, capital) — only
 *      when a KRS number is found in GUS or supplied directly
 *
 * Returns a normalised payload regardless of source. Frontend can use
 * any subset.
 */
class CompanyLookupService
{
    public function __construct(
        private readonly GusApiService $gus,
        private readonly KrsApiService $krs,
    ) {}

    /**
     * Validate a Polish NIP via the standard weighted-sum checksum.
     */
    public static function isValidNip(string $nip): bool
    {
        $nip = preg_replace('/[^0-9]/', '', $nip);
        if (strlen((string) $nip) !== 10) {
            return false;
        }
        $weights = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $weights[$i] * (int) $nip[$i];
        }

        return ($sum % 11) === (int) $nip[9];
    }

    /**
     * Full lookup by NIP. Returns null when NIP is invalid or GUS not
     * configured / unreachable. Otherwise returns:
     *
     *   nip, regon, name, street, building, apartment,
     *   postal_code, city, province, type, krs (optional)
     *
     * @return array<string,mixed>|null
     */
    public function lookupByNip(string $nip): ?array
    {
        if (! self::isValidNip($nip)) {
            return null;
        }

        $gusData = $this->gus->findByNip($nip);
        if ($gusData === null) {
            return null;
        }

        // GUS sometimes returns a KRS in the dataset for legal persons —
        // when present we could enrich; for MVP we just return GUS as-is
        // and let the caller hit KRS separately if needed.
        return $gusData;
    }

    /**
     * Look up a company by KRS number directly (for users entering KRS
     * instead of NIP). Returns subset focused on what's available there.
     *
     * @return array<string,mixed>|null
     */
    public function lookupByKrs(string $krs): ?array
    {
        if (! KrsApiService::isValidKrs($krs)) {
            return null;
        }

        $excerpt = $this->krs->fetchOdpisAktualny($krs);
        if (! is_array($excerpt)) {
            return null;
        }

        $podmiot = (array) data_get($excerpt, 'odpis.dane.dzial1.danePodmiotu', []);
        $siedziba = (array) data_get($excerpt, 'odpis.dane.dzial1.siedzibaIAdres', []);
        $address = (array) ($siedziba['adres'] ?? []);

        return [
            'krs' => $krs,
            'nip' => (string) ($podmiot['identyfikatory']['nip'] ?? ''),
            'regon' => (string) ($podmiot['identyfikatory']['regon'] ?? ''),
            'name' => (string) ($podmiot['nazwa'] ?? ''),
            'legal_form' => (string) ($podmiot['formaPrawna'] ?? ''),
            'street' => (string) ($address['ulica'] ?? '') ?: null,
            'building' => (string) ($address['nrDomu'] ?? '') ?: null,
            'apartment' => (string) ($address['nrLokalu'] ?? '') ?: null,
            'postal_code' => (string) ($address['kodPocztowy'] ?? '') ?: null,
            'city' => (string) ($address['miejscowosc'] ?? '') ?: null,
        ];
    }
}
