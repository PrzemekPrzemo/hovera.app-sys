<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Central\User;
use App\Services\TwoFactorAuthenticator;
use PHPUnit\Framework\TestCase;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticatorTest extends TestCase
{
    private TwoFactorAuthenticator $totp;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->google2fa = new Google2FA;
        $this->totp = new TwoFactorAuthenticator($this->google2fa);
    }

    public function test_secret_is_base32_and_long_enough(): void
    {
        $secret = $this->totp->generateSecret();
        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_provisioning_uri_includes_issuer_and_email(): void
    {
        $user = new User(['email' => 'foo@example.com']);
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = $this->totp->provisioningUri($user, $secret);

        $this->assertStringContainsString('Hovera', $uri);
        $this->assertStringContainsString('foo%40example.com', $uri);
        $this->assertStringContainsString($secret, $uri);
    }

    public function test_verify_accepts_a_freshly_generated_otp(): void
    {
        $secret = $this->totp->generateSecret();
        $code = $this->google2fa->getCurrentOtp($secret);

        $this->assertTrue($this->totp->verify($secret, $code));
    }

    public function test_verify_rejects_wrong_code(): void
    {
        $secret = $this->totp->generateSecret();
        $this->assertFalse($this->totp->verify($secret, '000000'));
    }

    public function test_recovery_codes_are_unique_and_uppercase(): void
    {
        $codes = $this->totp->generateRecoveryCodes(8);

        $this->assertCount(8, $codes);
        $this->assertSame($codes, array_unique($codes));
        foreach ($codes as $c) {
            $this->assertMatchesRegularExpression('/^[A-F0-9]{10}$/', $c);
        }
    }
}
