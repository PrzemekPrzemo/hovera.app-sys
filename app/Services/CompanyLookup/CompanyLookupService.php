<?php

declare(strict_types=1);

namespace App\Services\CompanyLookup;

/**
 * Orchestrates GUS + CEIDG + KRS lookup for company auto-fill.
 *
 * Reguła zapytań:
 *   1. Walidacja NIP-checksum lokalnie (oszczędza wywołania API).
 *   2. GUS po NIP (REGON + nazwa + adres) — źródło dla wszystkich podmiotów.
 *   3. Jeśli CEIDG skonfigurowane: równolegle hit CEIDG po NIP (dla JDG
 *      dostaniemy dokładniejszy adres + status + telefon).
 *   4. Jeśli GUS zwróciło KRS lub typ=osoba prawna: hit KRS żeby uzupełnić
 *      legal_form / kapitał / zarząd.
 *
 * Frontend (Filament action) używa `lookupByNip` i bierze co potrzebuje
 * z połączonego payload'u. Source priority: CEIDG > GUS > KRS dla pól
 * gdzie wszystkie 3 mogą występować (CEIDG ma najświeższe dane JDG).
 */
class CompanyLookupService
{
    public function __construct(
        private readonly GusApiService $gus,
        private readonly KrsApiService $krs,
        private readonly CeidgApiService $ceidg,
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
     * Full lookup by NIP — merge'd GUS + CEIDG + KRS payload.
     *
     * Zwraca null tylko gdy NIP niepoprawny formalnie LUB żadne źródło
     * nic nie zwróciło (typowo: nieaktywne API + nieznany NIP).
     *
     * Klucz `sources` w wynikowym array'u zawiera listę źródeł które
     * zwróciły dane — przydatne dla UI „dane pochodzą z X, Y".
     *
     * @return array<string,mixed>|null
     */
    public function lookupByNip(string $nip): ?array
    {
        if (! self::isValidNip($nip)) {
            return null;
        }

        $gusData = $this->gus->findByNip($nip);
        $ceidgData = $this->ceidg->findByNip($nip);

        if ($gusData === null && $ceidgData === null) {
            return null;
        }

        // CEIDG ma najświeższe dane dla JDG, GUS jest fallbackiem.
        // Merge: CEIDG override'uje GUS field-by-field gdy oba mają wartość.
        $merged = $gusData ?? [];
        if (is_array($ceidgData)) {
            foreach ($ceidgData as $k => $v) {
                if ($v !== null && $v !== '') {
                    $merged[$k] = $v;
                }
            }
        }

        $sources = [];
        if ($gusData !== null) {
            $sources[] = 'gus';
        }
        if ($ceidgData !== null) {
            $sources[] = 'ceidg';
        }

        // Enrich z KRS gdy GUS sugeruje osobę prawną. GUS Typ=P (osoba prawna)
        // lub LP (jednostka lokalna osoby prawnej). Dla osoby fizycznej (F) lub
        // JDG (LF) KRS nie ma wpisu.
        $gusType = (string) ($gusData['type'] ?? '');
        if ($gusType === 'P' || $gusType === 'LP') {
            $krsData = $this->krsLookupForGusPodmiot($merged);
            if (is_array($krsData)) {
                foreach (['legal_form'] as $k) {
                    if (! empty($krsData[$k])) {
                        $merged[$k] = $krsData[$k];
                    }
                }
                $sources[] = 'krs';
            }
        }

        $merged['nip'] = $nip;
        $merged['sources'] = $sources;

        return $merged;
    }

    /**
     * Helper: dla osoby prawnej z GUS spróbuj znaleźć KRS. GUS sam nie zwraca
     * KRS w basic search response — ale REGON zawiera prefix mappable na rejestr.
     * MVP: jeśli caller dostarczy KRS bezpośrednio, używa się `lookupByKrs`.
     * W przyszłości można dodać dodatkowy GUS call (DanePelnyRaport) który
     * zawiera KRS — na razie zwracamy null.
     *
     * @param  array<string,mixed>  $_gusData
     * @return array<string,mixed>|null
     */
    private function krsLookupForGusPodmiot(array $_gusData): ?array
    {
        // Placeholder dla przyszłego enrichmentu — GUS basic API nie zwraca
        // bezpośrednio KRS, byłby potrzebny dodatkowy call DanePelnyRaport.
        // Master admin tymczasowo może użyć osobnego pola KRS jeśli zna.
        return null;
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
            'sources' => ['krs'],
        ];
    }
}
