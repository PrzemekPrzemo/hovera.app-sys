<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generate PWA PNG icons (192, 512, apple-touch 180) from brand colors using GD.
 *
 * GD nie czyta SVG natywnie, więc nie rasteryzujemy hovera-icon.svg —
 * zamiast tego rysujemy brand placeholder (ochre tło + "h" w cream)
 * primitivami GD. Wyniki nadają się na PWA install icon, ale jakość
 * niższa od dedykowanego designu — TODO: zastąpić export'em z Figmy.
 */
class PwaIconsCommand extends Command
{
    protected $signature = 'hovera:pwa:icons
        {--force : Overwrite existing icons without prompting}';

    protected $description = 'Generate PWA PNG icons (192/512/apple-touch-180) into public/img/pwa.';

    private const DIR = 'img/pwa';

    private const SIZES = [
        'icon-192.png' => 192,
        'icon-512.png' => 512,
        'apple-touch-icon.png' => 180,
    ];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD extension not loaded — cannot generate icons.');

            return self::FAILURE;
        }

        $base = public_path(self::DIR);

        if (! is_dir($base) && ! mkdir($base, 0755, true) && ! is_dir($base)) {
            $this->error("Cannot create directory: {$base}");

            return self::FAILURE;
        }

        foreach (self::SIZES as $file => $size) {
            $path = $base.DIRECTORY_SEPARATOR.$file;

            if (file_exists($path) && ! $this->option('force')) {
                if (! $this->confirm("{$file} already exists — overwrite?", false)) {
                    $this->line("  skipped: {$file}");

                    continue;
                }
            }

            $this->renderIcon($path, $size);
            $this->info("  generated: {$file} ({$size}×{$size})");
        }

        $this->newLine();
        $this->comment('Done. Icons live in public/'.self::DIR);

        return self::SUCCESS;
    }

    /**
     * Draw a brand-coloured PWA icon: ochre rounded square + cream "h" wordmark.
     *
     * Apple ignoruje przezroczystość na home screen, więc tło zawsze pełne.
     */
    private function renderIcon(string $path, int $size): void
    {
        $img = imagecreatetruecolor($size, $size);
        if ($img === false) {
            throw new \RuntimeException('imagecreatetruecolor failed');
        }

        // Brand colors — config/hovera.php.
        $ochre = imagecolorallocate($img, 0xA8, 0x95, 0x6B); // #A8956B
        $cream = imagecolorallocate($img, 0xF7, 0xF4, 0xEF); // #F7F4EF
        $brown = imagecolorallocate($img, 0x3D, 0x2E, 0x22); // #3D2E22

        // Solid ochre background — covers full canvas (maskable safe zone OK
        // bo cały kwadrat jest brand kolorem, mask może obciąć dowolny kształt).
        imagefilledrectangle($img, 0, 0, $size, $size, $ochre);

        // "h" mark — gruba kreska na środku. Używamy GD line/arc bo
        // wbudowane fonty są za małe na 512px — rysujemy primitivami.
        imagesetthickness($img, max(2, (int) round($size * 0.08)));

        // Pionowa kreska (left stem of "h").
        $cx = (int) round($size * 0.34);
        $cy0 = (int) round($size * 0.22);
        $cy1 = (int) round($size * 0.78);
        imageline($img, $cx, $cy0, $cx, $cy1, $cream);

        // Górny "łuk" h — półokrąg w prawą stronę (od środka stroke'u w prawo).
        $arcCx = (int) round($size * 0.52);
        $arcCy = (int) round($size * 0.55);
        $arcR = (int) round($size * 0.23);
        imagearc($img, $arcCx, $arcCy, $arcR * 2, $arcR * 2, 180, 360, $cream);

        // Prawa noga "h" — od końca łuku w dół.
        $rx = $arcCx + $arcR;
        imageline($img, $rx, $arcCy, $rx, $cy1, $cream);

        // Subtelna ramka brown — pomaga na jasnych tłach iOS.
        imagesetthickness($img, max(1, (int) round($size * 0.02)));
        imagerectangle($img, 0, 0, $size - 1, $size - 1, $brown);

        imagepng($img, $path, 6);
        imagedestroy($img);
    }
}
