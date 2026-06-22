<?php

declare(strict_types=1);

namespace Tests\Feature\Specialist;

use App\Models\Central\ExternalSpecialist;
use App\Models\Central\SpecialistMagicLink;
use App\Services\TenantAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * `/specialist/setup/{token}` controller end-to-end via web routing.
 * Pokrywa magic link redemption flow PR O5 Channel B:
 *   - GET valid token → form view
 *   - GET invalid/expired/used token → redirect na invalid landing
 *   - POST valid + valid password → password_hash + email_verified_at set,
 *     link marked used, redirect na completed landing
 *   - POST weak password → validation errors
 */
class SetupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(TenantAuditLogger::class, function (MockInterface $m) {
            $m->shouldReceive('record')->andReturnNull();
        });
    }

    public function test_get_with_valid_token_shows_setup_form(): void
    {
        $specialist = $this->makeSpecialist('vet@example.com');
        ['plain_token' => $token] = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        $this->get('/specialist/setup/'.$token)
            ->assertOk()
            ->assertSee('Ustaw hasło', false)
            ->assertSee('vet@example.com');
    }

    public function test_get_with_invalid_token_redirects_to_invalid_landing(): void
    {
        // Token route constraint wymaga 64 hex chars — generujemy taki fake.
        $fake = str_repeat('f', 64);

        $this->get('/specialist/setup/'.$fake)
            ->assertRedirect('/specialist/setup/invalid');
    }

    public function test_get_with_expired_token_redirects_to_invalid_landing(): void
    {
        $specialist = $this->makeSpecialist();
        $issue = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);
        $issue['model']->forceFill(['expires_at' => now()->subHour()])->save();

        $this->get('/specialist/setup/'.$issue['plain_token'])
            ->assertRedirect('/specialist/setup/invalid');
    }

    public function test_get_with_used_token_redirects_to_invalid_landing(): void
    {
        $specialist = $this->makeSpecialist();
        $issue = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);
        $issue['model']->markUsed();

        $this->get('/specialist/setup/'.$issue['plain_token'])
            ->assertRedirect('/specialist/setup/invalid');
    }

    public function test_post_valid_password_completes_setup(): void
    {
        $specialist = $this->makeSpecialist();
        $this->assertNull($specialist->password_hash);
        $this->assertNull($specialist->email_verified_at);

        ['plain_token' => $token, 'model' => $link] = SpecialistMagicLink::issue(
            $specialist,
            SpecialistMagicLink::KIND_INITIAL_SETUP,
        );

        $this->post('/specialist/setup/'.$token, [
            'password' => 'StrongPass2026!',
            'password_confirmation' => 'StrongPass2026!',
        ])->assertRedirect('/specialist/setup/completed');

        $specialist->refresh();
        $this->assertNotNull($specialist->password_hash);
        $this->assertTrue(Hash::check('StrongPass2026!', $specialist->password_hash));
        $this->assertNotNull($specialist->email_verified_at);

        $link->refresh();
        $this->assertNotNull($link->used_at);
    }

    public function test_post_mismatched_confirmation_fails_with_validation_error(): void
    {
        $specialist = $this->makeSpecialist();
        ['plain_token' => $token] = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        $this->from('/specialist/setup/'.$token)
            ->post('/specialist/setup/'.$token, [
                'password' => 'StrongPass2026!',
                'password_confirmation' => 'Different2026!',
            ])
            ->assertSessionHasErrors('password');

        $specialist->refresh();
        $this->assertNull($specialist->password_hash);
    }

    public function test_post_weak_password_fails_with_validation_error(): void
    {
        $specialist = $this->makeSpecialist();
        ['plain_token' => $token] = SpecialistMagicLink::issue($specialist, SpecialistMagicLink::KIND_INITIAL_SETUP);

        $this->from('/specialist/setup/'.$token)
            ->post('/specialist/setup/'.$token, [
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_post_with_invalid_token_redirects_to_invalid_landing(): void
    {
        $fake = str_repeat('a', 64);

        $this->post('/specialist/setup/'.$fake, [
            'password' => 'StrongPass2026!',
            'password_confirmation' => 'StrongPass2026!',
        ])->assertRedirect('/specialist/setup/invalid');
    }

    public function test_token_route_rejects_non_hex_input(): void
    {
        // Token musi być 64 hex chars — route constraint odrzuca inne.
        $this->get('/specialist/setup/not-a-token')
            ->assertNotFound();
    }

    public function test_invalid_landing_page_returns_ok(): void
    {
        $this->get('/specialist/setup/invalid')
            ->assertOk()
            ->assertSee('Link wygasł', false);
    }

    public function test_completed_landing_page_returns_ok(): void
    {
        $this->get('/specialist/setup/completed')
            ->assertOk()
            ->assertSee('Konto aktywowane', false);
    }

    private function makeSpecialist(?string $email = null): ExternalSpecialist
    {
        return ExternalSpecialist::create([
            'email' => $email ?? 'setup-'.uniqid().'@example.com',
            'display_name' => 'dr Setup',
            'specialty' => 'vet',
        ]);
    }
}
