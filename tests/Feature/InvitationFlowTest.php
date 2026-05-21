<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\SendInvitation;
use App\Models\Central\Tenant;
use App\Models\Central\TenantMembership;
use App\Models\Central\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_invitation_creates_row_and_sends_mail(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $result = $this->app->make(SendInvitation::class)->execute(
            email: 'PRZYKŁAD@example.com',
            tenant: $tenant,
            role: 'instructor',
            name: 'Anna',
        );

        $invitation = $result['invitation'];
        $this->assertSame('przykład@example.com', $invitation->email);
        $this->assertSame('instructor', $invitation->role);
        $this->assertSame($tenant->id, $invitation->tenant_id);
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->assertNotEmpty($result['plaintext_token']);
        $this->assertNotSame($invitation->token_hash, $result['plaintext_token']); // it's hashed

        Notification::assertSentOnDemand(UserInvitationNotification::class);
    }

    public function test_send_invitation_revokes_prior_pending_invitations(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $service = $this->app->make(SendInvitation::class);

        $first = $service->execute('foo@example.com', $tenant, 'viewer')['invitation'];
        $this->assertTrue($first->isUsable());

        $second = $service->execute('foo@example.com', $tenant, 'viewer')['invitation'];

        $this->assertFalse($first->refresh()->isUsable());   // expired
        $this->assertTrue($second->isUsable());
    }

    public function test_accept_invitation_creates_user_and_attaches_membership(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $sent = $this->app->make(SendInvitation::class)->execute(
            email: 'newowner@example.com',
            tenant: $tenant,
            role: 'owner',
            name: 'New Owner',
        );

        $result = $this->app->make(AcceptInvitation::class)->execute(
            $sent['plaintext_token'],
            'StrongPassword12345',
        );

        $this->assertSame('newowner@example.com', $result['user']->email);
        $this->assertSame('New Owner', $result['user']->name);
        $this->assertTrue(Hash::check('StrongPassword12345', $result['user']->password));
        $this->assertNotNull($result['user']->email_verified_at);

        $this->assertNotNull($result['invitation']->accepted_at);

        $this->assertSame(1, TenantMembership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $result['user']->id)
            ->whereNull('revoked_at')
            ->count());
    }

    public function test_accept_invitation_rejects_short_password(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $sent = $this->app->make(SendInvitation::class)->execute('foo@example.com', $tenant, 'viewer');

        $this->expectException(\RuntimeException::class);
        $this->app->make(AcceptInvitation::class)->execute($sent['plaintext_token'], 'short');
    }

    public function test_accept_invitation_rejects_unknown_token(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->app->make(AcceptInvitation::class)->execute('not-a-real-token', 'StrongPassword12345');
    }

    public function test_accept_invitation_rejects_already_used_token(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $sent = $this->app->make(SendInvitation::class)->execute('foo@example.com', $tenant, 'viewer');

        // Use it once
        $this->app->make(AcceptInvitation::class)->execute($sent['plaintext_token'], 'StrongPassword12345');

        // Now reject the second attempt
        $this->expectException(\RuntimeException::class);
        $this->app->make(AcceptInvitation::class)->execute($sent['plaintext_token'], 'AnotherPassword12345');
    }

    public function test_accept_invitation_rejects_expired_token(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $sent = $this->app->make(SendInvitation::class)->execute('foo@example.com', $tenant, 'viewer');

        // Force expiry
        $sent['invitation']->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->expectException(\RuntimeException::class);
        $this->app->make(AcceptInvitation::class)->execute($sent['plaintext_token'], 'StrongPassword12345');
    }

    public function test_invitation_show_route_renders_form_for_valid_token(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $sent = $this->app->make(SendInvitation::class)->execute('foo@example.com', $tenant, 'viewer');

        $this->get('/invite/'.$sent['plaintext_token'])
            ->assertOk()
            ->assertSee('Ustaw hasło')
            ->assertSee('foo@example.com');
    }

    public function test_invitation_show_route_redirects_for_unknown_token(): void
    {
        $this->get('/invite/totally-bogus-token')
            ->assertRedirect(route('login'));
    }

    public function test_invitation_submit_logs_user_in_and_redirects(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $sent = $this->app->make(SendInvitation::class)->execute('foo@example.com', $tenant, 'viewer');

        $this->post('/invite/'.$sent['plaintext_token'], [
            'password' => 'StrongPassword12345',
            'password_confirmation' => 'StrongPassword12345',
        ])->assertRedirect('/app');

        $this->assertAuthenticated();
        $this->assertSame('foo@example.com', auth()->user()->email);

        // Tenant context primed for /app routes
        $this->assertSame($tenant->id, session('current_tenant_id'));
    }

    public function test_invitation_submit_validates_password_match(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $sent = $this->app->make(SendInvitation::class)->execute('foo@example.com', $tenant, 'viewer');

        $this->post('/invite/'.$sent['plaintext_token'], [
            'password' => 'StrongPassword12345',
            'password_confirmation' => 'DIFFERENT-PASSWORD-1234',
        ])->assertSessionHasErrors('password');
    }

    public function test_token_hash_is_sha256_of_plaintext(): void
    {
        $token = UserInvitation::generateToken();
        $hash = UserInvitation::hashToken($token);
        $this->assertSame(hash('sha256', $token), $hash);
        $this->assertSame(64, strlen($hash));
    }

    public function test_send_invitation_soft_fails_when_mail_dispatch_throws(): void
    {
        // Regresja od raportu "brak potwierdzenia na thanks page" — SMTP
        // padał w prod, exception bombował aż do controller'a i user
        // dostawał 'provisioning_failed' zamiast thanks page'a.
        // Teraz mail failure jest soft-fail: invitation row jest w DB,
        // caller dostaje result, admin może resendować ręcznie.

        // Wymuszamy SMTP fail przez ustawienie nieistniejącego mailera.
        config()->set('mail.default', 'broken-driver');
        config()->set('mail.mailers.broken-driver', ['transport' => 'array']);

        // Symulujemy SMTP padu via Notification fasada throwing.
        Notification::partialMock()
            ->shouldReceive('route')
            ->andThrow(new \RuntimeException('SMTP connection refused'));

        // Wyciszamy exception handler żeby report($e) nie crashowało testu —
        // testujemy soft-fail behavior, nie reporter implementation.
        $this->withoutExceptionHandling([]);

        $tenant = $this->makeTenant('soft-fail');

        $result = $this->app->make(SendInvitation::class)->execute(
            email: 'recipient@example.com',
            tenant: $tenant,
            role: 'owner',
        );

        // Invitation row musi być w DB mimo SMTP padu.
        $this->assertNotNull($result['invitation']);
        $this->assertSame('recipient@example.com', $result['invitation']->email);
        $this->assertNotEmpty($result['plaintext_token']);
        $this->assertDatabaseHas('user_invitations', ['id' => $result['invitation']->id]);
    }

    private function makeTenant(string $slug = 'acme'): Tenant
    {
        $t = new Tenant([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'db_name' => 'hovera_t_'.$slug,
            'db_username' => 'hovera_t_'.$slug,
            'status' => 'active',
        ]);
        $t->db_password = 'irrelevant';
        $t->save();

        return $t;
    }
}
