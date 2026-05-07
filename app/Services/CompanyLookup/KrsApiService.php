<?php

declare(strict_types=1);

namespace App\Services\CompanyLookup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * KRS Open Data API client (public, no API key required).
 *
 *   GET /api/krs/OdpisAktualny/{krs}?rejestr={P|S}&format=json   bieżący stan
 *   GET /api/krs/OdpisPelny/{krs}?rejestr={P|S}&format=json      pełna historia
 *
 * Cache TTL is long (30 days default) — KRS state changes are
 * deliberate court actions, not frequent. Per Billu-System pattern.
 *
 * Reference: https://api-krs.ms.gov.pl/
 */
class KrsApiService
{
    public const BASE_URL = 'https://api-krs.ms.gov.pl/api/krs';

    public const CACHE_TTL_SEC = 60 * 60 * 24 * 30; // 30 days

    public static function isValidKrs(string $krs): bool
    {
        $krs = preg_replace('/[^0-9]/', '', $krs);

        return strlen((string) $krs) === 10;
    }

    /**
     * @return array<string,mixed>|null  parsed JSON, null on 404 or network failure
     */
    public function fetchOdpisAktualny(string $krs, string $rejestr = 'P'): ?array
    {
        return $this->fetchExcerpt('OdpisAktualny', $krs, $rejestr);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function fetchOdpisPelny(string $krs, string $rejestr = 'P'): ?array
    {
        return $this->fetchExcerpt('OdpisPelny', $krs, $rejestr);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchExcerpt(string $kind, string $krs, string $rejestr): ?array
    {
        $krs = preg_replace('/[^0-9]/', '', $krs);
        if (strlen((string) $krs) !== 10) {
            return null;
        }
        $rejestr = strtoupper($rejestr);
        if (! in_array($rejestr, ['P', 'S'], true)) {
            $rejestr = 'P';
        }

        $cacheKey = "krs:{$kind}:{$rejestr}:{$krs}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SEC, function () use ($kind, $krs, $rejestr) {
            $response = Http::acceptJson()
                ->timeout(15)
                // throw:false — KRS 404 dla nieistniejącego numeru jest
                // normalnym przypadkiem, nie błędem do raportowania.
                ->retry(2, 1000, throw: false)
                ->withUserAgent('Hovera/1.0')
                ->get(self::BASE_URL."/{$kind}/{$krs}", [
                    'rejestr' => $rejestr,
                    'format' => 'json',
                ]);

            // 4xx = ostateczne (rejestr nie istnieje albo zły numer) — nie retry
            if (! $response->successful()) {
                return null;
            }

            $body = $response->json();

            return is_array($body) ? $body : null;
        });
    }
}
