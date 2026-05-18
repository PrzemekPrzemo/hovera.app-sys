<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Central\Tenant;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Pre-rendered Open Graph image (1200x630 PNG) dla publicznego profilu
 * transportera `/t/{slug}`. Endpoint serwowany pod `/t/{slug}/og-image.png`
 * i wskazywany przez <meta property="og:image"> w profile.blade.php.
 *
 * Po co osobny endpoint zamiast static asset:
 *   - tagline/nazwa/branding kompozytujemy w runtime z tenant.settings — nie da
 *     się pre-build'ować w buildzie, bo każdy verified transporter to inny obraz.
 *   - file cache na storage/app/public/og-images/transporter/{slug}.png:
 *     pierwsze trafienie z FB/LinkedIn crawlera renderuje + zapisuje, kolejne
 *     streamują z dysku. Cache invalidation = mtime < tenant.updated_at.
 *   - HTTP cache długi (immutable) — social crawler'y agresywnie cache'ują
 *     OG images po pierwszym fetchu, więc kontrolujemy invalidation przez slug
 *     (zmiana brandingu wymusza zmianę bumpa updated_at, my regenerujemy).
 *
 * 404 strategy: te same warunki co TransporterProfileController.resolveTenant
 * (verified + status in trialing|active|past_due). Slug nie matchujący regex'a
 * filtruje router przez `where()`. Dla pending/rejected/Stable/suspended/
 * soft-deleted — 404 (nie ma profilu = nie ma OG image; nie chcemy by ktoś
 * z nieaktywnym kontem mógł generować obrazki na naszym CPU).
 *
 * Rendering: plain GD + bundled DejaVu Sans TTF (resources/fonts/). Bundled
 * (nie system fonts) bo nie każdy production host ma `fonts-dejavu`
 * zainstalowane, a fallback do imagestring() wygląda jak z 1995.
 */
