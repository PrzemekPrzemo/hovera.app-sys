<?php

declare(strict_types=1);

namespace App\Services\CompanyLookup;

use App\Models\Central\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * GUS BIR (REGON) SOAP 1.2 client. Uses raw HTTP calls (Laravel Http
 * client) instead of PHP's SoapClient because GUS responses are MTOM
 * multipart and SoapClient can't unwrap that without contortions —
 * confirmed by Billu-System's experience.
 *
 * Three-step flow:
 *   1. Zaloguj(API_KEY)            → session id (sid)
 *   2. DaneSzukajPodmioty(NIP)     → REGON + basic data (jpegtype="osoba_fizyczna" / "osoba_prawna" etc.)
 *   3. Wyloguj(sid)                 → cleanup (best-effort)
 *
 * Master-admin configures one API key for whole Hovera installation
 * via SystemSetting key 'gus.api_key' + 'gus.env' (test|prod).
 *
 * GUS docs: https://api.stat.gov.pl/Home/RegonApi
 */
class GusApiService
{
    public const ENDPOINT_TEST = 'https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';

    public const ENDPOINT_PROD = 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/UslugaBIRzewnPubl.svc';

    public const NS_TEMPUR = 'http://CIS/BIR/PUBL/2014/07';

    public const NS_BIR = 'http://CIS/BIR/PUBL/2014/07/DataContract';

    public const SESSION_TTL_SEC = 50 * 60; // GUS sessions live ~60 min, cache 50

    public function isConfigured(): bool
    {
        return SystemSetting::getSecret('gus.api_key') !== null;
    }

    /**
     * Search a company by NIP. Returns null when not found, GUS is
     * unreachable, or the master-admin hasn't configured the API key.
     *
     * @return array{
     *   nip:string, regon:string, name:string,
     *   street:?string, building:?string, apartment:?string,
     *   postal_code:?string, city:?string, type:?string,
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

        // 24h cache — GUS data changes rarely, mainly registration & address
        $cacheKey = "gus:nip:{$nip}";

        return Cache::remember($cacheKey, 60 * 60 * 24, fn () => $this->callSearch($nip));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function callSearch(string $nip): ?array
    {
        $apiKey = SystemSetting::getSecret('gus.api_key');
        if ($apiKey === null) {
            return null;
        }
        $endpoint = SystemSetting::getValue('gus.env', 'test') === 'prod'
            ? self::ENDPOINT_PROD
            : self::ENDPOINT_TEST;

        $sid = $this->login($apiKey, $endpoint);
        if ($sid === null) {
            return null;
        }

        try {
            $rawXml = $this->soapCall(
                $endpoint,
                $sid,
                action: self::NS_TEMPUR.'/IUslugaBIRzewnPubl/DaneSzukajPodmioty',
                bodyXml: '<tns:DaneSzukajPodmioty xmlns:tns="'.self::NS_TEMPUR.'">'
                    .'<tns:pParametryWyszukiwania>'
                    .'<dat:Nip xmlns:dat="'.self::NS_BIR.'">'.htmlspecialchars($nip, ENT_XML1).'</dat:Nip>'
                    .'</tns:pParametryWyszukiwania>'
                    .'</tns:DaneSzukajPodmioty>',
            );
        } finally {
            $this->logout($sid, $endpoint);
        }

        if ($rawXml === null) {
            return null;
        }

        return $this->parseSearchResponse($rawXml, $nip);
    }

    private function login(string $apiKey, string $endpoint): ?string
    {
        $body = '<tns:Zaloguj xmlns:tns="'.self::NS_TEMPUR.'">'
            .'<tns:pKluczUzytkownika>'.htmlspecialchars($apiKey, ENT_XML1).'</tns:pKluczUzytkownika>'
            .'</tns:Zaloguj>';

        $xml = $this->soapCall($endpoint, sid: null, action: self::NS_TEMPUR.'/IUslugaBIRzewnPubl/Zaloguj', bodyXml: $body);
        if ($xml === null) {
            return null;
        }
        if (preg_match('/<ZalogujResult[^>]*>([^<]+)<\/ZalogujResult>/', $xml, $m)) {
            $sid = trim($m[1]);

            return $sid !== '' ? $sid : null;
        }

        return null;
    }

    private function logout(string $sid, string $endpoint): void
    {
        $body = '<tns:Wyloguj xmlns:tns="'.self::NS_TEMPUR.'">'
            .'<tns:pIdentyfikatorSesji>'.htmlspecialchars($sid, ENT_XML1).'</tns:pIdentyfikatorSesji>'
            .'</tns:Wyloguj>';

        $this->soapCall(
            $endpoint,
            sid: null,
            action: self::NS_TEMPUR.'/IUslugaBIRzewnPubl/Wyloguj',
            bodyXml: $body,
        );
    }

    /**
     * Bare-bones SOAP 1.2 envelope POST. `sid` (when set) goes into
     * the WS-Addressing-style `sid` header that GUS uses for session
     * binding.
     *
     * Returns the raw body of the response or null on transport error.
     */
    private function soapCall(string $endpoint, ?string $sid, string $action, string $bodyXml): ?string
    {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<s:Envelope xmlns:s="http://www.w3.org/2003/05/soap-envelope"'
            .' xmlns:a="http://www.w3.org/2005/08/addressing">'
            .'<s:Header>'
            .'<a:Action s:mustUnderstand="1">'.htmlspecialchars($action, ENT_XML1).'</a:Action>'
            .'<a:To s:mustUnderstand="1">'.htmlspecialchars($endpoint, ENT_XML1).'</a:To>'
            .'</s:Header>'
            .'<s:Body>'.$bodyXml.'</s:Body>'
            .'</s:Envelope>';

        $headers = [
            'Content-Type' => 'application/soap+xml; charset=utf-8; action="'.$action.'"',
        ];
        if ($sid !== null) {
            $headers['sid'] = $sid;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->withBody($envelope, 'application/soap+xml')
            ->post($endpoint);

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    /**
     * Parse the inline-XML inside `DaneSzukajPodmiotyResult`. GUS wraps
     * the result as a string-encoded XML, so we libxml-load it again
     * with XXE protection (LIBXML_NONET).
     *
     * @return array<string,mixed>|null
     */
    private function parseSearchResponse(string $rawXml, string $nip): ?array
    {
        if (! preg_match('/<DaneSzukajPodmiotyResult[^>]*>(.+?)<\/DaneSzukajPodmiotyResult>/s', $rawXml, $m)) {
            return null;
        }
        $inner = html_entity_decode((string) $m[1], ENT_XML1);
        if (trim($inner) === '') {
            return null;
        }

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($inner, 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($prev);

        if (! $doc instanceof \SimpleXMLElement) {
            return null;
        }
        $row = $doc->dane ?? null;
        if (! $row instanceof \SimpleXMLElement) {
            return null;
        }

        return [
            'nip' => $nip,
            'regon' => trim((string) ($row->Regon ?? '')),
            'name' => trim((string) ($row->Nazwa ?? '')),
            'street' => trim((string) ($row->Ulica ?? '')) ?: null,
            'building' => trim((string) ($row->NrNieruchomosci ?? '')) ?: null,
            'apartment' => trim((string) ($row->NrLokalu ?? '')) ?: null,
            'postal_code' => trim((string) ($row->KodPocztowy ?? '')) ?: null,
            'city' => trim((string) ($row->Miejscowosc ?? '')) ?: null,
            'province' => trim((string) ($row->Wojewodztwo ?? '')) ?: null,
            'type' => trim((string) ($row->Typ ?? '')) ?: null, // F/P/LF/LP
        ];
    }
}
