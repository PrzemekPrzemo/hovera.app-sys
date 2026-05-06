<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\SendInvitation;
use App\Models\Central\Tenant;
use App\Models\Central\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_helper_reports_pending_accepted_expired(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $pending = $this->send('a@example.com', $tenant)['invitation'];
        $this->assertSame('pending', $pending->status());

        $accepted = $this->send('b@example.com', $tenant)['invitation'];
        $accepted->forceFill(['accepted_at' => now()])->save();
        $this->assertSame('accepted', $accepted->refresh()->status());

        $expired = $this->send('c@example.com', $tenant)['invitation'];
        $expired->forceFill(['expires_at' => now()->subDay()])->save();
        $this->assertSame('expired', $expired->refresh()->status());
    }

    public function test_pending_scope_excludes_accepted_and_expired(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $pending = $this->send('p@example.com', $tenant)['invitation'];
        $accepted = $this->send('a@example.com', $tenant)['invitation'];
        $accepted->forceFill(['accepted_at' => now()])->save();
        $expired = $this->send('e@example.com', $tenant)['invitation'];
        $expired->forceFill(['expires_at' => now()->subDay()])->save();

        $ids = UserInvitation::pending()->pluck('id')->all();

        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($accepted->id, $ids);
        $this->assertNotContains($expired->id, $ids);
    }

    public function test_resend_creates_new_invitation_and_revokes_old(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $original = $this->send('user@example.com', $tenant);

        // "Resend" is just calling SendInvitation again with the same params.
        $resent = $this->send('user@example.com', $tenant);

        $this->assertNotSame($original['invitation']->id, $resent['invitation']->id);
        $this->assertNotSame($original['invitation']->token_hash, $resent['invitation']->token_hash);
        $this->assertNotSame($original['plaintext_token'], $resent['plaintext_token']);

        // Original is now expired (revoked) and not pending anymore.
        $this->assertFalse($original['invitation']->refresh()->isUsable());
        $this->assertTrue($resent['invitation']->isUsable());

        // Pending scope only contains the new one.
        $pendingIds = UserInvitation::pending()
            ->where('email', 'user@example.com')
            ->pluck('id')->all();
        $this->assertSame([$resent['invitation']->id], $pendingIds);
    }

    public function test_resend_extends_ttl_relative_to_now(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $original = $this->send('user@example.com', $tenant);

        // Move time forward 3 days
        $this->travel(3)->days();

        $resent = $this->send('user@example.com', $tenant);

        // New expiry is ~7 days from "now" (which is 3 days after start),
        // so it sits at least 6 days past the original expiry.
        $this->assertTrue(
            $resent['invitation']->expires_at->gt($original['invitation']->expires_at)
        );
    }

    public function test_old_token_cannot_be_used_after_resend(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();

        $original = $this->send('user@example.com', $tenant);
        $this->send('user@example.com', $tenant);   // resend → revokes original

        $this->expectException(\RuntimeException::class);
        $this->app->make(AcceptInvitation::class)->execute(
            $original['plaintext_token'],
            'StrongPassword12345',
        );
    }

    public function test_revoking_invitation_marks_it_unusable(): void
    {
        Notification::fake();
        $tenant = $this->makeTenant();
        $sent = $this->send('user@example.com', $tenant);

        // Revoke (manual emulation of the Filament action)
        $sent['invitation']->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->assertFalse($sent['invitation']->refresh()->isUsable());

        $this->expectException(\RuntimeException::class);
        $this->app->make(AcceptInvitation::class)->execute(
            $sent['plaintext_token'],
            'StrongPassword12345',
        );
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

    /**
     * @return array{invitation:UserInvitation, plaintext_token:string}
     */
    private function send(string $email, Tenant $tenant): array
    {
        return $this->app->make(SendInvitation::class)->execute(
            email: $email,
            tenant: $tenant,
            role: 'viewer',
        );
    }
}