class TransporterOgImageController extends Controller
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const PADDING = 60;

    private const DEFAULT_BG = '#1F1A17';

    private const DISK = 'public';

    public function show(string $slug): Response
    {
        $tenant = $this->resolveTenant($slug);

        if (! $tenant) {
            abort(404);
        }

        $relativePath = "og-images/transporter/{$slug}.png";
        $disk = Storage::disk(self::DISK);

        // File cache: regenerate tylko gdy nie ma pliku albo tenant zaktualizowany
        // po ostatnim render'ze. updated_at bumpa się przy każdej zmianie brandingu/
        // settings, więc wystarczy mtime comparison.
        $needsRender = ! $disk->exists($relativePath)
            || $disk->lastModified($relativePath) < ($tenant->updated_at?->getTimestamp() ?? PHP_INT_MAX);

        if ($needsRender) {
            $png = $this->render($tenant);
            $disk->put($relativePath, $png);
        } else {
            $png = (string) $disk->get($relativePath);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
            // Long s-maxage = social crawlers (FB/LinkedIn) cache'ują u siebie,
            // ale my chcemy by w razie zmiany tenant.updated_at nowy render od
            // razu wyszedł — dlatego file cache invaliduje się po stronie
            // serwera, a HTTP cache jest długie dla crawlerów.
            'Cache-Control' => 'public, max-age=86400, s-maxage=604800, immutable',
        ]);
    }

    /**
     * Re-implementuje TransporterProfileController::resolveTenant. Świadomie
     * duplikujemy ~10 linijek zamiast wyciągać do service'u — chcemy
     * niezależnie ewoluować politykę 404 dla obrazków (np. kiedyś mogą być
     * generowane też dla preview'ów w admin panelu).
     */
    private function resolveTenant(string $slug): ?Tenant
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug)) {
            return null;
        }

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->first();

        if (! $tenant || ! $tenant->isVerifiedTransporter()) {
            return null;
        }

        return $tenant;
    }

    private function render(Tenant $tenant): string
    {
        $branding = (array) ($tenant->branding ?? []);
        $publicProfile = (array) (($tenant->settings ?? [])['public_profile'] ?? []);

        $locale = $this->resolveLocale($tenant);
        $strings = $this->loadStrings($locale);

        $tagline = (string) ($publicProfile['tagline'] ?? $strings['default_tagline']);
        $name = (string) $tenant->name;
        $footer = (string) $strings['footer'];

        $bgHex = (string) ($branding['primary_color'] ?? self::DEFAULT_BG);
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $bgHex)) {
            $bgHex = self::DEFAULT_BG;
        }

        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        if ($img === false) {
            throw new \RuntimeException('Unable to allocate GD image canvas.');
        }

        try {
            $this->paintBackground($img, $bgHex);
            $this->paintWordmark($img);

            $logoPath = isset($branding['logo_url']) && is_string($branding['logo_url'])
                ? $this->fetchLogo($branding['logo_url'])
                : null;
            if ($logoPath !== null) {
                $this->paintLogo($img, $logoPath);
                @unlink($logoPath);
            }

            $this->paintCenter($img, $name, $tagline, $logoPath !== null);
            $this->paintFooter($img, $footer);

            ob_start();
            imagepng($img, null, 6);
            $bytes = (string) ob_get_clean();
        } finally {
            imagedestroy($img);
        }

        return $bytes;
    }

    private function paintBackground(\GdImage $img, string $bgHex): void
    {
        [$r, $g, $b] = $this->hexToRgb($bgHex);

        // Gradient diagonalny: top-left ciemniejszy → bottom-right ~25% jaśniejszy.
        // Robimy ręcznie po liniach bo GD nie ma gradient API. 630 lines * 1 fill,
        // ~3ms dla całego canvas'u. Step kombinujemy z y/H i x/W żeby było
        // diagonalne (kierunek 135deg jak na profile.blade.php hero).
        $h = self::HEIGHT;
        $w = self::WIDTH;
        $lightR = min(255, (int) ($r + (255 - $r) * 0.25));
        $lightG = min(255, (int) ($g + (255 - $g) * 0.25));
        $lightB = min(255, (int) ($b + (255 - $b) * 0.25));

        for ($y = 0; $y < $h; $y++) {
            $ty = $y / ($h - 1);
            // Pojedyncza linia w środkowym tonie y; subtelny shift x dodajemy
            // przez prostokąty po liniach poziomych — w praktyce wystarczy
            // wertykalny gradient żeby nie wyglądało jak placeholder.
            $rr = (int) ($r + ($lightR - $r) * $ty);
            $gg = (int) ($g + ($lightG - $g) * $ty);
            $bb = (int) ($b + ($lightB - $b) * $ty);
            $color = imagecolorallocate($img, $rr, $gg, $bb);
            imageline($img, 0, $y, $w - 1, $y, $color);
        }
    }

    private function paintWordmark(\GdImage $img): void
    {
        $white = imagecolorallocate($img, 255, 255, 255);
        $fontBold = $this->fontPath(bold: true);

        if ($fontBold !== null) {
            // top-left wordmark "hovera" — small, bold, white.
            imagettftext($img, 28, 0, self::PADDING, self::PADDING + 28, $white, $fontBold, 'hovera');
        } else {
            imagestring($img, 5, self::PADDING, self::PADDING, 'hovera', $white);
        }
    }

    private function paintCenter(\GdImage $img, string $name, string $tagline, bool $hasLogo): void
    {
        $white = imagecolorallocate($img, 255, 255, 255);
        $offWhite = imagecolorallocate($img, 233, 226, 211); // hovera ivory

        $fontBold = $this->fontPath(bold: true);
        $fontRegular = $this->fontPath(bold: false);

        // Pivot Y for the headline: jeśli mamy logo skomponowane wyżej (paintLogo
        // ląduje go na y~150), pchnijmy headline w dół żeby się nie nakładał.
        $headlineY = $hasLogo ? 380 : 320;

        if ($fontBold !== null) {
            $nameSize = 70;
            // Auto-fit: jak nazwa szersza niż safe-zone, zmniejsz size do tego co wejdzie.
            $maxWidth = self::WIDTH - 2 * self::PADDING;
            while ($nameSize > 36) {
                $box = imagettfbbox($nameSize, 0, $fontBold, $name);
                $w = abs($box[2] - $box[0]);
                if ($w <= $maxWidth) {
                    break;
                }
                $nameSize -= 4;
            }
            $box = imagettfbbox($nameSize, 0, $fontBold, $name);
            $w = abs($box[2] - $box[0]);
            $x = (int) ((self::WIDTH - $w) / 2);
            imagettftext($img, $nameSize, 0, $x, $headlineY, $white, $fontBold, $name);
        } else {
            // Bitmap fallback — built-in font #5 to max GD ma do dyspozycji.
            $w = imagefontwidth(5) * strlen($name);
            $x = (int) ((self::WIDTH - $w) / 2);
            imagestring($img, 5, $x, $headlineY - 20, $name, $white);
        }

        if ($tagline === '' || $fontRegular === null) {
            if ($tagline !== '' && $fontRegular === null) {
                $w = imagefontwidth(4) * mb_strlen($tagline);
                $x = (int) ((self::WIDTH - $w) / 2);
                imagestring($img, 4, $x, $headlineY + 30, $tagline, $offWhite);
            }

            return;
        }

        $taglineSize = 32;
        $maxWidth = self::WIDTH - 2 * self::PADDING;
        // truncate jeśli za długi — OG image to nie miejsce na elaborate tagline'y
        $tagline = $this->truncateToWidth($tagline, $fontRegular, $taglineSize, $maxWidth);
        $box = imagettfbbox($taglineSize, 0, $fontRegular, $tagline);
        $w = abs($box[2] - $box[0]);
        $x = (int) ((self::WIDTH - $w) / 2);
        imagettftext($img, $taglineSize, 0, $x, $headlineY + 60, $offWhite, $fontRegular, $tagline);
    }

    private function paintFooter(\GdImage $img, string $footer): void
    {
        $offWhite = imagecolorallocate($img, 200, 184, 164); // hovera muted-ivory
        $fontRegular = $this->fontPath(bold: false);

        $y = self::HEIGHT - self::PADDING;

        if ($fontRegular !== null) {
            $size = 22;
            $box = imagettfbbox($size, 0, $fontRegular, $footer);
            $w = abs($box[2] - $box[0]);
            $x = (int) ((self::WIDTH - $w) / 2);
            imagettftext($img, $size, 0, $x, $y, $offWhite, $fontRegular, $footer);
        } else {
            $w = imagefontwidth(3) * strlen($footer);
            $x = (int) ((self::WIDTH - $w) / 2);
            imagestring($img, 3, $x, $y - 14, $footer, $offWhite);
        }
    }

    /**
     * Composite logo z brandingu (max 200x200) w środkowo-górnej części.
     * Akceptujemy local path (gdy fetch zapisał) — jpg/png/gif/webp.
     */
    private function paintLogo(\GdImage $img, string $logoPath): void
    {
        $logo = $this->loadImageFromFile($logoPath);
        if ($logo === null) {
            return;
        }

        try {
            $srcW = imagesx($logo);
            $srcH = imagesy($logo);
            $maxDim = 200;
            $scale = min($maxDim / max(1, $srcW), $maxDim / max(1, $srcH), 1.0);
            $dstW = max(1, (int) ($srcW * $scale));
            $dstH = max(1, (int) ($srcH * $scale));

            $x = (int) ((self::WIDTH - $dstW) / 2);
            $y = 150;

            // Preserve alpha — logo PNG'i często mają przezroczystość.
            imagealphablending($img, true);
            imagesavealpha($img, true);
            imagecopyresampled($img, $logo, $x, $y, 0, 0, $dstW, $dstH, $srcW, $srcH);
        } finally {
            imagedestroy($logo);
        }
    }

    private function loadImageFromFile(string $path): ?\GdImage
    {
        if (! is_file($path)) {
            return null;
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $img = @imagecreatefromstring($bytes);

        return $img === false ? null : $img;
    }

    /**
     * Fetch zdalnego logo do tmp pliku. Timeout 2s — NIGDY nie blokujemy
     * generowania OG image na zewnętrzny zasób. Jak nie zassiemy, jedziemy
     * text-only. Zwraca ścieżkę do tmp pliku lub null.
     */
    private function fetchLogo(string $url): ?string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        try {
            $response = Http::timeout(2)->get($url);
            if (! $response->ok()) {
                return null;
            }
            $body = $response->body();
            if ($body === '' || strlen($body) > 4 * 1024 * 1024) {
                // 4MB cap — logo no powinno być większe; chronimy się przed
                // attacker'em ustawiającym branding.logo_url na 50MB zdjęcie.
                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'hov-og-logo-');
            if ($tmp === false) {
                return null;
            }
            file_put_contents($tmp, $body);

            return $tmp;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function truncateToWidth(string $text, string $fontPath, int $size, int $maxWidth): string
    {
        $box = imagettfbbox($size, 0, $fontPath, $text);
        if (abs($box[2] - $box[0]) <= $maxWidth) {
            return $text;
        }

        $ellipsis = '…';
        while (mb_strlen($text) > 1) {
            $text = mb_substr($text, 0, -1);
            $candidate = rtrim($text).$ellipsis;
            $box = imagettfbbox($size, 0, $fontPath, $candidate);
            if (abs($box[2] - $box[0]) <= $maxWidth) {
                return $candidate;
            }
        }

        return $text;
    }

    private function fontPath(bool $bold): ?string
    {
        $file = $bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf';
        $path = resource_path('fonts/'.$file);

        return is_file($path) ? $path : null;
    }

    private function resolveLocale(Tenant $tenant): string
    {
        $supported = ['pl', 'en', 'de', 'fr', 'ru'];
        $locale = $tenant->locale ?? null;
        if (is_string($locale) && in_array(strtolower(substr($locale, 0, 2)), $supported, true)) {
            return strtolower(substr($locale, 0, 2));
        }

        return 'pl';
    }

    /**
     * @return array{default_tagline:string, footer:string}
     */
    private function loadStrings(string $locale): array
    {
        $key = 'public/transporter_og';

        return [
            'default_tagline' => (string) trans($key.'.default_tagline', [], $locale),
            'footer' => (string) trans($key.'.footer', [], $locale),
        ];
    }
}
