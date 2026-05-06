<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Central\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticator
{
    private const ISSUER = 'Hovera';

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(self::ISSUER, $user->email, $secret);
    }

    public function provisioningQrSvg(string $uri, int $size = 220): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($uri);
    }

    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->google2fa->verifyKey($secret, trim($code), 1);
    }

    /**
     * @return string[]
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }

        return $codes;
    }
}
