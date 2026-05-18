<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\TenantType;
use App\Enums\VerificationStatus;
use App\Models\Central\Tenant;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Publiczne pliki SEO: /sitemap.xml + /robots.txt.
 *
 * Sitemap zawiera tylko strony nadające się do indeksowania — landing, formularz
 * zapytania, kalkulator + każdy zweryfikowany transporter (/t/{slug}) i każda
 * aktywna stajnia (/s/{slug}). Trasy z tokenami (np. /transport/quote/...) oraz
 * panele (/admin, /app, portale klienta) są celowo pominięte / zablokowane w
 * robots.txt — nie chcemy aby boty indeksowały URL-e z poświadczeniami.
 *
 * Cache 1h na poziomie kontrolera — sitemap zmienia się rzadko (nowy
 * transporter / nowa stajnia ≈ kilka dziennie), a generowanie XML wymaga
 * przeszukania całej tabeli tenants.
 */
class SitemapController extends Controller
{
    private const CACHE_KEY_SITEMAP = 'public_sitemap_xml';

    private const CACHE_KEY_ROBOTS = 'public_robots_txt';

    public function sitemap(): Response
    {
        $xml = Cache::remember(
            self::CACHE_KEY_SITEMAP,
            now()->addHour(),
            fn (): string => $this->renderSitemap(),
        );

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function robots(): Response
    {
        $body = Cache::remember(
            self::CACHE_KEY_ROBOTS,
            now()->addHour(),
            fn (): string => view('public.robots')->render(),
        );

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function renderSitemap(): string
    {
        $transporters = Tenant::query()
            ->where('type', TenantType::Transporter)
            ->where('verification_status', VerificationStatus::Verified)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->orderBy('slug')
            ->get(['slug', 'updated_at']);

        $stables = Tenant::query()
            ->where('type', TenantType::Stable)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->orderBy('slug')
            ->get(['slug', 'updated_at']);

        return view('public.sitemap', [
            'transporters' => $transporters,
            'stables' => $stables,
        ])->render();
    }
}
