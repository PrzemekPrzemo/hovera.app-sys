<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Auth\TwoFactorController;
use App\Models\Central\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EncryptCookies in the real middleware stack expects an encrypted
     * value with a CookieValuePrefix; replicate that here so withCookie()
     * survives decryption.
     */
    private function encryptCookie(string $name, string $value): string
    {
        return Crypt::encrypt(
            CookieValuePrefix::create($name, app('encrypter')->getKey()).$value,
            false,
        );
    }

    public function test_unauthenticated_users_get_redirected_from_admin(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect();
    }

    public function test_non_admin_user_is_redirected_to_app_panel(): void
    {
        $user = User::create([
            'email' => 'user@example.com',
            'name' => 'Regular',
            'password' => bcrypt('secret123'),
            'is_master_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect('/app');
    }

    public function test_master_admin_can_reach_admin_when_2fa_disabled(): void
    {
        config()->set('hovera.admin.require_2fa', false);

        $admin = User::create([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_master_admin_without_2fa_is_redirected_to_setup_when_required(): void
    {
        config()->set('hovera.admin.require_2fa', true);

        $admin = User::create([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect(route('two-factor.setup'));
    }

    public function test_valid_remember_device_cookie_skips_2fa_challenge(): void
    {
        config()->set('hovera.admin.require_2fa', true);

        $admin = new User([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);
        $admin->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $cookieValue = json_encode([
            'user_id' => $admin->id,
            'issued_at' => now()->timestamp,
        ]);

        $response = $this->actingAs($admin)
            ->disableCookieEncryption()
            ->withCookie(TwoFactorController::REMEMBER_COOKIE_NAME, $this->encryptCookie(TwoFactorController::REMEMBER_COOKIE_NAME, $cookieValue))
            ->get('/admin');

        // EnsureMasterAdmin should not bounce us to the 2FA challenge —
        // either we land on the panel (200) or Filament redirects to its
        // dashboard (302 to a non-2FA URL).
        $response->assertDontSee('two-factor');
        $this->assertNotEquals(route('two-factor.challenge'), $response->headers->get('Location'));
        $this->assertNotEquals(route('two-factor.setup'), $response->headers->get('Location'));
    }

    public function test_expired_remember_device_cookie_redirects_to_challenge(): void
    {
        config()->set('hovera.admin.require_2fa', true);

        $admin = new User([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);
        $admin->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $cookieValue = json_encode([
            'user_id' => $admin->id,
            'issued_at' => now()->subDays(TwoFactorController::REMEMBER_DAYS + 1)->timestamp,
        ]);

        $this->actingAs($admin)
            ->disableCookieEncryption()
            ->withCookie(TwoFactorController::REMEMBER_COOKIE_NAME, $this->encryptCookie(TwoFactorController::REMEMBER_COOKIE_NAME, $cookieValue))
            ->get('/admin')
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_remember_device_cookie_for_other_user_is_ignored(): void
    {
        config()->set('hovera.admin.require_2fa', true);

        $admin = new User([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'password' => bcrypt('secret123'),
            'is_master_admin' => true,
        ]);
        $admin->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $cookieValue = json_encode([
            'user_id' => 'someone-else-ulid',
            'issued_at' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->disableCookieEncryption()
            ->withCookie(TwoFactorController::REMEMBER_COOKIE_NAME, $this->encryptCookie(TwoFactorController::REMEMBER_COOKIE_NAME, $cookieValue))
            ->get('/admin')
            ->assertRedirect(route('two-factor.challenge'));
    }
}
